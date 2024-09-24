<?php

global $oopspam_db_version;
$oopspam_db_version = '1.4';

function oopspam_db_install() {
	global $wpdb;
	global $oopspam_db_version;

	$table_name = $wpdb->prefix . 'oopspam_frm_spam_entries';
	$ham_table_name = $wpdb->prefix . 'oopspam_frm_ham_entries';

	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        form_id varchar(50) NULL,
		message TEXT NULL,
        ip varchar(100) NULL,
		email varchar(100) NULL,
		score int NULL,
        raw_entry MEDIUMTEXT NULL,
		reported boolean NULL,
		reason varchar(100) NULL,
		date timestamp default current_timestamp not null,
		PRIMARY KEY  (id)
	) $charset_collate; CREATE TABLE $ham_table_name (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        form_id varchar(50) NULL,
		message TEXT NULL,
        ip varchar(100) NULL,
		email varchar(100) NULL,
		score int NULL,
        raw_entry MEDIUMTEXT NULL,
		reported boolean NULL,
		date timestamp default current_timestamp not null,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'oopspam_db_version', $oopspam_db_version );

	/*Adding an Upgrade Function*/

	global $wpdb;
	$installed_ver = get_option( "oopspam_db_version" );

	if ( $installed_ver != $oopspam_db_version ) {

		$table_name = $wpdb->prefix . 'oopspam_frm_spam_entries';
		$ham_table_name = $wpdb->prefix . 'oopspam_frm_ham_entries';

		$sql = "CREATE TABLE $table_name (
			id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			form_id varchar(51) NULL,
			message TEXT NULL,
			ip varchar(100) NULL,
			email varchar(100) NULL,
			score int NULL,
			raw_entry MEDIUMTEXT NULL,
			reported boolean NULL,
			date timestamp default current_timestamp not null,
			PRIMARY KEY  (id)
		) $charset_collate; CREATE TABLE $ham_table_name (
			id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			form_id varchar(50) NULL,
			message TEXT NULL,
			ip varchar(100) NULL,
			email varchar(100) NULL,
			score int NULL,
			raw_entry MEDIUMTEXT NULL,
			reported boolean NULL,
			date timestamp default current_timestamp not null,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( "oopspam_db_version", $oopspam_db_version );
	}
}


function oopspam_update_db_check() {
    global $oopspam_db_version;
    if ( get_site_option( 'oopspam_db_version' ) != $oopspam_db_version ) {
        oopspam_db_install();
    }
}
add_action( 'plugins_loaded', 'oopspam_update_db_check' );