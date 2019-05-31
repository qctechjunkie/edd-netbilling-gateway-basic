<?php

/**
 * Register Netbilling gateway subsection
 *
 * @since  1.0.0
 * @param  array $gateway_sections  Current Gateway Tab subsections
 * @return array                    Gateway subsections with Netbilling
 */
function edd_register_netbilling_gateway_section( $gateway_sections ) {
    $gateway_sections[ 'netbilling' ] = __( 'Netbilling - Basic', 'easy-digital-downloads' );

    return $gateway_sections;
}
add_filter( 'edd_settings_sections_gateways', 'edd_register_netbilling_gateway_section', 1, 1 );


/**
 * Registers Netbilling settings for the Netbilling subsection
 *
 * @since  1.0.0
 * @param  array $gateway_settings  Gateway tab settings
 * @return array                    Gateway tab settings with the Netbilling settings
 */
function edd_register_netbilling_gateway_settings( $gateway_settings ) {
  $netbilling_settings = array(
    array(
      'id'    => 'netbilling_settings',
      'name'  => '<strong>' . __( 'Netbilling Settings', 'easy-digital-downloads' ) . '</strong>',
      'type'  => 'header'
    ),

    array(
      'id'    => 'netbilling_account_id',
      'name'  => __( 'Account ID', 'easy-digital-downloads' ),
      'desc'  => __( 'This is the Account ID for your Netbilling account', 'edd_netbilling' ),
      'type'  => 'text',
      'size'  => 'regular'
    ),

    array(
      'id'    => 'netbilling_site_tag',
      'name'  => __( 'Site Tag', 'easy-digital-downloads' ),
      'desc'  => __( 'This can be configured from your Netbilling account and controls which email templates will be used. It also tags the site for accounting purposes if you are using the same merchant account across multiple sites.', 'edd_netbilling' ),
      'type'  => 'text',
      'size'  => 'regular'
    ),

    array(
      'id'    => 'netbilling_order_integrity_key',
      'name'  => __( 'Order Integrity Key', 'easy-digital-downloads' ),
      'desc'  => __( 'Order integrity protects merchants from a hacker that attempts to circumvent the intent of the merchant by editing the HTML from a merchant\'s shopping cart and changing the details of their order just before purchase.', 'edd_netbilling' ),
      'type'  => 'text',
      'size'  => 'regular'
    ),
  );

  $netbilling_settings = apply_filters( 'edd_netbilling_settings', $netbilling_settings );
  $gateway_settings[ 'netbilling' ] = $netbilling_settings;

  return $gateway_settings;
}
add_filter( 'edd_settings_gateways', 'edd_register_netbilling_gateway_settings',  1, 1 );
