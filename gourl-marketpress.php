<?php
/*
Plugin Name: 		GoUrl MarketPress - Bitcoin Altcoin Payment Gateway Addon
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Bitcoin/Altcoins Payment Gateway for <a href="https://wordpress.org/plugins/wordpress-ecommerce/">MarketPress 2.9+</a>. Convert your USD/EUR/etc prices to cryptocoins using Google/Bitstamp/Cryptsy Live Exchange Rates; sends the amount straight to your business Bitcoin/Altcoin wallet. Accept Bitcoin, Litecoin, Speedcoin, Dogecoin, Paycoin, Darkcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Vericoin payments online. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.0.0
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly


function gourl_mp_gateway_load()
{

	
	// Marketpress required
	$dir = "";
	$arr = get_option('active_plugins');
	foreach ($arr as $v) if (substr($v, -16) == "/marketpress.php") $dir = substr($v, 0, -16);
	if (!$dir || !file_exists(WP_PLUGIN_DIR.'/'.$dir.'/marketpress-includes/marketpress-gateways.php')) return;
	if (!class_exists('MP_Gateway_API')) require_once(WP_PLUGIN_DIR.'/'.$dir.'/marketpress-includes/marketpress-gateways.php');
	
	
	DEFINE( 'GOURLMP', 			'mp-gourl');
	DEFINE( 'GOURLMP_ADMIN', 	__('GoUrl Bitcoin/Altcoins', GOURLMP));
	
	add_filter( 'plugin_action_links', 'gourl_mp_action_links', 10, 2 );
	

	/*
	 *	1.
	*/
	function gourl_mp_action_links($links, $file)
	{
		static $this_plugin;
	
		if (false === isset($this_plugin) || true === empty($this_plugin)) {
			$this_plugin = plugin_basename(__FILE__);
		}
	
		if ($file == $this_plugin) {
			$settings_link = '<a href="'.admin_url('edit.php?post_type=product&page=marketpress&tab=gateways').'">'.__( 'Settings', GOURLMP ).'</a>';
			array_unshift($links, $settings_link);
				
			if (defined('GOURL'))
			{
				$unrecognised_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=unrecognised').'">'.__( 'Unrecognised', GOURLMP ).'</a>';
				array_unshift($links, $unrecognised_link);
				$payments_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=gourlmarketpress').'">'.__( 'Payments', GOURLMP ).'</a>';
				array_unshift($links, $payments_link);
			}
		}
	
		return $links;
	}
	
	
	/*
	 *	2.
	*/
	class MP_Gateway_GoUrl extends MP_Gateway_API 
	{
	
		private $payments 			= array();
		private $languages 			= array();
		private $coin_names			= array();
		private $statuses 			= array('paid' => 'Paid', 'shipped' => 'Shipped', 'closed' => 'Closed');
		private $mainplugin_url		= '';
		private $url				= '';
		private $url2				= '';
		private $url3				= '';
		private $cointxt			= '';
		private $method_description	= '';
		
		private $title        		= '';
		private $emultiplier  		= '';
		private $ostatus  			= '';
		private $ostatus2  			= '';
		private $deflang  			= '';
		private $defcoin  			= '';
		private $iconwidth  		= '';	
		
		public $plugin_name 		= 'gourlmarketpress';
		public $admin_name 			= '';
		public $public_name 		= '';
		public $method_img_url 		= '';
		public $method_button_img_url = '';
		public $force_ssl 			= false;
		public $ipn_url				= '';
		public $skip_form 			= true;
	
	
		/*
		 *	2.1
		*/
		public function __construct()
		{
			global $gourl, $mp;
			
			$this->admin_name        		= GOURLMP_ADMIN;
			$this->mainplugin_url 			= admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
				
			$this->method_description  		= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:25px' src='https://gourl.io/images/gourlpayments.png'></a>";
			$this->method_description  	   .= sprintf(__( '<a target="_blank" href="%s">Plugin Homepage</a> &#160;&amp;&#160; <a target="_blank" href="%s">screenshots &#187;</a>', GOURLMP ), "https://gourl.io/bitcoin-payments-wpmudev-marketpress.html", "https://gourl.io/bitcoin-payments-wpmudev-marketpress.html#screenshot") . "<br>";
			$this->method_description  	   .= sprintf(__( '<a target="_blank" href="%s">Plugin on Github - 100%% Free Open Source &#187;</a>', GOURLMP ), "https://github.com/cryptoapi/Bitcoin-Payments-MarketPress") . "<br><br>";
			
			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{
				if (true === version_compare(GOURL_VERSION, '1.2.9', '<'))
				{
					$this->method_description .= '<div class="error"><p>' .sprintf(__( '<b>Your GoUrl Bitcoin Gateway <a href="%s">Main Plugin</a> version is too old. Requires 1.2.9 or higher version. Please <a href="%s">update</a> to latest version.</b>  &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Main Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a>', GOURLMP ), GOURL_ADMIN.GOURL, $this->mainplugin_url).'</p></div>';
				}
				elseif (true === version_compare($mp->version, '2.9.5', '<'))
				{
					$this->method_description .= '<div class="error"><p>' .sprintf(__( '<b>Your MarketPress eCommerce version %s is too old</b>. Requires 2.9.5 or higher version for GoUrl Bitcoin/Altcoins Payment Gateway', GOURLMP ), $mp->version).'</p></div>';
				}			
				else
				{
					$this->payments 			= $gourl->payments(); 		// Activated Payments (coins)
					$this->coin_names			= $gourl->coin_names(); 	// All Coins
					$this->languages			= $gourl->languages(); 		// All Languages
				}
				
				$this->url		= GOURL_ADMIN.GOURL."settings";
				$this->url2		= GOURL_ADMIN.GOURL."payments&s=".$this->plugin_name;
				$this->url3		= GOURL_ADMIN.GOURL;
				$this->cointxt 	= (implode(", ", $this->payments)) ? implode(", ", $this->payments) : __( '- Please setup -', GOURLMP );
			}
			else
			{
				$this->method_description  = '<div style="margin-bottom:20px;padding:5px;border:1px dashed red;background:#ffe6e6;color:#444"><p>' .sprintf(__( '<b>You need to install GoUrl Bitcoin Gateway Main Plugin also. &#160; Go to - <a href="%s">Automatic installation</a> or <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Manual</a></b>. &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Main Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a> ', GOURLMP ), $this->mainplugin_url).'</p></div>' . $this->method_description;
				
				
				$this->url		= $this->mainplugin_url;
				$this->url2		= $this->url;
				$this->url3		= $this->url;
				$this->cointxt 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin &#187;', GOURLMP ).'</b>';
			}
				
			$this->method_description .= "<br/><b>" . __( 'Secure payments with virtual currency in Marketpress. &#160; <a target="_blank" href="https://bitcoin.org/">What is Bitcoin?</a>', GOURLMP ) . '</b><br/>';
			$this->method_description .= sprintf(__( 'Accept %s payments online in Marketpress.', GOURLMP), ($this->coin_names?ucwords(implode(", ", $this->coin_names)):"Bitcoin, Litecoin, Speedcoin, Dogecoin, Paycoin, Darkcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Vericoin")).'<br/>';
			$this->method_description .= __( 'If you use multiple stores/sites online, please create separate <a target="_blank" href="https://gourl.io/editrecord/coin_boxes/0">GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.', GOURLMP );
			$this->method_description .= '<br/><br/>';
			
			$this->gourl_settings();
			$this->public_name 		= $this->title;
			$this->method_img_url 	= 'https://gourl.io/images/'.$this->logo.'/payments.png';
				
	
			add_filter('mp_order_status_section_title_payment_info', array($this, 'cryptocoin_payment'), 20, 2);
			
			parent::__construct();
			
			return true;
			
		}
		
		
		
		/*
		 *	2.2
		*/
		private function gourl_settings()
		{
			global $mp;
	
			// Define user set variables
			$this->title        = $mp->get_setting('gateways->'.$this->plugin_name.'->title');
			$this->logo         = $mp->get_setting('gateways->'.$this->plugin_name.'->logo');
			$this->emultiplier  = trim(str_replace("%", "", $mp->get_setting('gateways->'.$this->plugin_name.'->emultiplier')));
			$this->ostatus  	= $mp->get_setting('gateways->'.$this->plugin_name.'->ostatus');
			$this->ostatus2  	= $mp->get_setting('gateways->'.$this->plugin_name.'->ostatus2');
			$this->deflang  	= $mp->get_setting('gateways->'.$this->plugin_name.'->deflang');
			$this->defcoin  	= $mp->get_setting('gateways->'.$this->plugin_name.'->defcoin');
			$this->iconwidth  	= trim(str_replace("px", "", $mp->get_setting('gateways->'.$this->plugin_name.'->iconwidth')));
				
			// Re-check
			if (!$this->title)								$this->title 		= __( 'GoUrl Bitcoin/Altcoins', GOURLMP );
			if (!in_array($this->logo, $this->coin_names) && $this->logo != 'global') $this->logo = 'bitcoin';
			if (!isset($this->statuses[$this->ostatus])) 	$this->ostatus  	= 'paid';
			if (!isset($this->statuses[$this->ostatus2])) 	$this->ostatus2 	= 'paid';
			if (!isset($this->languages[$this->deflang])) 	$this->deflang 		= 'en';
				
			if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01) 	$this->emultiplier = 1;
			if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250) 		$this->iconwidth = 60;
			
			if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin])) $this->defcoin = key($this->payments);
			elseif (!$this->payments)						$this->defcoin		= '';
			elseif (!$this->defcoin)						$this->defcoin		= key($this->payments);
			
			return true;
		}
		
		
		
		/*
		 *	2.3
		*/
		public function gateway_settings_box($settings)
		{
			global $mp, $gourl;
		
			$this->gourl_settings();
			
			$logos = array('global' => __( 'GoUrl default logo - "Global Payments"', GOURLMP ));
			foreach ($this->coin_names as $v) $logos[$v] = __( 'GoUrl logo with text - "'.ucfirst($v).' Payments"', GOURLMP );
				
			
			$tmp  = '<div id="mp_'.$this->plugin_name.'_payments" class="postbox mp-pages-msgs">';
			$tmp .= '<h3 class="handle"><span>'.__( 'GoUrl Bitcoin/Altcoins Settings', GOURLMP ).'</span></h3>';
			$tmp .= '<div class="inside">';
			$tmp .= '<table class="form-table">';
			$tmp .= '<tr valign="top"><td colspan=2>';
			$tmp .= '<div style="font-size:13px; color:#888">'.$this->method_description.'</div>';
			$tmp .= '</th></tr>';
		
			// a
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'title">'.__( 'Title', GOURLMP ).'</label></th>
	            	<td><input type="text" value="'.$this->title.'" name="mp[gateways]['.$this->plugin_name.'][title]" id="mp[gateways]['.$this->plugin_name.'][title]">';
			$tmp .= '<p class="description">'.__('Payment method title that the customer will see on your checkout', GOURLMP )."</p>";
			$tmp .= "</tr>";
			
			// b
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'logo">'.__( 'Logo', GOURLMP ).'</label></th>
	            	<td><select name="mp[gateways]['.$this->plugin_name.'][logo]" id="mp[gateways]['.$this->plugin_name.'][logo]">';
			foreach ($logos as $k => $v) $tmp .= "<option value='".$k."'".$this->sel($k, $this->logo).">".$v."</option>";
			$tmp .= "</select>";
			$tmp .= '<p class="description">'.__("Payment method logo that the customer will see on your checkout", GOURLMP)."</p>";
			$tmp .= "</tr>";
				
			// c
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'defcoin">'.__( 'PaymentBox Default Coin', GOURLMP ).'</label></th>
	            	<td><select name="mp[gateways]['.$this->plugin_name.'][defcoin]" id="mp[gateways]['.$this->plugin_name.'][defcoin]">';
			foreach ($this->payments as $k => $v) $tmp .= "<option value='".$k."'".$this->sel($k, $this->defcoin).">".$v."</option>";
			$tmp .= "</select>";
			$tmp .= '<p class="description">'.sprintf(__( 'Default Coin in Crypto Payment Box. &#160; Activated Payments : <a href="%s">%s</a>', GOURLMP), $this->url, $this->cointxt)."</p>";
			$tmp .= "</tr>";
		
			// d
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'deflang">'.__( 'PaymentBox Language', GOURLMP ).'</label></th>
	            	<td><select name="mp[gateways]['.$this->plugin_name.'][deflang]" id="mp[gateways]['.$this->plugin_name.'][deflang]">';
			foreach ($this->languages as $k => $v) $tmp .= "<option value='".$k."'".$this->sel($k, $this->deflang).">".$v."</option>";
			$tmp .= "</select>";
			$tmp .= '<p class="description">'.__("Default Crypto Payment Box Localisation", GOURLMP)."</p>";
			$tmp .= "</tr>";
		
			// e
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'emultiplier">'.__( 'Exchange Rate Multiplier', GOURLMP ).'</label></th>
	            	<td><input type="text" value="'.$this->emultiplier.'" name="mp[gateways]['.$this->plugin_name.'][emultiplier]" id="mp[gateways]['.$this->plugin_name.'][emultiplier]">';
			$tmp .= '<p class="description">'.sprintf(__('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to %s. <br />Example: <b>1.05</b> - will add an extra 5%% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15%% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLMP ), implode(", ", $this->payments))."</p>";
			$tmp .= "</tr>";
		
			// f
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'ostatus">'.__( 'Order Status - Cryptocoin Payment Received', GOURLMP ).'</label></th>
	            	<td><select name="mp[gateways]['.$this->plugin_name.'][ostatus]" id="mp[gateways]['.$this->plugin_name.'][ostatus]">';
			foreach ($this->statuses as $k => $v) $tmp .= "<option value='".$k."'".$this->sel($k, $this->ostatus).">".$v."</option>";
			$tmp .= "</select>";
			$tmp .= '<p class="description">'.sprintf(__("Payment is received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br/>If you sell digital products / software downloads you can use the status 'Completed' showing that particular customer already has instant access to your digital products", GOURLMP), $this->url2)."</p>";
			$tmp .= "</tr>";
				
			// g
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'ostatus2">'.__( 'Order Status - Previously Received Payment Confirmed', GOURLMP ).'</label></th>
	            	<td><select name="mp[gateways]['.$this->plugin_name.'][ostatus2]" id="mp[gateways]['.$this->plugin_name.'][ostatus2]">';
			foreach ($this->statuses as $k => $v) $tmp .= "<option value='".$k."'".$this->sel($k, $this->ostatus2).">".$v."</option>";
			$tmp .= "</select>";
			$tmp .= '<p class="description">'.__("About one hour after the payment is received, the bitcoin transaction should get 6 confirmations (for transactions using other cryptocoins ~ 20-30min).<br>A transaction confirmation is needed to prevent double spending of the same money.", GOURLMP)."</p>";
			$tmp .= "</tr>";
			
			// h
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'iconwidth">'.__( 'Icon Width', GOURLMP ).'</label></th>
	            	<td><input type="text" value="'.$this->iconwidth.'px" name="mp[gateways]['.$this->plugin_name.'][iconwidth]" id="mp[gateways]['.$this->plugin_name.'][iconwidth]">';
			$tmp .= '<p class="description">'.__( 'Cryptocoin icons width in "Select Payment Method". Default 60px. Allowed: 30..250px', GOURLMP )."</p>";
			$tmp .= "</tr>";
		
			// i
			$tmp .= '<tr valign="top">
	            	<th><label for="'.GOURLMP.'boxstyle">'.__( 'PaymentBox Style', GOURLMP ).'</label></th>
	            	<td>'.sprintf(__( 'Payment Box <a target="_blank" href="%s">sizes</a> and border <a target="_blank" href="%s">shadow</a> you can change <a href="%s">here &#187;</a>', GOURLMP ), "https://gourl.io/images/global/sizes.png", "https://gourl.io/images/global/styles.png", $this->url."#gourlvericoinprivate_key")."</td>";
			$tmp .= "</tr>";
				
				
			$tmp .= "</table></div></div>";
		
			echo $tmp;
	
			
			return true;
		}
		
		
	
		/*
		 *	2.4
		*/
		public function process_payment($cart, $shipping_info) 
		{
			global $mp;
			$timestamp = time();
			
			$totals = array();
			$coupon_code = $mp->get_coupon_code();
	
			foreach ($cart as $product_id => $variations) 
			{
				foreach ($variations as $data) {
					$price = $mp->coupon_value_product($coupon_code, $data['price'] * $data['quantity'], $product_id);
					$totals[] = $price;
				}
			}
			$total = array_sum($totals);
	
			$shipping_tax = 0;
			if ( ($shipping_price = $mp->shipping_price(false)) !== false ) 
			{
				$total += $shipping_price;
				$shipping_tax = ($mp->shipping_tax_price($shipping_price) - $shipping_price);
			}
	
			if ( ! $mp->get_setting('tax->tax_inclusive') ) 
			{
				$tax_price = ($mp->tax_price(false) + $shipping_tax);
				$total += $tax_price;
			}
	
			$order_id = $mp->generate_order_id();
	
			$payment_info['gateway_public_name'] 	= $this->public_name;
			$payment_info['gateway_private_name'] 	= $this->admin_name;
			$payment_info['total'] 					= $total;
			$payment_info['transaction_id'] 		= $order_id;
			$payment_info['currency'] 				= $mp->get_setting('currency');
			$payment_info['method'] 				= __('Virtual currency', GOURLMP);
			 
			$result = $mp->create_order($order_id, $cart, $shipping_info, $payment_info, false);
			
			return true;
		}
		
		
		/*
		 *	2.5
		*/
		public function order_confirmation_email($msg, $order) { return $msg; }
			
		public function order_confirmation_msg($content, $order) { return $content; }
	
		public function process_payment_form($cart, $shipping_info) { }
		
		public function confirm_payment_form($cart, $shipping_info) { }

		
		
		/*
		 *	2.6
		*/
		public function order_confirmation($order) 
		{
			// New Order
			$user = (!$order->post_author) ? __('Guest', GOURLMP) : "<a href='".admin_url("user-edit.php?user_id=".$order->post_author)."'>user".$order->post_author."</a>";
			$this->add_order_note($order, sprintf(__('Order Created by %s<br>Awaiting Cryptocurrency Payment ...<br>', GOURLMP), $user));
				
			wp_redirect(mp_orderstatus_link(false, true).$order->post_name.'/');
			
			return true;
		}
		
		
		
		/*
		 *	2.7
		*/
		public function cryptocoin_payment( $subject, $order )
		{
			global $gourl, $mp;

			if (!isset($order->mp_payment_info["gateway_private_name"]) || $order->mp_payment_info["gateway_private_name"] != GOURLMP_ADMIN) return $subject;
			
			if ($order === false) throw new Exception('The GoUrl payment plugin was called to process a payment but could not retrieve the order details. Cannot continue!');
				
			$tmp 	= "";
			$paid 	= false;
			
			if ($order->post_status == "trash" || $order->post_status == "delete")
			{
				$tmp .= "<div class='mp_checkout_error'>". __( 'This order&rsquo;s status is &ldquo;Cancelled&rdquo; &mdash; it cannot be paid for. Please contact us if you need assistance.', GOURLMP )."</div>";
			}
			elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
			{
				$tmp .= "<div class='mp_checkout_error'>".__( "Please try a different payment method. Admin need to install and activate wordpress plugin 'GoUrl Bitcoin Gateway' (https://gourl.io/bitcoin-wordpress-plugin.html) to accept Bitcoin/Altcoin Payments online", GOURLMP )."</div>";
			}
			elseif (!$this->payments || !$this->defcoin || true === version_compare($mp->version, '2.9.5', '<') || true === version_compare(GOURL_VERSION, '1.2.9', '<') ||
					(array_key_exists($order->order_currency, $this->coin_names) && !array_key_exists($order->order_currency, $this->payments)))
			{
				$tmp .= "<div class='mp_checkout_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance. (GoUrl Bitcoin Plugin not configured - %s not activated)', GOURLMP ),(!$this->payments || !$this->defcoin?$this->title:$this->coin_names[$order->order_currency]))."</div>";
			}
			else
			{
				$plugin			= "gourlmarketpress";
				$amount 		= $order->mp_payment_info["total"];
				$currency 		= $order->mp_payment_info["currency"];
				$orderID		= $order->post_name;
				$userID			= $order->post_author;
				$period			= "NOEXPIRY";
				$language		= $this->deflang;
				$coin 			= $this->coin_names[$this->defcoin];
				$affiliate_key 	= "gourl";
				$crypto			= array_key_exists($currency, $this->coin_names);
					
				if (!$userID) $userID = "guest"; // allow guests to make checkout (payments)
					
	
				if (!$userID)
				{
					$tmp .= "<div align='center'><a href='".wp_login_url(get_permalink())."'>
							<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Payments', GOURLMP )."' vspace='10'
							src='".$gourl->box_image()."' border='0'></a></div>";
				}
				elseif ($amount <= 0)
				{
					$tmp .= "<div class='mp_checkout_error'>". sprintf(__( 'This order&rsquo;s amount is &ldquo;%s&rdquo; &mdash; it cannot be paid for. Please contact us if you need assistance.', GOURLMP ), floatval($amount) ." " . $currency)."</div>";
				}
				else
				{
		
					// Exchange (optional)
					// --------------------
					if ($currency != "USD" && !$crypto)
					{
						$amount = gourl_convert_currency($currency, "USD", $amount);
		
						if ($amount <= 0)
						{
							$tmp .= "<div class='mp_checkout_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD from Google Finance', GOURLMP ), $currency)."</div>";
						}
						else $currency = "USD";
					}
	
					if (!$crypto) $amount = $amount * $this->emultiplier;
						
		
						
					// Payment Box
					// ------------------
					if ($amount > 0)
					{
						// crypto payment gateway
						$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $this->iconwidth);
							
						if ($result["error"]) $tmp .= "<div class='mp_checkout_error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLMP )."<br/>".$result["error"]."</div>";
						else
						{
							// display payment box or successful payment result
							$tmp .= $result["html_payment_box"];
		
							// payment received
							if ($result["is_paid"])
							{
								$paid = true;
								$tmp .= "<div align='center'>" . sprintf( __('%s Payment ID: #%s', GOURLMP), ucfirst($result["coinname"]), $result["paymentID"]) . "</div><br>";
							}
							// unpaid order expired after 12 hours
							elseif (strtotime($order->post_date_gmt) < (strtotime(gmdate("Y-m-d H:i:s")) - 12*60*60)) 
							{
								$mp->update_order_status($order->ID, "trash");
								$tmp = "<div class='mp_checkout_error'>". __( 'This order&rsquo;s status is &ldquo;Cancelled&rdquo; &mdash; it cannot be paid for. Please contact us if you need assistance.', GOURLMP )."</div>";
							}
						}
					}
				}
			}
		
			$tmp  = '<br>' . ($paid?__($subject, GOURLMP):__('Pay Now', GOURLMP)) . '</h3>' . $tmp . '<br><h3>';
		
			return $tmp;
		}
		
		
		
		
		/*
		 * 2.8 GoUrl Bitcoin Gateway - Instant Payment Notification
		*/
		public function gourlcallback( $user_id, $order_id, $payment_details, $box_status)
		{
			global $mp;
			
			if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
		
			if (!$user_id || $payment_details["status"] != "payment_received") return false;
		
			$order = $mp->get_order($order_id);  if ($order === false) return false;
		
		
			$coinName 	= ucfirst($payment_details["coinname"]);
			$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
			$payID		= $payment_details["paymentID"];
			$status		= ($payment_details["is_confirmed"]) ? $this->ostatus2 : $this->ostatus;
			$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLMP) : __('No', GOURLMP);
		
		
			// New Payment Received
			if ($box_status == "cryptobox_newrecord")
			{
				$this->add_order_note($order, sprintf(__('%s Payment Received<br>%s<br>Payment id <a href="%s">%s</a> &#160; (<a href="%s">page</a>)<br>Awaiting network confirmation...<br>', GOURLMP), $coinName, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, mp_orderstatus_link(false, true).$order->post_name.'/'."?gourlcryptocoin=".$payment_details["coinname"]));
				 
				update_post_meta( $order->ID, 'coinname', 		$coinName);
				update_post_meta( $order->ID, 'amount', 		$payment_details["amount"] . " " . $payment_details["coinlabel"] );
				update_post_meta( $order->ID, 'userid', 		$payment_details["userID"] );
				update_post_meta( $order->ID, 'country', 		get_country_name($payment_details["usercountry"]) );
				update_post_meta( $order->ID, 'tx', 			$payment_details["tx"] );
				update_post_meta( $order->ID, 'confirmed', 		$confirmed );
				update_post_meta( $order->ID, 'details', 		$payment_details["paymentLink"] );
			}
		
		
			// Update Status
			$mp->update_order_status($order->ID, $status);
		
		
			// Existing Payment confirmed (6+ confirmations)
			if ($payment_details["is_confirmed"])
			{
				update_post_meta( $order->ID, 'confirmed', $confirmed );
				$this->add_order_note($order, sprintf(__('%s Payment id <a href="%s">%s</a> Confirmed<br>', GOURLMP), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
			}
		
			return true;
		}



		/*
		 *	2.9
		*/
		private function add_order_note($order, $note)
		{
			//get old status
			$payment_info = $order->mp_payment_info;
			$timestamp = time();
			$payment_info['status'][$timestamp] = '<br>'.$note;
			//update post meta
			update_post_meta($order->ID, 'mp_payment_info', $payment_info);
			
			return true;
		}
		
		

		/*
		 *	2.10
		*/
		private function sel($val1, $val2)
		{
			$tmp = ((is_array($val1) && in_array($val2, $val1)) || strval($val1) == strval($val2)) ? ' selected="selected"' : '';
		
			return $tmp;
		}
		
		 
	} // end class MP_Gateway_GoUrl
	
	
	
	/*
	 *  3. Instant Payment Notification Function - pluginname."_gourlcallback"
	*
	*  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully.
	*  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway),
	*  payment details as array and box status.
	*
	*  The function will automatically appear for each new payment usually two times :
	*  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
	*  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
	*
	*  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
	*  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
	*
	*  Payment_details example - https://gourl.io/images/plugin2.png
	*  Read more - https://gourl.io/affiliates.html#wordpress
	*/
	function gourlmarketpress_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
	{
		global $mp_gateway_active_plugins;
			
		$gateway = "";
		foreach ($mp_gateway_active_plugins as $row)
			if ($row->plugin_name == "gourlmarketpress") $gateway = $row;  
			
		if (!$gateway) return;
	
		if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
	
		// forward data to MP_Gateway_GoUrl
		$gateway->gourlcallback( $user_id, $order_id, $payment_details, $box_status);
	
		return true;
	}
	

	/*
	 *  4. Register Gateway
	 */  
	mp_register_gateway_plugin( 'MP_Gateway_GoUrl', 'gourlmarketpress', GOURLMP_ADMIN );

}


add_action( 'plugins_loaded', 'gourl_mp_gateway_load', 10 );

