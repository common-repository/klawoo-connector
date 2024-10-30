<?php
/*
 * Plugin Name: Klawoo Connector
 * Plugin URI: http://storeapps.org
 * Description: Connect Wordpress with Klawoo - The next generation customer engagement and marketing platform.
 * Version: 1.9
 * Author: storeapps
 * Author URI: http://storeapps.org/
 * WC requires at least: 2.0.0
 * WC tested up to: 3.2.6
 * License: GPL 3.0
*/

// Table creation on installtion
register_activation_hook( __FILE__, 'klawoo_create_tables' );

function klawoo_create_tables(){
    global $wpdb;

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $collate = '';

    if ( $wpdb->has_cap( 'collation' ) ) {
        if( ! empty($wpdb->charset ) )
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
        if( ! empty($wpdb->collate ) )
                $collate .= " COLLATE $wpdb->collate";
    }

    $create_table_query = " CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wc_klawoo` (
                                `id` bigint(20) unsigned NOT NULL,
                                `list_name` text NOT NULL,
                                `list_id` varchar(50) NOT NULL,
                                `brand_id` varchar(50) NOT NULL,
                                `custom_attributes` varchar(255) NOT NULL,
                                 PRIMARY KEY  (list_id)
                                ) $collate; ";
   
    dbDelta( $create_table_query );
    
    // changes to add type of email service when activate the plugin
    $get_email_service_settings = get_option('wc_klawoo_smtp_settings');
    
    if( empty($get_email_service_settings['type'])){
        $get_email_service_settings['type'] = 'smtp';
        update_option('wc_klawoo_smtp_settings', $get_email_service_settings );
    }
    
    
}

add_action( 'plugins_loaded', 'wc_klawoo_pre_init' );

function wc_klawoo_pre_init() {
    // Simple check for WooCommerce being active...
    if ( class_exists('WooCommerce') ) {
        wc_klawoo_init();
    }
}

function wc_klawoo_init() {
    include_once 'classes/class.wc-klawoo.php';
    $GLOBALS['wc_klawoo'] = WC_Klawoo::getInstance();
        
}