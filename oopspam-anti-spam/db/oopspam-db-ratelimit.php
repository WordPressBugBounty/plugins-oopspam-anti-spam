<?php

global $oopspam_rt_db_version;
$oopspam_rt_db_version = '1.0';


function oopspam_rt_db_install() {
	global $wpdb, $oopspam_rt_db_version;

	$charset_collate = $wpdb->get_charset_collate();
	$rt_table_name = $wpdb->prefix . 'oopspam_rate_limits';

	$sql = "CREATE TABLE $rt_table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		identifier varchar(200) NOT NULL,
		type varchar(10) NOT NULL,
		attempts int(11) NOT NULL DEFAULT '1',
		first_attempt datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		last_attempt datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		is_blocked tinyint(1) NOT NULL DEFAULT '0',
		blocked_until datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY identifier_type (identifier, type)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	update_option( 'oopspam_rt_db_version', $oopspam_rt_db_version );
}

function oopspam_update_rt_db_check() {
	global $oopspam_rt_db_version;

	if ( get_option( 'oopspam_rt_db_version' ) !== $oopspam_rt_db_version ) {
		oopspam_rt_db_install();
	}
}
add_action( 'plugins_loaded', 'oopspam_update_rt_db_check' );