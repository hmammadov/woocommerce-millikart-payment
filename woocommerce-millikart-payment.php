<?php
/*
Plugin Name: WooCommerce MilliKart Payment
Plugin URI:  https://github.com/hmammadov/woocommerce-millikart-payment
Description: MilliKart Payment Gateway for WooCommerce.
Version:     1.0.0
Author:      Huseyn Mammadov
Author URI:  https://hmammadov.ru
License:     GPL v3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: woocommerce-millikart-payment
Domain Path: /languages
*/

//Exit if accessed directly.
if(!defined('ABSPATH')) exit;

$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if(in_array('woocommerce/woocommerce.php', $active_plugins)){
    add_filter('woocommerce_payment_gateways', 'add_millikart_payment');
    function add_millikart_payment($gateways){
        $gateways[] = 'WC_Millikart_Payment';
        return $gateways; 
    }
    add_action('plugins_loaded', 'init_millikart_payment');
    function init_millikart_payment(){
        require 'class-woocommerce-millikart-payment.php';
    }
}

//Payment status checking
add_action('wp', function(){
    $millikart_options = new WC_Millikart_Payment();
    $callback_url = $millikart_options->callback_url;
    $callback_url = substr($callback_url, strrpos($callback_url, '/') + 1);
    if(isset($_SERVER['REQUEST_URI']) && isset($_GET['reference']) && strpos($_SERVER['REQUEST_URI'], $callback_url) !== false){
        global $wpdb;
        //Get order_id if reference isset
        $order_id = $wpdb->get_results($wpdb->prepare( "SELECT order_id, language FROM " . $wpdb->prefix . "woocommerce_millikart WHERE reference = '%s' LIMIT 1", $_GET['reference']));
        //If reference isset
        if($order_id == true){
	        $array = json_decode(json_encode($order_id), True);
            $language = $array[0]['language'];
            $order_id = $array[0]['order_id'];
            //Get data from plugin settings
            $mid = $millikart_options->mid;
            $payment_status = $millikart_options->payment_status;
            //Get payment status from Millikart
            $url = $payment_status.'?mid='.$mid.'&reference='.$_GET['reference'];
            $xml = file_get_contents($url);
            $xml = simplexml_load_string($xml);
            $RCcode = $xml->RC;
            //Change payment status
            $order = new WC_Order($order_id);
            if($RCcode == '000'){
                $order->update_status('processing', __('<b>Successful payment!</b>', 'woocommerce-millikart-payment'));
            }else{
                $order->update_status('failed', __('<b>Payment failed!</b> Get more information from <a target="_blank" href="https://mwp.millikart.az/login.php">MilliKart Account</a> or contact to your operator in MilliKart', 'woocommerce-millikart-payment'));
            }
            //Get checkout ID
            $default_checkout_id = wc_get_page_id('checkout');
            //Check if WPML or Polylang installed
            if (function_exists('icl_object_id')){
                //If checkout in other lang, get it ID
                $lang_post_id = icl_object_id( $default_checkout_id , 'page', true, $language );
            }else{
                $lang_post_id = $default_checkout_id;
            }
            //Get order received URL
            $order_received_url = wc_get_endpoint_url('order-received', $order->id, get_permalink($lang_post_id));
            $order_received_url = add_query_arg('key', $order->order_key, $order_received_url);
            header("Location: ".$order_received_url);
        }else{
            header("Location: ".get_option('home'));
        }
    }
});

//Creating file and tabel for Millikart
global $millikart_db_version;
$millikart_db_version = "1.0";
function millikart_install(){
    global $wpdb;
    global $millikart_db_version;
    //Creat table in mysql for references
    $table_name = $wpdb->prefix . "woocommerce_millikart";
    if($wpdb->get_var("show tables like '$table_name'") != $table_name){
        $sql = "CREATE TABLE " . $table_name . " (
            id_reference mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) UNSIGNED NOT NULL,
            reference VARCHAR(55) NOT NULL,
            language VARCHAR(3) NOT NULL,
            PRIMARY KEY  (id_reference)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        add_option("millikart_db_version", $millikart_db_version);
    }
}
register_activation_hook(__FILE__,'millikart_install');

// Add Settings link
function millikart_settings_link($links) {
  $settings_link = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=millikart_payment').'">'.__('Settings', 'woocommerce-millikart-payment').'</a>';
  array_unshift($links, $settings_link);
  return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'millikart_settings_link');
