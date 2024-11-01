<?php
/*
Plugin Name: WooCommerce PayPlus+ (ISR) Payment Gateway 
Plugin URI: https://wordpress.org/plugins/payplus
Description: PayPlus+ 3D Secure Payment gateway for WooCommerce
Version: 3.0.4
Author: PayPlus+
Author URI: http://www.payplus.co.il
*/

add_action('plugins_loaded', 'init_pp_payplus_class', 0);

function init_pp_payplus_class(){
	if(!class_exists('WC_Payment_Gateway')) return;
 
	class WC_Gateway_PayPlus extends WC_Payment_Gateway{
		var $pfsResponseUrl;
		
		public function __construct(){
			$this -> id = 'payplus';
			$this -> medthod_title = 'PayPlus+';
			$this -> method_description = 'PayPlus+ Credit Card Secure Payment';
			$this -> icon = plugins_url( 'images/payplus.gif' , __FILE__ );
			$this -> has_fields = false;
			
			$this -> init_form_fields();
			$this -> init_settings();
			
			$this -> title = $this -> settings['title'];
			$this -> description = $this -> settings['description'];
			$this -> pfsAuthCode = $this -> settings['pfsAuthCode'];
			$this -> pfsAuthUrl = $this -> settings['pfsAuthUrl'];
			$this -> pfsPaymentUrl = $this -> settings['pfsPaymentUrl'];
			$this -> pfsResponseUrl = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_PayPlus', home_url( '/' ) ) );
	 		
	 		$this -> pfsIsUsePayPlusIpn = $this -> settings['pfsIsUsePayPlusIpn'];
	 		$this -> pfsIpnUrl = $this -> settings['pfsIpnUrl'];
	 		
	 		$this -> pfsMultiSettingsId = $this -> settings['pfsMultiSettingsId'];
	 		$this -> redirect_page_id =  get_site_url() . "/";
	 		
			$this -> msg['message'] = "";
			$this -> msg['class'] = "";
			
			$this -> userlang = ($this -> settings['pfsUserLanguage'] != '') ? $this -> settings['pfsUserLanguage'] : 'he';
			$this -> textleng = $this -> get_user_ui_text();

			add_action('init', array(&$this, 'check_ipn_response'));
			add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
			
			//if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>=' ) ) {
			// 	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			//} else {
			//	add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			//}
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_api_wc_gateway_payplus', array( &$this, 'check_ipn_response' ) );
		}
	
		

	
		/*
		* init form fields
		*/
	
		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable PayPlus+ Payment', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'PayPlus+', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'description' => array(
					'title' => __( 'Description:', 'woocommerce' ),
					'type' => 'textarea',
					'default' => 'Pay securely by Credit Card through PayPlus+'
				),
				'pfsParamsTitle' => array(
					'title' => __( 'PAYPLUS 3D SECURE PARAMETERS', 'woocommerce' ),
					'type' => 'title',
					'description' => '',
				),
				'pfsAuthCode' => array(
					'title' => __( 'Authrization Code', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'PayPlus Authrization Code as received via email.', 'woocommerce' ),
					'default' => __( '2851500dbdf34ad3a21e3eb417ffef28', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'pfsAuthUrl' => array(
					'title' => __( 'Authrization URL', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'PayPlus Authrization URL as received via email.', 'woocommerce' ),
					'default' => __( 'https://ws.payplus.co.il/Sites/_payplus/pfsAuth.aspx', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'pfsPaymentUrl' => array(
					'title' => __( 'Payment URL', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'PayPlus Payment URL as received via email.', 'woocommerce' ),
					'default' => __( 'https://ws.payplus.co.il/Sites/_payplus/payment.aspx', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'pfsIsUsePayPlusIpn' => array(
					'title' => __( 'Use PayPlus IPN', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'After return site, Verify the transaction with PayPlus+ IPN', 'woocommerce' ),
					'default' => 'yes'
				),
				'pfsIpnUrl' => array(
					'title' => __( 'PayPlus IPN URL', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'PayPlus IPN URL as received by email.', 'woocommerce' ),
					'default' => __( 'https://ws.payplus.co.il/Sites/_payplus/ipn.aspx', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'pfsMultiSettingsId' => array(
					'title' => __( 'Multi Settings Id', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'If you NOT get this value from PayPlus leave EMPTY.', 'woocommerce' ),
					'default' => __( '', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'pfsUserInterfaceTitle' => array(
					'title' => __( 'USER INTERFACE', 'woocommerce' ),
					'type' => 'title',
					'description' => '',
				),
				'pfsUserLanguage' => array(
					'title' => __('User Language'),
					'type' => 'select',
					'options' => array('he' => 'Hebrew', 'en' => 'English'),
					'description' => "Select language"
				),
				'pfsICountParams' => array(
					'title' => __( 'ICOUNT PARAMETERS', 'woocommerce' ),
					'type' => 'title',
					'description' => 'For more information <a href="http://www.icount.co.il" target="_blank">www.icount.co.il</a>',
				),
				'pfsIcountLanguage' => array(
					'title' => __( 'Invoce language', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Empty for Hebrew | "en" for English.', 'woocommerce' ),
					'default' => __( '', 'woocommerce' ),
					'desc_tip'      => true,
				)
			);
		}

		public function admin_options(){
			echo '<h3>'.__('PAYPLUS', 'woocommerce').'</h3>';
			echo '<p>'.__('For more information <a href="http://www.payplus.co.il" target="_blank">www.payplus.co.il</a>').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this -> generate_settings_html();
			echo '</table>'; 
		}

		/**
		*  There are no payment fields for payplus, but we want to show the description if set.
		**/
		function payment_fields(){
			if($this -> description) echo wpautop(wptexturize($this -> description));
		}

		/**
		* Receipt Page
		**/
		function receipt_page($order){
			echo '<p>'.__($this -> textleng[$this -> userlang]['please_wait_redirecting'], 'woocommerce').'</p>';
			echo $this -> generate_payplus_form($order);
		}

		/**
		* Generate payu button link
		**/
		public function generate_payplus_form($order_id){
	 
			global $woocommerce;
	 
	  		$order = new WC_Order($order_id);
	       
			$pfs_ref_url = $this -> pfsResponseUrl;
	 
			$productinfo = "Order $order_id";
	 		

		
			/*******************************************
			*	START - PAYPLUS 3D SECURE
			*******************************************/
		
			//	---	PFS PARAMETERS	---
			$pfs_pfsAuthCode = $this -> pfsAuthCode;
			$pfs_pfsAuthUrl = $this -> pfsAuthUrl;
			$pfs_pfsPaymentUrl = $this -> pfsPaymentUrl;
		
			$pfs_pfsMultiSettingsId = $this -> pfsMultiSettingsId;// PayPlus XML Settings Id

			$pfs_pfsIcountLanguage = $this -> pfsIcountLanguage;// payplus -> icount language

			$pfs_amount = $order->order_total * 100; // Amount AGOROT /Cent etc...
			$pfs_uniqNum = $order_id;
			$pfs_md = "";
			$pfs_tt = "";		
		
			$pfs_post_str = "";	// post value for auth as get format
			$pfs_curl_response = ""; // payplus auth string response
		

			// PayPlus 3D Secure - SETP 1 - Authtication
			$pfs_post_variables = Array(
				'a' 		=> 	(string)$pfs_amount, 
				'uniqNum' 	=> 	$pfs_uniqNum, 
				'pfsAuthCode'	=> 	$pfs_pfsAuthCode);
		



			foreach ($pfs_post_variables as $name => $value) {
				if( $pfs_post_str != '') $pfs_post_str .= '&';
				$pfs_post_str .= $name . '=' . $value ;
			}
		
			// curl open connection
			$pfs_ch = curl_init();
			// curl settings
			curl_setopt($pfs_ch, CURLOPT_URL, $pfs_pfsAuthUrl);
			curl_setopt($pfs_ch, CURLOPT_POST, 3);
			curl_setopt($pfs_ch, CURLOPT_POSTFIELDS, $pfs_post_str);
			curl_setopt($pfs_ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($pfs_ch, CURLOPT_SSL_VERIFYPEER, false);
			// curl execute post
			$pfs_curl_response = curl_exec($pfs_ch);
			// curl close connection
			curl_close($pfs_ch);
		
			// check if payplus authitication (STEP 1)
			if(strpos($pfs_curl_response, "~") == false){
				// payplus authrization error
				$response_err_msg = $this -> textleng[$this -> userlang]['payplus_authrization_faild'];
				return $response_err_msg;
			}

			// set payplus md & tt response values
			$pfs_a1 = Array();
			$pfs_a2 = Array();
			$pfs_a3 = Array();

			$pfs_a1 = explode("~", $pfs_curl_response);
			$pfs_a2 = explode("&TT=", $pfs_a1[1]);
			$pfs_a3 = explode("MD=", $pfs_a2[0]);				
		
			$pfs_md = $pfs_a3[1];
			$pfs_tt = $pfs_a2[1];
		
			$pfs_a1 = $pfs_a2 = $pfs_a3 = null;
		

			// PayPlus 3D Secure - SETP 2 - POST user to Payment page
			
			// set iCount Invoce Params
			
			// Cart Contents
			$ic_unit_name = '';
			$ic_unit_quantity = '';
			$ic_unit_price = '';
			$ic_spliter1 = '';
			$ic_spliter2 = '';
			
			$item_loop = 0;
			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( $item['qty'] ) {
						$item_loop++;
						$item_name = $item['name'];
						$pfs_item_total =  ($order->get_item_subtotal( $item, true ) * 100);// (int)$item['qty'];

						$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
						if ( $meta = $item_meta->display( true, true ) ) $item_name .= ' ( ' . $meta . ' )';

						$ic_unit_name .= $ic_spliter1 . str_replace('~', ' ', $item_name);
						$ic_unit_quantity .= $ic_spliter2 . $item['qty'];
						$ic_unit_price .= $ic_spliter2 . $pfs_item_total;
						
						$ic_spliter1 = '~';
						$ic_spliter2 = ',';
					}
				}
				
				// Shipping Cost item 
				if (sizeof( $order->get_total_shipping() ) > 0 ) {
					if((number_format( $order->get_total_shipping(), 2, '.', '' )*100) > 0){
						$ic_unit_name .= $ic_spliter1 . str_replace('~', ' ', ucwords( $order->shipping_method_title ) );
						$ic_unit_quantity .= $ic_spliter2 . '1';
						$ic_unit_price .= $ic_spliter2 . (string)(number_format( $order->get_total_shipping(), 2, '.', '' )*100);
					}
				}
				
				
				// Coupon Items for amount
				
				//if ( sizeof( $order->get_order_discount() ) > 0 ) {
					if((number_format( $order->get_total_discount( false ), 2, '.', '' )*100) > 0){
						$ic_unit_name .= $ic_spliter1 . 'הנחה';
						$ic_unit_quantity .= $ic_spliter2 . '1';
						$ic_unit_price .= $ic_spliter2 . '-' .(string)(number_format( $order->get_total_discount( false ), 2, '.', '' )*100);
					}
				//}
				
				
			}

			$pfs_post_variables = Array(
				// PAYPLUS - Required fields
				'a' 			=> 	(string)$pfs_amount, 
				'uniqNum' 		=> 	$pfs_uniqNum, 
				'id' 			=> 	'', 
				'refURL' 		=> 	$pfs_ref_url, 
				'TT' 			=> 	$pfs_tt,
				'MD' 			=> 	$pfs_md,
				'pfsAuthCode'		=> 	$pfs_pfsAuthCode,
				// PAYPLUS - Extra fields
				'multi_sttings_id'	=>	$pfs_pfsMultiSettingsId,
				'refURL_Cancel'		=>	$order->get_cancel_order_url(),
				// PAYPLUS - iCount module
				'ic_language'		=>	$pfs_pfsIcountLanguage,			
				'ic_client_vatid'	=>	isset($address->identity) ? $address->identity : '' , // identity / biz id
				'ic_client_name'	=>	$order -> billing_first_name.' '.$order-> billing_last_name,
				'ic_client_street'	=>	$order-> billing_address_1,
				'ic_client_city'	=>	$order-> billing_city,
				'ic_client_country'	=>	$order -> billing_country,
				'ic_client_email'	=>	$order -> billing_email,
				'ic_doc_free_text'	=>	$ic_unit_name, // product name list
				'ic_unit_quantity'	=>	$ic_unit_quantity , // quantity per unit list
				'ic_unit_price'		=>	$ic_unit_price // price per unit list
				);
			
			// add spin image	
			$html = '<form action="'. $pfs_pfsPaymentUrl . '" method="post" name="woocommers_payplus_form" id="woocommers_payplus_form" >';
			foreach ($pfs_post_variables as $name => $value) {
				$html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars ($value) . '" />';
			}
			$html .= '<input type="submit" class="button-alt" id="submit_payplus_form" value="'.__($this -> textleng[$this -> userlang]['submit_payment'], 'woocommerce').'" /> ';
			$html .= '<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__($this -> textleng[$this -> userlang]['cancel_button'], 'woocommerce').'</a>';
			$html .= '<script type="text/javascript">';
			
			$html .= 'jQuery("#submit_payplus_form").click();';
			
			/*$html .= '	jQuery(function(){ ';
			
			$html .= '		jQuery("body").block( { ';
			$html .= '			message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__($this -> textleng[$this -> userlang]['please_wait_redirecting'], 'woocommerce').'", ';
			$html .= '			overlayCSS: { ';
			$html .= '				background: "#fff", ';
			$html .= '				opacity: 0.6 ';
			$html .= '			}, ';
			$html .= '			css: { ';
			$html .= '				padding: 20, ';
			$html .= '				textAlign: "center", ';
			$html .= '				color: "#555", ';
			$html .= '				border: "3px solid #aaa", ';
			$html .= '				backgroundColor:"#fff", ';
			$html .= '				cursor: "wait", ';
			$html .= '				lineHeight:"32px" ';
			$html .= '			} ';
			//$html .= '		}); ';
			$html .= '	jQuery("#submit_payplus_form").click();}); })</script>';
			//$html .= '	jQuery("#woocommers_payplus_form").submit();});})</script>';
			//$html .= '	document.woocommers_payplus_form.submit();</script>';
			*/
			$html .= '</script>';
			$html .= '</form>';//</div>';
			
						
			return $html;
		}

		/**
		* Process the payment and return the result
		**/
		function process_payment($order_id){
			global $woocommerce;
			$order = new WC_Order($order_id);
			
			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
			);
		}
		
		/**
		* Check for valid payment using  PayPlus+ IPN
		**/
		function check_ipn_response(){
			
			global $woocommerce;
			
			
			$is_ipn_success = false;
			if(isset($_REQUEST['p96']) && isset($_REQUEST['p120'])){
				$order_id = trim($_REQUEST['p120']);
				$order_id = (int)$order_id;
				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );
						
						$pfs_voucher_id = $_REQUEST['p96'];
						$pfs_uniq_num = $_REQUEST['p120'];
						
						if($order -> status !=='completed'){
							//	---	PAYPLUS IPN	---
							$pfs_pfsIsUsePayPlusIpn = ($this -> pfsIsUsePayPlusIpn == 'yes') ? true : false;
							$pfs_pfsIpnUrl = $this -> pfsIpnUrl;
							$pfs_pfsAuthCode =  $this -> pfsAuthCode;
							
							if($pfs_pfsIsUsePayPlusIpn){
								// if Use PAYPLUS IPN Check
								$pfs_post_variables = Array(
									'voucherId' 	=> 	$pfs_voucher_id, 
									'uniqNum' 	=> 	$pfs_uniq_num, 
									'pfsAuthCode'	=> 	$pfs_pfsAuthCode);
		
								$pfs_post_str = '';
								foreach ($pfs_post_variables as $name => $value) {
									if( $pfs_post_str != '') $pfs_post_str .= '&';
									$pfs_post_str .= $name . '=' . $value ;
								}		
		
								// curl open connection
								$pfs_ch = curl_init();
								// curl settings
								curl_setopt($pfs_ch, CURLOPT_URL, $pfs_pfsIpnUrl);
								curl_setopt($pfs_ch, CURLOPT_POST, 3);
								curl_setopt($pfs_ch, CURLOPT_POSTFIELDS, $pfs_post_str);
								curl_setopt($pfs_ch, CURLOPT_RETURNTRANSFER, true);
								curl_setopt($pfs_ch, CURLOPT_SSL_VERIFYPEER, false);
								// curl execute post
								$pfs_curl_response = curl_exec($pfs_ch);
								// curl close connection
								curl_close($pfs_ch);

								if($pfs_curl_response !=''){
									$_ipn_response = substr($pfs_curl_response, 0 ,1);
				
									if($_ipn_response == 'Y'){
										// PayPlus IPN Response Success
										$this -> msg['message'] = $this -> textleng[$this -> userlang]['ipn_success'];
                                						$this -> msg['class'] = 'woocommerce_message';
										
										$order -> payment_complete();
										$order -> add_order_note('PayPlus+ payment successful<br/>Transaction Voutcher Id: '.$pfs_voucher_id . ' | Customer Identity:' .$_REQUEST['p200'] );
										$order -> add_order_note($this->msg['message']);
										$woocommerce -> cart -> empty_cart();
										
										$is_ipn_success = true;
									}
									else{
										// PayPlus IPN Response Error
										$this -> msg['message'] = $this -> textleng[$this -> userlang]['ipn_error'];
										$this -> msg['class'] = 'error';
										
										$order -> update_status('failed');
										$order -> add_order_note('Failed');
										$order -> add_order_note($this->msg['message']);
									}
								}
								else{ 
									// PayPlus IPN Connection Error
									$this -> msg['message'] = $this -> textleng[$this -> userlang]['ipn_connection_error'];
									$this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
									
									$order -> add_order_note('PayPlus+ payment status is pending<br/>Transaction Voutcher Id: '.$pfs_voucher_id);
									$order -> add_order_note($this->msg['message']);
									$order -> update_status('on-hold');
 									$woocommerce -> cart -> empty_cart();
								}
							}
							else{
								// PayPlus IPN not in use
								$this -> msg['message'] = $this -> textleng[$this -> userlang]['ipn_disable'];
                      						$this -> msg['class'] = 'woocommerce_message';
										
								$order -> payment_complete();
								$order -> add_order_note('PayPlus+ payment successful<br/>Transaction Voutcher Id: '.$pfs_voucher_id. ' | Customer Identity:' .$_REQUEST['p200']);
								$order -> add_order_note($this->msg['message']);
								$woocommerce -> cart -> empty_cart();
								
								$is_ipn_success = true;
							}
						}
						
					}
					catch(Exception $ex){
						$this -> msg['message'] = $this -> textleng[$this -> userlang]['ipn_exception_error'];
									
					}					
					
					if(!$is_ipn_success) 
						wp_die($this -> msg['message'] . '<br/><a href="javascript:document.location.reload(true);" >'.$this -> textleng[$this -> userlang]['try_again_button'].'</a>');
					else	
						wp_redirect($this->get_return_url( $order ));
				}
			}
		}

		
		function showMessage($content){
			return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
		}

		

		
		function get_user_ui_text(){
			return array(
				'en' => array(
					'please_wait_redirecting' 	=> 'Thank you for your order, Please wait while redirecting to PayPlus+.',
					'payplus_authrization_faild' 	=> 'PayPlus+ Authrization faild. <a href="#" onclick="document.location.reload(true);">Try again</a> or connect to website administrator',
					'submit_payment'		=> 'Please wait while redirecting to PayPlus+',
					'cancel_button'			=> 'Cancel order &amp; restore cart',
					'ipn_success'			=> 'Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.',
					'ipn_error'			=> 'Security Error. Illegal access detected',
					'ipn_connection_error'		=> 'Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail',
					'ipn_disable'			=> 'Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.',
					'ipn_exception_error'		=> 'Security internal error.',
					'try_again_button'		=> 'Click here to Try Again'
			
				),
				'he' => array(
					'please_wait_redirecting' 	=> 'תודה על הזמנתך, אנא המתן למעבר לעמוד התשלום המאובטח של פיי פלוס+',
					'payplus_authrization_faild' 	=> 'האימות מול פיי פלוס+ נכשל. <a href="#" onclick="document.location.reload(true);">נסה שנית</a> או צור קשר עם מנהל האתר',
					'submit_payment'		=> 'למעבר לעמוד התשלום לחצו כאן',
					'cancel_button'			=> 'בטול הזמנה ואיפוס קופה',
					'ipn_success'			=> 'התשלום בוצע בהצלחה, תודה על הזמנתך',
					'ipn_error'			=> 'התקבלה שגיאה באימות התשלום',
					'ipn_connection_error'		=> 'התקבלה שגיאת תקשורת לאימות התשלום',
					'ipn_disable'			=> 'הזמנתך בוצעה בהצלחה',
					'ipn_exception_error'		=> 'התקבלה שגיאת מערכת באימות התשלום',
					'try_again_button'		=> 'לחצו כאן לנסות שנית'
				)
			);
		}

	}// End PayPlus Class

	/*
	* Add the Gateway to WooCommerce
	*/
	function add_pp_payplus_class($methods) {
		$methods[] = 'WC_Gateway_PayPlus';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_pp_payplus_class' );
	
}



