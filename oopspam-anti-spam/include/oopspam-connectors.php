<?php
/**
 * OOPSpam Connectors API support (WordPress 7.0+).
 *
 * Registers OOPSpam as a core "Connectors" service (type: spam_filtering) so
 * administrators can store the OOPSpam API key from the core screen at
 * Settings -> Connectors, alongside the existing OOPSpam settings page and the
 * OOPSPAM_API_KEY constant.
 *
 * @package OOPSpam_Anti_Spam
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the core Connectors API is available (WordPress 7.0+).
 *
 * @return bool
 */
function oopspam_connectors_supported() {
	return class_exists( 'WP_Connector_Registry' ) || function_exists( 'wp_get_connectors' );
}

/**
 * Register the OOPSpam connector on the core Connectors registry.
 *
 * @param object $registry The WP_Connector_Registry instance.
 * @return void
 */
function oopspam_register_connector( $registry ) {
	if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
		return;
	}

	// Never override an existing registration silently.
	if ( method_exists( $registry, 'is_registered' ) && $registry->is_registered( 'oopspam' ) ) {
		return;
	}

	// The OOPSpam mark, shipped with the plugin and rendered by the core
	// Connectors screen. plugins_url() needs a *file* inside the plugin (not the
	// plugin directory) so the plugin folder is kept in the resulting URL.
	$oopspam_plugin_file = dirname( __DIR__ ) . '/oopspam-antispam.php';

	/**
	 * Filter the OOPSpam connector registration args before they reach core.
	 *
	 * @param array $args Connector registration arguments.
	 */
	$args = apply_filters(
		'oopspam_connector_registration_args',
		array(
			'name'           => __( 'OOPSpam', 'oopspam-anti-spam' ),
			'description'    => __( 'Block spam in comments and forms with the OOPSpam API.', 'oopspam-anti-spam' ),
			'logo_url'       => plugins_url( 'assets/oopspam-logo.svg', $oopspam_plugin_file ),
			'type'           => 'spam_filtering',
			'plugin'         => array(
				'file'      => 'oopspam-anti-spam/oopspam-antispam.php',
				'is_active' => static function () {
					return function_exists( 'oopspamantispam_get_key' );
				},
			),
			'authentication' => array(
				'method'          => 'api_key',
				'setting_name'    => defined( 'OOPSPAM_CONNECTOR_API_KEY' ) ? OOPSPAM_CONNECTOR_API_KEY : 'oopspam_api_key',
				'constant_name'   => 'OOPSPAM_API_KEY',
				'env_var_name'    => 'OOPSPAM_API_KEY',
				'credentials_url' => 'https://app.oopspam.com/',
			),
		)
	);

	$registry->register( 'oopspam', $args );
}

// Only hook the registration action when the API can fire it (WP 7.0+).
if ( oopspam_connectors_supported() ) {
	add_action( 'wp_connectors_init', 'oopspam_register_connector' );
}
