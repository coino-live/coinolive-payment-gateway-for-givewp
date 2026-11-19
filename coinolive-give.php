<?php
/**
 * Plugin Name:       Coino Live Crypto Donations for GiveWP
 * Plugin URI:        https://coino.live/api-guide#integrations
 * Description:       Accept donations in USDT, Bitcoin, Ethereum, TRON, TON, Solana and more with GiveWP. Donors pay on the Coino Live checkout; donations update automatically.
 * Version:           1.0.1
 * Author:            Coino Live
 * Author URI:        https://coino.live
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       coinolive-give
 * Domain Path:       /languages
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  give
 */

defined( 'ABSPATH' ) || exit;

define( 'COINOLIVE_GIVE_VERSION', '1.0.1' );
define( 'COINOLIVE_GIVE_FILE', __FILE__ );
define( 'COINOLIVE_GIVE_DIR', plugin_dir_path( __FILE__ ) );
define( 'COINOLIVE_GIVE_URL', plugin_dir_url( __FILE__ ) );

// Only in the download from coino.live (see the file); WordPress.org delivers translations itself.
if ( is_readable( COINOLIVE_GIVE_DIR . 'includes/bundled-translations.php' ) ) {
	require_once COINOLIVE_GIVE_DIR . 'includes/bundled-translations.php';
}

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( '\Give\Framework\PaymentGateways\PaymentGateway' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Coino Live Crypto Donations needs GiveWP 3 or newer to be installed and active.', 'coinolive-give' ) . '</p></div>';
				}
			);
			return;
		}

		require_once COINOLIVE_GIVE_DIR . 'includes/class-coinolive-give-client.php';
		require_once COINOLIVE_GIVE_DIR . 'includes/class-coinolive-give-payments.php';
		require_once COINOLIVE_GIVE_DIR . 'includes/class-coinolive-give-gateway.php';
		CoinoLive_Give_Payments::init();

		add_action(
			'givewp_register_payment_gateway',
			static function ( $registrar ) {
				$registrar->registerGateway( CoinoLive_Give_Gateway::class );
			}
		);

		if ( is_admin() ) {
			require_once COINOLIVE_GIVE_DIR . 'includes/class-coinolive-give-admin.php';
			CoinoLive_Give_Admin::init();
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=coinolive' ) ) . '">' . esc_html__( 'Settings', 'coinolive-give' ) . '</a>' );
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=give_forms&page=coinolive-give' ) ) . '">' . esc_html__( 'Dashboard', 'coinolive-give' ) . '</a>' );
		return $links;
	}
);

// Safety net for missed webhooks: re-check open donations every five minutes.
add_filter(
	'cron_schedules',
	static function ( $schedules ) {
		$schedules['coinolive_give_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes', 'coinolive-give' ),
		);
		return $schedules;
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! wp_next_scheduled( 'coinolive_give_sync' ) ) {
			wp_schedule_event( time() + 300, 'coinolive_give_five_minutes', 'coinolive_give_sync' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'coinolive_give_sync' );
	}
);
