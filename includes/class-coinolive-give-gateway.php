<?php
/**
 * The Coino Live gateway for GiveWP (visual form builder and classic forms).
 *
 * @package CoinoLiveGive
 */

use Give\Donations\Models\Donation;
use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;

defined( 'ABSPATH' ) || exit;

class CoinoLive_Give_Gateway extends PaymentGateway {

	/** Public: Coino Live posts payment changes here. */
	public $routeMethods = array( 'coinoliveWebhook' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- GiveWP property.

	/** Signed per donation: the donor comes back here from the payment page. */
	public $secureRouteMethods = array( 'returnFromCoinoLive' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- GiveWP property.

	public static function id(): string {
		return 'coinolive';
	}

	public function getId(): string {
		return self::id();
	}

	public function getName(): string {
		return __( 'Coino Live (crypto)', 'coinolive-give' );
	}

	public function getPaymentMethodLabel(): string {
		return __( 'Donate with crypto', 'coinolive-give' );
	}

	/** Visual form builder: the message shown when the donor picks crypto. */
	public function formSettings( int $formId ): array {
		return array(
			'message' => __( 'Donate with USDT, Bitcoin, Ethereum, TRON, TON, Solana and more. You will be taken to a secure payment page.', 'coinolive-give' ),
		);
	}

	public function enqueueScript( int $formId ) {
		wp_enqueue_script( 'coinolive-give-gateway', COINOLIVE_GIVE_URL . 'assets/js/gateway.js', array( 'wp-element' ), COINOLIVE_GIVE_VERSION, true );
	}

	/** Classic (v2) forms. */
	public function getLegacyFormFieldMarkup( int $formId, array $args ): string {
		return '<fieldset class="no-fields"><p>' . esc_html( $this->formSettings( $formId )['message'] ) . '</p></fieldset>';
	}

	/** Creates (or reuses) the Coino Live payment and sends the donor to it. */
	public function createPayment( Donation $donation, $gatewayData ) {
		if ( '' === trim( (string) CoinoLive_Give_Payments::option( 'api_key' ) ) ) {
			throw new PaymentGatewayException( __( 'Crypto donations are not set up yet. Please choose another payment method.', 'coinolive-give' ) );
		}

		$success = (string) ( $gatewayData['successUrl'] ?? '' );
		if ( '' === $success ) {
			$success = class_exists( '\Give\DonationForms\Actions\GenerateDonationConfirmationReceiptViewRouteUrl' )
				? ( new \Give\DonationForms\Actions\GenerateDonationConfirmationReceiptViewRouteUrl() )( $donation->purchaseKey )
				: ( function_exists( 'give_get_success_page_uri' ) ? give_get_success_page_uri() : home_url( '/' ) );
		}
		$cancel = (string) ( $gatewayData['cancelUrl'] ?? ( $gatewayData['failedUrl'] ?? home_url( '/' ) ) );

		$return = $this->generateSecureGatewayRouteUrl(
			'returnFromCoinoLive',
			(int) $donation->id,
			array(
				'givewp-donation-id' => (int) $donation->id,
				'givewp-return-url'  => $success,
				'givewp-cancel-url'  => $cancel,
			)
		);

		try {
			$payment = CoinoLive_Give_Payments::payment_for( $donation, $return );
		} catch ( CoinoLive_Give_Exception $e ) {
			/* translators: %s: error message */
			throw new PaymentGatewayException( sprintf( __( 'Crypto donations are not available right now: %s', 'coinolive-give' ), $e->getMessage() ) );
		}

		return new RedirectOffsite( in_array( $payment['status'], array( 'paid', 'confirming' ), true ) ? $success : $payment['checkout_url'] );
	}

	public function refundDonation( Donation $donation ) {
		throw new PaymentGatewayException( __( 'Crypto donations are refunded from the Coino Live dashboard.', 'coinolive-give' ) );
	}

	/** Back from the payment page: re-check, then the receipt (paid or on its way) or the form (not paid). */
	protected function returnFromCoinoLive( array $queryParams ): RedirectResponse {
		$id = (int) ( $queryParams['givewp-donation-id'] ?? 0 );
		try {
			CoinoLive_Give_Payments::refresh( $id );
		} catch ( CoinoLive_Give_Exception $e ) {
			// The webhook and the cron job will update the donation.
		}
		$state = (array) give_get_meta( $id, CoinoLive_Give_Payments::META_STATES, true );
		$now   = explode( '|', (string) ( $state[ (string) give_get_meta( $id, CoinoLive_Give_Payments::META_PAYMENT, true ) ] ?? 'open|0' ) )[0];

		return new RedirectResponse( in_array( $now, array( 'paid', 'confirming', 'partially_paid' ), true ) ? $queryParams['givewp-return-url'] : $queryParams['givewp-cancel-url'] );
	}

	/** A webhook only says "this payment changed"; the donation is updated from the payment as read back from the API. */
	// Not "webhook": GiveWP's base class has a static webhook() of its own.
	protected function coinoliveWebhook( array $queryParams ) {
		$body   = (string) file_get_contents( 'php://input', false, null, 0, 65536 );
		$secret = trim( (string) CoinoLive_Give_Payments::option( 'webhook_secret' ) );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only.
			wp_send_json( array( 'message' => 'Method not allowed.' ), 405 );
		}
		if ( '' !== $secret && ! CoinoLive_Give_Payments::valid_signature( (string) ( $_SERVER['HTTP_X_COINO_SIGNATURE'] ?? '' ), $body, $secret ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- verified as an HMAC.
			wp_send_json( array( 'message' => 'Invalid signature.' ), 401 );
		}

		$event = json_decode( $body, true );
		$id    = is_array( $event ) ? (string) ( $event['data']['id'] ?? '' ) : '';
		if ( 0 !== strpos( (string) ( $event['event'] ?? '' ), 'payment.' ) || ! preg_match( '/^[0-9a-z]{26}$/', $id ) ) {
			wp_send_json( array( 'ignored' => true ), 200 );
		}

		try {
			$payment = CoinoLive_Give_Payments::client()->get_payment( $id );
		} catch ( CoinoLive_Give_Exception $e ) {
			wp_send_json( array( 'message' => 404 === $e->status ? 'Unknown payment.' : 'Try again later.' ), 404 === $e->status ? 200 : 503 );
		}

		wp_send_json( array( 'ok' => true, 'result' => CoinoLive_Give_Payments::apply( $payment ) ), 200 );
	}

	/** Where Coino Live posts payment changes (sent with every payment). */
	public static function webhook_url(): string {
		return add_query_arg(
			array(
				'give-listener'       => 'give-gateway',
				'give-gateway-id'     => self::id(),
				'give-gateway-method' => 'coinoliveWebhook',
			),
			home_url( '/' )
		);
	}
}
