<?php 
class WC_Millikart_Payment extends WC_Payment_Gateway{
    
	public function __construct(){
		$this->id = 'millikart_payment';
		$this->method_title = 'MilliKart';
		$this->title = 'MilliKart';
		$this->has_fields = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title');
        $this->mid = $this->get_option('mid');
		$this->pskey = $this->get_option('pskey');
		$this->currency = $this->get_option('currency');
        $this->callback_url = $this->get_option('callback_url');
		$this->paymenturl = $this->get_option('paymenturl');
		$this->payment_status = $this->get_option('payment_status');
		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
	}
    
	public function init_form_fields(){
        $this->form_fields = array(
            'enabled' => array(
                'title' 		=> __('Enable/Disable', 'woocommerce-millikart-payment'),
                'type' 			=> 'checkbox',
                'label' 		=> __('Enable MilliKart Payment', 'woocommerce-millikart-payment'),
                'default' 		=> 'no',
            ),
            'title' => array(
                'title' 		=> __('Payment Method Title', 'woocommerce-millikart-payment'),
                'type' 			=> 'text',
                'description' 	=> __('It will be shown on checkout page', 'woocommerce-millikart-payment'),
                'default'		=> 'Visa/MasterCard',
                'desc_tip'		=> true,
            ),
            'mid' => array(
                'title' 		=> 'Merchant ID',
                'type' 			=> 'text',
                'description' 	=> __('MID value is given by MilliKart', 'woocommerce-millikart-payment'),
                'placeholder'	=> __('Provided by MilliKart', 'woocommerce-millikart-payment'),
                'desc_tip'		=> true,
            ),
            'pskey' => array(
                'title' 		=> 'Key',
                'type' 			=> 'text',
                'description' 	=> __('Key value is given by MilliKart', 'woocommerce-millikart-payment'),
                'placeholder'	=> __('Provided by MilliKart', 'woocommerce-millikart-payment'),
                'desc_tip'		=> true,
            ),
            'currency' => array(
                'title' 		=> __('Currency', 'woocommerce-millikart-payment'),
                'type' 			=> 'text',
                'description' 	=> __('Currency code of payment by ISO 4217 (ex.: AZN = 944)', 'woocommerce-millikart-payment'),
                'placeholder'	=> __('ex.: 944', 'woocommerce-millikart-payment'),
                'desc_tip'		=> true,
            ),
            'callback_url' => array(
                'title' 		=> __('Callback URL', 'woocommerce-millikart-payment'),
                'type' 			=> 'text',
                'description' 	=> __('The callback URL that was set on registration in MilliKart.', 'woocommerce-millikart-payment'),
                'placeholder'	=> __('ex.: http(s)://your.domain/millikart_callback.php', 'woocommerce-millikart-payment'),
                'desc_tip'		=> true,
            ),
            'paymenturl' => array(
                'title' 		=> __('Payment URL', 'woocommerce-millikart-payment'),
                'type' 			=> 'text',
                'description' 	=> __('URL to MilliKart payment page (provided by MilliKart)', 'woocommerce-millikart-payment'),
                'placeholder'	=> __('ex.: http://.../gateway/payment/register', 'woocommerce-millikart-payment'),
                'desc_tip'		=> true,
            ),
            'payment_status' => array(
                'title' 		=> __('Payment Status', 'woocommerce-millikart-payment'),
                'type' 			=> 'text',
                'description' 	=> __('Payment Status checking URL (provided by MilliKart)', 'woocommerce-millikart-payment'),
                'placeholder'	=> __('ex.: http://.../gateway/payment/status', 'woocommerce-millikart-payment'),
                'desc_tip'		=> true,
            )
        );
	}
    
	public function process_payment($order_id){
		global $woocommerce, $wpdb;
		$order = new WC_Order($order_id);
        $millikart_payment = $wpdb->prefix . "woocommerce_millikart";
        //randomKey function for generating reference
        function randomKey($length = 41){
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for($i = 0; $i < $length; $i++){
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            $randomString = md5(strtoupper($randomString));
            return strtoupper($randomString);
        }
        //Get required variables
        $mid = $this->mid;
        $currency = $this->currency;
        $description = 'order'.$order->id;
        $key = $this->pskey;
        $millikartURL = $this->paymenturl;
        $reference = randomKey();
        $amount = $order->get_total() * 100;
        $language = get_locale();
        if($language == 'ru_RU'){
            $language = 'ru';
        }elseif($language == 'az'){
            $language = 'az';
        }else{
            $language = 'en';
        }
        $signature = strtoupper(md5(strlen($mid).$mid.strlen($amount).$amount.strlen($currency).$currency.(!empty($description)?strlen($description).$description:"0").strlen($reference).$reference.strlen($language).$language.$key));
        $paymentURL = $millikartURL.'?mid='.$mid.'&amount='.$amount.'&currency='.$currency.'&description='.$description.'&reference='.$reference.'&language='.$language.'&signature='.$signature.'&redirect=1';
		//Order marks as pending
		$order->update_status('pending', __('<b>Pending payment!</b>', 'woocommerce-millikart-payment'));
        //Adding order reference into db
        $reference_db_add = $wpdb->insert($millikart_payment, array('order_id' => $order->id, 'reference' => $reference, 'language' => $language));
        // Remove cart
		$woocommerce->cart->empty_cart();
		// Redirect to Millikart payment page
		return array(
			'result'     => 'success',
			'redirect'   => $paymentURL
		);
	}
}
