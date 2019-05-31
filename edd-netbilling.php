<?php
/**
 * Plugin Name: Easy Digital Downloads - Netbilling Payment Gateway Basic
 * Plugin URI: https://qctechjunkie.com
 * Description: Provides Netbilling Payment Form access for Easy Digital Downloads
 * Version: 1.0.0
 * Copyright: 2019, TechJunkie LLC
 * Author: TechJunkie LLC
 * Author URI: https://qctechjunkie.com
 *
 * Netbilling Payment Gateway Basic for EDD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Netbilling Payment Gateway Basic for EDD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Netbilling Payment Gateway Basic for EDD. If not, see <http://www.gnu.org/licenses/>.
 */


 class EDD_Netbilling_Basic {

 	private static $instance;

 	private function __construct() {

 	}

 	public static function instance() {
 		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Netbilling_Basic ) ) {
 			self::$instance = new EDD_Netbilling_Basic;

 			if( version_compare( PHP_VERSION, '5.3.3', '<' ) ) {

 				add_action( 'admin_notices', self::below_php_version_notice() );

 			} else {

 				self::$instance->setup_constants();
 				self::$instance->includes();
 				self::$instance->filters();

 			}
 		}

 		return self::$instance;
 	}

 	function below_php_version_notice() {
 		echo '<div class="error"><p>' . __( 'Your version of PHP is below the minimum version of PHP required by Easy Digital Downloads - Netbilling Basic Payment Gateway. Please contact your host and request that your version be upgraded to 5.3.3 or later.', 'eddn' ) . '</p></div>';
 	}

 	private function setup_constants() {
 		if ( ! defined( 'EDD_NETBILLING_PLUGIN_DIR' ) ) {
 			define( 'EDD_NETBILLING_PLUGIN_DIR', dirname( __FILE__ ) );
 		}

 		if ( ! defined( 'EDDNETBILLING_PLUGIN_URL' ) ) {
 			define( 'EDDNETBILLING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
 		}

 		define( 'EDD_NETBILLING_VERSION', '1.0.0' );
 	}

 	private function includes() {

 		require_once EDD_NETBILLING_PLUGIN_DIR . '/includes/functions.php';
 		require_once EDD_NETBILLING_PLUGIN_DIR . '/includes/payment-actions.php';
 		require_once EDD_NETBILLING_PLUGIN_DIR . '/includes/template-functions.php';

    if ( is_admin() ) {
			require_once EDD_NETBILLING_PLUGIN_DIR . '/includes/admin/settings.php';
		}

 	}

 	private function filters() {
 		add_filter( 'edd_payment_gateways', array( self::$instance, 'register_gateway' ) );
 	}

  public function register_gateway( $gateways ) {
		// Format: ID => Name
		$gateways['netbilling'] = array(
			'admin_label'    => 'Netbilling - Basic',
			'checkout_label' => __( 'Netbilling', 'edds' ),
		);
		return $gateways;
	}


 }

 function edd_netbilling() {

 	if( ! function_exists( 'EDD' ) ) {
 		return;
 	}

 	return EDD_Netbilling_Basic::instance();
 }
 add_action( 'plugins_loaded', 'edd_netbilling', 10 );

 /**
  * Plugin activation
  *
  * @since       1.0.0
  * @return      void
  */
 function edd_netbilling_plugin_activation() {

 	if( ! function_exists( 'EDD' ) ) {
 		return;
 	}

 	global $edd_options;

 	$changed = false;
 	$options = get_option( 'edd_settings', array() );

 	if( $changed ) {

 		$options['netbilling_checkout'] = 1;
 		$options['gateways']['netbilling'] = 1;

 		if( isset( $options['gateway']['netbilling_checkout'] ) ) {
 			unset( $options['gateway']['netbilling_checkout'] );
 		}

 		$merged_options = array_merge( $edd_options, $options );
 		$edd_options    = $merged_options;
 		update_option( 'edd_settings', $merged_options );

 	}

 	if( is_plugin_active( 'edd-netbilling-gateway/edd-netbilling-gateway.php' ) ) {
 		deactivate_plugins( 'edd-netbilling-gateway/edd-netbilling-gateway.php' );
 	}

 }
 register_activation_hook( __FILE__, 'edd_netbilling_plugin_activation' );

 /**
  * Register our payment gateway
  *
  * @since       1.0.0
  * @return      array
  */
 function eddn_register_gateway( $gateways ) {
 	return edd_netbilling()->register_gateway( $gateways );
 }
