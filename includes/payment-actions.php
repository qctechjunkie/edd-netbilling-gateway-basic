<?php

/**
 * Process Netbilling Purchase
 *
 * @since 1.0.0
 * @param array   $purchase_data Purchase Data
 * @return void
 */
function edd_netbilling_process_payment( $purchase_data ) {
	global $edd_options;

  $account_id			= $edd_options[ 'netbilling_account_id' ];
  $site_tag				= $edd_options[ 'netbilling_site_tag' ];
	$integrity_key	= $edd_options[ 'netbilling_order_integrity_key' ];
	$interactive		= 'https://secure.netbilling.com/gw/native/interactive2.2';

	if( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
		wp_die( __( 'Nonce verification has failed', 'easy-digital-downloads' ), __( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	  edd_clear_errors();

	//Check if any item has a recurring payment flag, this gateway does not support it.
	if( is_array( $purchase_data['cart_details'] ) && ! empty( $purchase_data['cart_details'] ) ) {
		foreach ( $purchase_data['cart_details'] as $item ) {
			if ( isset( $item['item_number']['options']['recurring'] ) ) {
				// error code followed by error message
		    edd_set_error('recurring_download', __('Netbilling Basic does not support recurring payments', 'edd'));
				break;
			}
		}
	}

  //Get any errors from the above checks
  $errors = edd_get_errors( );

	if ( $errors ) {
		//There were errors found, send back to checkout with list of errors
		edd_send_back_to_checkout( $errors );
	} else {
		// Collect payment data
		$payment_data = array(
			'price'         => $purchase_data['price'],
			'date'          => $purchase_data['date'],
			'user_email'    => $purchase_data['user_email'],
			'purchase_key'  => $purchase_data['purchase_key'],
			'currency'      => edd_get_currency(),
			'downloads'     => $purchase_data['downloads'],
			'user_info'     => $purchase_data['user_info'],
			'cart_details'  => $purchase_data['cart_details'],
			'gateway'       => 'netbilling',
			'status'        => ! empty( $purchase_data['buy_now'] ) ? 'private' : 'pending'
		);
	}

	// Record the pending payment
	$payment = edd_insert_payment( $payment_data );

	// Check payment
	if ( ! $payment ) {
		// Record the error
		edd_record_gateway_error( __( 'Payment Error', 'easy-digital-downloads' ), sprintf( __( 'Payment creation failed before sending buyer to Netbilling. Payment data: %s', 'easy-digital-downloads' ), json_encode( $payment_data ) ), $payment );

		// Problems? send back
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	} else {

		// Only send to Netbilling if the pending payment is created successfully
		$listener_url = add_query_arg( 'edd-listener', 'netbilling', home_url( 'index.php' ) );

		// Set the session data to recover this payment in the event of abandonment or error.
		EDD()->session->set( 'edd_resume_payment', $payment );

		// Setup Netbilling arguments
		$args = array(
			'Ecom_Ezic_AccountAndSitetag'					=> $account_id . ':' . $site_tag,
			'Ecom_Cost_Total'      								=> number_format( ( float ) $purchase_data[ 'price' ], 2 ),
			'Ecom_BillTo_Online_Email'						=> $purchase_data['user_email'],
			'Ecom_BillTo_Postal_Name_First'				=> $purchase_data['user_info']['first_name'],
			'Ecom_BillTo_Postal_Name_Last'				=> $purchase_data['user_info']['last_name'],
			'Ecom_ConsumerOrderID'								=> $payment,
			'Ecom_Ezic_Fulfillment_ReturnURL'			=> $listener_url,
			'Ecom_Ezic_Fulfillment_GiveUpURL' 		=> edd_get_failed_transaction_uri( '?payment-id=' . $payment ),
			'Ecom_Ezic_Payment_AuthorizationType'	=> 'SALE',
		);

		// Add taxes to the cart
		if ( edd_use_taxes() ) {
			$args['Ecom_Cost_Tax'] = edd_sanitize_amount( $purchase_data['tax'] );
		}

		$args['Ecom_Receipt_Description'] = '';

		if( is_array( $purchase_data['cart_details'] ) && ! empty( $purchase_data['cart_details'] ) ) {
			foreach ( $purchase_data['cart_details'] as $item ) {
				$item_amount = round( ( $item['subtotal'] / $item['quantity'] ) - ( $item['discount'] / $item['quantity'] ), 2 );
				if( $item_amount <= 0 ) {
					$item_amount = 0;
				}

				$args['Ecom_Receipt_Description'] .= 'Name: ' . stripslashes_deep( html_entity_decode( edd_get_cart_item_name( $item ), ENT_COMPAT, 'UTF-8' ) );
				$args['Ecom_Receipt_Description'] .= '<br />Quatity: ' . $item['quantity'];
				$args['Ecom_Receipt_Description'] .= '<br />Amount: ' . $item_amount;

				if ( edd_use_skus() ) {
					$args['Ecom_Receipt_Description'] .= '<br />SKU: ' . edd_get_download_sku( $item['id'] );
				}

				$args['Ecom_Receipt_Description'] .= '<hr />';
			}
		}

		//Crypto-Hash Fields
		$args['Ecom_Ezic_Security_HashFields']		= 'Ecom_Ezic_AccountAndSitetag Ecom_Cost_Total Ecom_ConsumerOrderID';
		$args['Ecom_Ezic_Security_HashValue_MD5']	= md5( $integrity_key . $args['Ecom_Ezic_AccountAndSitetag'] . $args['Ecom_Cost_Total'] . $args['Ecom_ConsumerOrderID'] );

		$args = apply_filters( 'edd_netbilling_redirect_args', $args, $purchase_data );
		edd_debug_log( 'Netbilling arguments: ' . print_r( $args, true ) );

		// Build query
		$interactive .= '?' . http_build_query( $args );

		// Fix for some sites that encode the entities
		$interactive = str_replace( '&amp;', '&', $interactive );

		// Redirect to Netbilling
		wp_redirect( $interactive );
		exit;
	}
}
add_action( 'edd_gateway_netbilling', 'edd_netbilling_process_payment' );

/**
 * Listen for Netbilling events
 *
 * @since       1.0.0
 * @return      void
 */

function edds_netbilling_event_listener() {
	// Regular Netbilling IPN
	if ( isset( $_GET['edd-listener'] ) && $_GET['edd-listener'] == 'netbilling' ) {
		edd_debug_log( 'Netbilling IPN endpoint loaded' );
		do_action( 'edd_process_netbilling_pn' );

	}

}
add_action( 'init', 'edds_netbilling_event_listener' );

/**
 * Process Netbilling IPN
 *
 * @since 1.0.0
 * @return void
 */
function edd_process_netbilling_pn() {
	global $edd_options;

	$integrity_key	= $edd_options[ 'netbilling_order_integrity_key' ];

	// Check the request method is POST
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] != 'POST' ) {
		return;
	}

	edd_debug_log( 'edd_process_netbilling_ipn() running during Netbilling IPN processing' );

	edd_debug_log( 'Netbilling IPN $_POST: ' . print_r($_POST, true) );

	if( empty( $_POST['Ecom_ConsumerOrderID'] ) ) {
		return;
	}

	$payment = new EDD_Payment( $_POST['Ecom_ConsumerOrderID'] );

	if ( $payment->gateway != 'netbilling' ) {
		return; // this isn't a Netbilling IPN
	}

	if ( $payment->status == 'complete' ) {
		return; // Only complete payments once
	}

	$verify = md5( $integrity_key . $_POST['Ecom_Ezic_Response_TransactionID'] . $_POST['Ecom_Ezic_Response_StatusCode'] . $_POST['Ecom_Ezic_AccountAndSitetag'] . $_POST['Ecom_Cost_Total'] . $_POST['Ecom_ConsumerOrderID'] );

	if ( strtoupper($verify) != $_POST['Ecom_Ezic_ProofOfPurchase_MD5'] ) {
		edd_debug_log( 'Attempt to verify Netbilling Proof of Purchase Failed' );
		edd_debug_log( $verify . ' != ' . $_POST['Ecom_Ezic_ProofOfPurchase_MD5'] );
		$payment->add_note( __( 'Proof of Purchase could not be verified.', 'easy-digital-downloads' ) );
		$payment->status = 'pending';
		return;
	}

	$payment_status = $_POST['Ecom_Ezic_Response_StatusCode'];

	if ( ( '0' == $payment_status || 'F' == $payment_status ) && isset( $_POST['Ecom_Ezic_Response_AuthMessage'] ) ) {
		edd_debug_log( 'Payment not marked as completed' );
		$payment->status = 'failed';
		$payment->transaction_id = sanitize_text_field( $_POST['Ecom_Ezic_Response_TransactionID'] );
		$payment->add_note( __( 'Payment declined or failed. Response message: ' . $_POST['Ecom_Ezic_Response_AuthMessage'], 'easy-digital-downloads' ) );
		$payment->save();

		wp_redirect( edd_get_failed_transaction_uri( ) );
	} else {
		edd_debug_log( 'Payment marked as completed' );
		$payment->add_note( sprintf( __( 'Netbilling Transaction ID: %s', 'easy-digital-downloads' ) , $_POST['Ecom_Ezic_Response_TransactionID'] ) );
		$payment->transaction_id = sanitize_text_field( $_POST['Ecom_Ezic_Response_TransactionID'] );
		$payment->status = 'complete';
		$payment->save();

		// Get rid of cart contents
		edd_empty_cart( );

		// go to the success page
		edd_send_to_success_page( );
	}
}
add_action( 'edd_process_netbilling_pn', 'edd_process_netbilling_pn' );
