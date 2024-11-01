<?php
/*
Plugin Name: WP link or article sharing
Plugin URI: http://kuaza.com
Description: Wordpress website for link sharing or article submit plugin
Version: 1.3
Lang Version: 1.2
Author: Selcuk kilic (Kuaza)
Author URI: http://kuaza.com
License: GPLv2 or later
*/

// gelistirici icindir: hatalari gormek icin (varsa) :)
//error_reporting(E_ALL); ini_set("display_errors", 1);

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'WLOPS_VERSION', '1.3' );
define( 'WLOPS_MINIMUM_WP_VERSION', '3.1' );
define( 'WLOPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WLOPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'wlops', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'wlops', 'plugin_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'wlops', 'plugin_delete') );

require_once( WLOPS_PLUGIN_DIR . 'class.WLOPS.php' );

function wlops_cikis_sayisi() {	

_deprecated_function( __FUNCTION__, '3.0', 'WLOPS::wlops_konu_cikis_sayisi()' );
	return WLOPS::wlops_konu_cikis_sayisi();
}