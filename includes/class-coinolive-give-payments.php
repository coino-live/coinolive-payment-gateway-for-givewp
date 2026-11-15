<?php
/**
 * Donations <-> Coino Live payments.
 *
 * A donation only changes from a payment as read back from the API with the site's key:
 * a webhook is just a trigger, so a forged one cannot mark anything paid.
 *
 * @package CoinoLiveGive
 */

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;

defined( 'ABSPATH' ) || exit;

class CoinoLive_Give_Payments {

	const META_PAYMENT  = '_coinolive_payment_id';
	const META_PAYMENTS = '_coinolive_payments';
	const META_CHECKOUT = '_coinolive_checkout_url';
	const META_PRICE    = '_coinolive_price';
	const META_ATTEMPT  = '_coinolive_attempt';
	const META_NONCE    = '_coinolive_nonce';
	const META_STATES   = '_coinolive_states';

	public static function init() {
		add_filter( 'give_get_sections_gateways', array( __CLASS__, 'settings_section' ) );
		add_filter( 'give_get_settings_gateways', array( __CLASS__, 'settings' ) );
		add_action( 'coinolive_give_sync', array( __CLASS__, 'sync_open' ) );
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( 'coinolive_give_sync' ) ) {
					wp_schedule_event( time() + 300, 'coinolive_give_five_minutes', 'coinolive_give_sync' );
				}
			}
		);
	}

	public static function option( $key, $default = '' ) {
		return function_exists( 'give_get_option' ) ? give_get_option( 'coinolive_' . $key, $default ) : $default;
	}

	public static function client() {
		$url = trim( (string) self::option( 'api_url' ) );
		return new CoinoLive_Give_Client( (string) self::option( 'api_key' ), self::is_allowed_api_url( $url ) ? $url : '' );
	}

	/** @return list<string> Currency codes the API can price payments in (cached 12 hours). */
	public static function currencies() {
		$cached = get_transient( 'coinolive_give_currencies' );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}
		$codes = array_values( array_map( static fn( $c ) => (string) $c['code'], self::client()->currencies() ) );
		set_transient( 'coinolive_give_currencies', $codes, 12 * HOUR_IN_SECONDS );
		return $codes;
	}

	/**
	 * The payment for a donation: the current one while it still fits (or money is on its way), otherwise a new one.
	 *
	 * @param string $return_url Where the payment page sends the donor back (the gateway's signed route).
	 * @return array The payment (open, confirming, partially_paid or paid)
	 */
	public static function payment_for( Donation $donation, $return_url ) {
		$id       = (int) $donation->id;
		$amount   = number_format( (float) $donation->amount->formatToDecimal(), 2, '.', '' );
		$currency = strtoupper( (string) $donation->amount->getCurrency()->getCode() );
		$price    = $amount . ' ' . $currency;
		$client   = self::client();

		$existing = (string) give_get_meta( $id, self::META_PAYMENT, true );
		if ( '' !== $existing ) {
			try {
				$payment = $client->get_payment( $existing );
				self::apply( $payment );
				if ( in_array( $payment['status'], array( 'confirming', 'partially_paid', 'paid' ), true )
					|| ( 'open' === $payment['status'] && give_get_meta( $id, self::META_PRICE, true ) === $price ) ) {
					return $payment;
				}
			} catch ( CoinoLive_Give_Exception $e ) {
				if ( 404 !== $e->status ) {
					throw $e;
				}
			}
		}

		$attempt = '' === $existing ? 0 : (int) give_get_meta( $id, self::META_ATTEMPT, true ) + 1;
		// A random per-donation nonce keeps keys unique even if donation IDs repeat (a reset database).
		$nonce = (string) give_get_meta( $id, self::META_NONCE, true );
		if ( '' === $nonce ) {
			$nonce = bin2hex( random_bytes( 8 ) );
			give_update_meta( $id, self::META_NONCE, $nonce );
		}
		$key = 'give-' . $id . '-' . $attempt . '-' . substr( md5( $nonce . '|' . $price . '|' . home_url() ), 0, 16 );

		$data = array(
			'amount'         => $amount,
			'currency'       => $currency,
			'order_id'       => (string) $id,
			/* translators: 1: donation ID, 2: form or site name */
			'description'    => mb_substr( sprintf( __( 'Donation #%1$s to %2$s', 'coinolive-give' ), $id, $donation->formTitle ? $donation->formTitle : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), 0, 500 ),
			'customer_email' => is_email( $donation->email ) ? $donation->email : null,
			'success_url'    => $return_url,
			'cancel_url'     => $return_url,
			'expires_in'     => max( 15, min( 10080, (int) self::option( 'expires_in', 60 ) ) ),
			'metadata'       => array(
				'give_donation_id' => (string) $id,
				'site'             => home_url( '/' ),
			),
		);
		$notify = CoinoLive_Give_Gateway::webhook_url();
		if ( self::is_public_url( $notify ) ) {
			$data['notify_url'] = $notify;
		}

		$payment = $client->create_payment( $data, $key );
		if ( (string) ( $payment['metadata']['give_donation_id'] ?? '' ) !== (string) $id || 'open' !== $payment['status'] ) {
			throw new CoinoLive_Give_Exception( __( 'Coino Live returned a payment that does not belong to this donation. Please try again.', 'coinolive-give' ) );
		}

		$all   = (array) give_get_meta( $id, self::META_PAYMENTS, true );
		$all[] = $payment['id'];
		give_update_meta( $id, self::META_PAYMENTS, array_values( array_unique( array_filter( $all ) ) ) );
		give_update_meta( $id, self::META_PAYMENT, $payment['id'] );
		give_update_meta( $id, self::META_CHECKOUT, $payment['checkout_url'] );
		give_update_meta( $id, self::META_PRICE, $price );
		give_update_meta( $id, self::META_ATTEMPT, $attempt );
		/* translators: 1: payment ID, 2: amount in USD */
		self::note( $id, sprintf( __( 'Coino Live payment %1$s created (%2$s USD). Waiting for the donor to pay.', 'coinolive-give' ), $payment['id'], $payment['amount'] ) );

		return $payment;
	}

	/**
	 * Brings the donation in line with the payment (as read from the API). Safe to call any number of times.
	 *
	 * @return string paid, updated, unchanged or unknown
	 */
	public static function apply( array $payment ) {
		$id       = (int) ( $payment['metadata']['give_donation_id'] ?? 0 );
		$donation = $id ? Donation::find( $id ) : null;
		// Only payments this site created, for the donation they were created for.
		if ( ! $donation || CoinoLive_Give_Gateway::id() !== $donation->gatewayId || ! in_array( $payment['id'], (array) give_get_meta( $id, self::META_PAYMENTS, true ), true ) ) {
			return 'unknown';
		}

		$states = (array) give_get_meta( $id, self::META_STATES, true );
		$state  = $payment['status'] . '|' . $payment['received_usd'];
		if ( ( $states[ $payment['id'] ] ?? '' ) === $state ) {
			return 'unchanged';
		}

		$current = (string) $donation->status->getValue();
		$done    = in_array( $current, array( DonationStatus::COMPLETE, DonationStatus::REFUNDED, DonationStatus::REVOKED ), true );
		$latest  = give_get_meta( $id, self::META_PAYMENT, true ) === $payment['id'];
		$result  = 'updated';

		switch ( $payment['status'] ) {
			case 'paid':
				$requested = $payment['requested_amount'] . ' ' . $payment['requested_currency'];
				if ( $done ) {
					/* translators: 1: payment ID, 2: list of deposits */
					self::note( $id, sprintf( __( 'Another Coino Live payment (%1$s) was paid for this donation: %2$s.', 'coinolive-give' ), $payment['id'], self::deposits( $payment ) ) );
				} elseif ( $latest && give_get_meta( $id, self::META_PRICE, true ) !== $requested ) {
					self::set_status( $donation, DonationStatus::PROCESSING() );
					/* translators: 1: amount paid for, 2: donation amount */
					self::note( $id, sprintf( __( 'Coino Live payment is paid but was for %1$s, the donation is %2$s. Please check.', 'coinolive-give' ), $requested, give_get_meta( $id, self::META_PRICE, true ) ) );
				} else {
					$donation->gatewayTransactionId = (string) $payment['id'];
					self::set_status( $donation, DonationStatus::COMPLETE() );
					/* translators: 1: payment ID, 2: list of deposits */
					self::note( $id, sprintf( __( 'Coino Live payment %1$s confirmed: %2$s', 'coinolive-give' ), $payment['id'], self::deposits( $payment ) ) );
					$result = 'paid';
				}
				break;
			case 'confirming':
				if ( ! $done ) {
					self::set_status( $donation, DonationStatus::PROCESSING() );
				}
				self::note( $id, __( 'Coino Live: the payment was sent and is waiting for network confirmations.', 'coinolive-give' ) );
				break;
			case 'partially_paid':
				if ( ! $done ) {
					self::set_status( $donation, DonationStatus::PROCESSING() );
				}
				/* translators: 1: USD received, 2: USD expected */
				self::note( $id, sprintf( __( 'Coino Live: only %1$s of %2$s USD received so far. The donor was asked to send the rest.', 'coinolive-give' ), $payment['received_usd'], $payment['amount'] ) );
				break;
			case 'expired':
			case 'cancelled':
				if ( (float) $payment['received_usd'] > 0 ) {
					if ( ! $done ) {
						self::set_status( $donation, DonationStatus::PROCESSING() );
					}
					/* translators: 1: USD received, 2: USD expected */
					self::note( $id, sprintf( __( 'Coino Live: the payment closed with %1$s of %2$s USD received (in your Coino Live balance).', 'coinolive-give' ), $payment['received_usd'], $payment['amount'] ) );
				} elseif ( $latest && in_array( $current, array( DonationStatus::PENDING, DonationStatus::PROCESSING ), true ) ) {
					self::set_status( $donation, DonationStatus::FAILED() );
					self::note( $id, __( 'Coino Live: the payment window closed without payment.', 'coinolive-give' ) );
				}
				break;
			default:
				// open: nothing to record.
		}

		$states                   = (array) give_get_meta( $id, self::META_STATES, true );
		$states[ $payment['id'] ] = $state;
		give_update_meta( $id, self::META_STATES, $states );

		return $result;
	}

	/** Re-reads a donation's current payment and applies it. */
	public static function refresh( $donation_id ) {
		$payment_id = (string) give_get_meta( (int) $donation_id, self::META_PAYMENT, true );
		if ( '' === $payment_id ) {
			return 'unknown';
		}

		return self::apply( self::client()->get_payment( $payment_id ) );
	}

	/** Safety net for missed webhooks (WP-Cron, every five minutes). */
	public static function sync_open() {
		if ( '' === trim( (string) self::option( 'api_key' ) ) || ! function_exists( 'give_get_payments' ) ) {
			return;
		}
		$ids = give_get_payments(
			array(
				'gateway'    => CoinoLive_Give_Gateway::id(),
				'status'     => array( 'pending', 'processing' ),
				'number'     => 50,
				'fields'     => 'ids',
				'date_query' => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ) ) ),
			)
		);
		foreach ( (array) $ids as $id ) {
			try {
				self::refresh( is_object( $id ) ? $id->ID : (int) $id );
			} catch ( CoinoLive_Give_Exception $e ) {
				if ( 0 === $e->status || $e->status >= 500 ) {
					break; // API unreachable: try again on the next run.
				}
			}
		}
	}

	public static function settings_section( $sections ) {
		$sections['coinolive'] = __( 'Coino Live (crypto)', 'coinolive-give' );
		return $sections;
	}

	public static function settings( $settings ) {
		if ( ! function_exists( 'give_get_current_setting_section' ) || 'coinolive' !== give_get_current_setting_section() ) {
			return $settings;
		}

		return array(
			array(
				'id'   => 'coinolive_title',
				'type' => 'title',
				'desc' => self::connection_status(),
			),
			array(
				'id'   => 'coinolive_api_key',
				'name' => __( 'API key', 'coinolive-give' ),
				'desc' => __( 'Create one in your Coino Live dashboard under Developers. Keys start with ck_live_ (or ck_test_ on the test network).', 'coinolive-give' ),
				'type' => 'api_key',
			),
			array(
				'id'   => 'coinolive_webhook_secret',
				'name' => __( 'Webhook signing secret (optional)', 'coinolive-give' ),
				'desc' => __( 'From your gateway settings (whsec_…). When set, webhooks with a wrong signature are refused. Donations are always checked against the API before they change, so this is an extra layer.', 'coinolive-give' ),
				'type' => 'api_key',
			),
			array(
				'id'      => 'coinolive_expires_in',
				'name'    => __( 'Payment window (minutes)', 'coinolive-give' ),
				'desc'    => __( 'How long the donor has to send the payment. Between 15 and 10080.', 'coinolive-give' ),
				'type'    => 'number',
				'default' => 60,
			),
			array(
				'id'      => 'coinolive_api_url',
				'name'    => __( 'API URL', 'coinolive-give' ),
				'desc'    => __( 'Leave as is unless Coino Live support tells you otherwise.', 'coinolive-give' ),
				'type'    => 'text',
				'default' => CoinoLive_Give_Client::DEFAULT_URL,
			),
			array(
				'id'   => 'coinolive_title',
				'type' => 'sectionend',
			),
		);
	}

	/** Shown on the settings page: is the key accepted, and can the site currency be priced? */
	private static function connection_status() {
		if ( '' === trim( (string) self::option( 'api_key' ) ) ) {
			return esc_html__( 'Add your API key below and save.', 'coinolive-give' );
		}
		$url = trim( (string) self::option( 'api_url' ) );
		if ( '' !== $url && ! self::is_allowed_api_url( $url ) ) {
			return '<span style="color:#b32d2e">' . esc_html__( 'The API URL must start with https://.', 'coinolive-give' ) . '</span>';
		}
		try {
			delete_transient( 'coinolive_give_currencies' );
			$currency = function_exists( 'give_get_currency' ) ? strtoupper( (string) give_get_currency() ) : 'USD';
			if ( ! in_array( $currency, self::currencies(), true ) ) {
				/* translators: %s: currency code */
				return '<span style="color:#b32d2e">' . esc_html( sprintf( __( 'Connected, but Coino Live cannot price payments in %s yet.', 'coinolive-give' ), $currency ) ) . '</span>';
			}
			return '<span style="color:#008a20">' . esc_html__( 'Connected to Coino Live.', 'coinolive-give' ) . '</span> ' . esc_html__( 'Webhook URL (sent with every payment, nothing to configure):', 'coinolive-give' ) . ' <code>' . esc_html( CoinoLive_Give_Gateway::webhook_url() ) . '</code>';
		} catch ( CoinoLive_Give_Exception $e ) {
			/* translators: %s: error message */
			return '<span style="color:#b32d2e">' . esc_html( sprintf( __( 'Coino Live refused the API key: %s', 'coinolive-give' ), $e->getMessage() ) ) . '</span>';
		}
	}

	/** X-Coino-Signature: t=<unix time>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")> */
	public static function valid_signature( $header, $body, $secret ) {
		$parts = array();
		foreach ( explode( ',', (string) $header ) as $pair ) {
			$kv = explode( '=', trim( $pair ), 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ] = $kv[1];
			}
		}
		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) || ! ctype_digit( $parts['t'] ) || abs( time() - (int) $parts['t'] ) > 300 ) {
			return false;
		}

		return hash_equals( hash_hmac( 'sha256', $parts['t'] . '.' . $body, $secret ), $parts['v1'] );
	}

	/** False for local development sites, which Coino Live cannot (and must not) call. */
	public static function is_public_url( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $host || 'localhost' === $host || preg_match( '/\.(local|localhost|test|internal)$/', $host ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return true;
	}

	private static function set_status( Donation $donation, DonationStatus $status ) {
		if ( (string) $donation->status->getValue() !== (string) $status->getValue() ) {
			$donation->status = $status;
		}
		$donation->save();
	}

	private static function note( $donation_id, $text ) {
		DonationNote::create(
			array(
				'donationId' => (int) $donation_id,
				'content'    => $text,
			)
		);
	}

	private static function deposits( array $payment ) {
		$lines = array();
		foreach ( (array) ( $payment['deposits'] ?? array() ) as $d ) {
			$lines[] = sprintf( '%s %s (≈ %s USD), tx %s', $d['amount'], $d['symbol'] ?? strtoupper( (string) $d['asset'] ), $d['usd_value'], $d['tx_hash'] );
		}
		return implode( '; ', $lines );
	}

	private static function is_allowed_api_url( $url ) {
		return 0 === strpos( $url, 'https://' ) || (bool) preg_match( '#^http://(localhost|127\.0\.0\.1)(:\d+)?/#', $url );
	}
}
