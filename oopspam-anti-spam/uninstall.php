<?php
if ( ! current_user_can( 'activate_plugins' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) || !defined('WP_UNINSTALL_PLUGIN') ) {
	exit();
}

delete_option( 'oopspamantispam_settings' );
delete_option( 'manual_moderation_settings' );
delete_option( 'oopspamantispam_privacy_settings' );
delete_option( 'oopspamantispam_ratelimit_settings' );
delete_option( 'oopspam_db_version' );
delete_option( 'oopspam-activation-date' );
delete_option( 'oopspam_countryallowlist' );
delete_option( 'oopspam_languageallowlist' );
delete_option( 'oopspam_countryblocklist' );
delete_option( 'oopspam_is_check_for_length' );

wp_clear_scheduled_hook( 'oopspam_cleanup_ham_entries_cron' );
wp_clear_scheduled_hook( 'oopspam_cleanup_spam_entries_cron' );
wp_clear_scheduled_hook( 'oopspam_cleanup_ratelimit_entries_cron' );


/* 
 * Remove OOPSpam-related tables
 */
global $wpdb;
$tb_spam_entries = $wpdb->prefix . 'oopspam_frm_spam_entries';
$tb_ham_entries = $wpdb->prefix . 'oopspam_frm_ham_entries';
$tb_rate_limiting = $wpdb->prefix . 'oopspam_rate_limits';

// drop the tables from the database.
$wpdb->query( "DROP TABLE IF EXISTS $tb_spam_entries" );
$wpdb->query( "DROP TABLE IF EXISTS $tb_ham_entries" );
$wpdb->query( "DROP TABLE IF EXISTS $tb_rate_limiting" );