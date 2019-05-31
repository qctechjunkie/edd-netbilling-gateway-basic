<?php
/**
 * Add an errors div
 *
 * @since       1.0.0
 * @return      void
 */
function edds_add_netbilling_errors() {
	echo '<div id="edd-netbilling-payment-errors"></div>';
}
add_action( 'edd_after_cc_fields', 'edds_add_netbilling_errors', 999 );

/**
 * Netbilling Remove CC Form
 *
 * Netbilling Basic does not need a CC form, so remove it.
 *
 * @since 1.0.0
 */
add_action( 'edd_netbilling_cc_form', '__return_false' );
