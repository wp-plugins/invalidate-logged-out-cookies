<?php
if ( defined('ABSPATH') && defined('WP_UNINSTALL_PLUGIN') ) {
	$GLOBALS['wpdb']->query( 'DROP TABLE ' . $GLOBALS['wpdb']->prefix.'invalidated_cookies' );
	delete_option('invalidated_cookies_db_ver');
}