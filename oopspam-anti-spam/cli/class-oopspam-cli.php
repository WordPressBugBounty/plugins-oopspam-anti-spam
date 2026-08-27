<?php
/**
 * WP-CLI commands for the OOPSpam Anti-Spam plugin.
 *
 * Provides:
 *   wp oopspam status
 *   wp oopspam get [<setting>] [--format=json]
 *   wp oopspam set <setting> <value>
 *   wp oopspam set-many <json>
 *   wp oopspam export [<file>]
 *   wp oopspam import <file> [--replace] [--all-sites]
 *   wp oopspam reset [--all-sites]
 *
 * @package OOPSpam_Anti_Spam
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WP_CLI\Utils as WP_CLI_Utils;

/**
 * Manage OOPSpam Anti-Spam settings from the command line.
 */
class OOPSpam_Command extends WP_CLI_Command {

	/**
	 * Make sure the shared settings transfer class is available.
	 *
	 * @return void
	 */
	private function ensure_transfer_class() {
		if ( ! class_exists( 'OOPSpam_Settings_Transfer' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/include/class-oopspam-settings-transfer.php';
		}
	}

	/**
	 * Show the OOPSpam plugin status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam status
	 *
	 * @subcommand status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		$options = get_option( 'oopspamantispam_settings', array() );
		$api_key = defined( 'OOPSPAM_API_KEY' ) ? OOPSPAM_API_KEY : ( isset( $options['oopspam_api_key'] ) ? $options['oopspam_api_key'] : '' );

		$rate_limiting = 'no';
		if ( function_exists( 'oopspam_isRateLimitingEnabled' ) ) {
			$rate_limiting = oopspam_isRateLimitingEnabled() ? 'yes' : 'no';
		}

		$data = array(
			'plugin_version'  => OOPSpam_Settings_Transfer::get_plugin_version(),
			'configured'      => empty( $api_key ) ? 'no' : 'yes',
			'api_key'         => OOPSpam_Settings_Transfer::mask_api_key( $api_key ),
			'api_key_source'  => isset( $options['oopspam_api_key_source'] ) ? $options['oopspam_api_key_source'] : '',
			'rate_limiting'   => $rate_limiting,
			'site_url'        => home_url(),
			'multisite'       => is_multisite() ? 'yes' : 'no',
		);

		WP_CLI_Utils\format_items( 'table', array( $data ), array_keys( $data ) );
	}

	/**
	 * Get OOPSpam settings.
	 *
	 * Without arguments, prints the whole settings collection. Pass a setting
	 * name to read a single value. Dotted paths are supported, e.g.
	 * "oopspamantispam_settings.oopspam_api_key".
	 *
	 * ## OPTIONS
	 *
	 * [<setting>]
	 * : Setting name or dotted path to read. Omit to dump all settings.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or plain. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - plain
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam get
	 *     wp oopspam get oopspamantispam_settings.oopspam_api_key --format=plain
	 *     wp oopspam get --format=json
	 *
	 * @subcommand get
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		$path   = isset( $args[0] ) ? $args[0] : null;
		$format = WP_CLI_Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( null !== $path ) {
			$value = OOPSpam_Settings_Transfer::get_setting( $path );

			if ( null === $value ) {
				WP_CLI::error( sprintf( "Setting '%s' not found.", $path ) );
			}

			if ( 'json' === $format ) {
				WP_CLI::log( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			} elseif ( is_scalar( $value ) || null === $value ) {
				WP_CLI::log( (string) $value );
			} else {
				WP_CLI::log( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( OOPSpam_Settings_Transfer::build_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$rows = array();
		foreach ( OOPSpam_Settings_Transfer::collect_settings() as $key => $value ) {
			$rows[] = array(
				'key'   => $key,
				'value' => is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value ),
			);
		}

		WP_CLI_Utils\format_items( 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Set a single OOPSpam setting.
	 *
	 * Dotted paths are supported, e.g. "oopspamantispam_settings.oopspam_api_key".
	 * When a JSON value is provided it is decoded automatically.
	 *
	 * ## OPTIONS
	 *
	 * <setting>
	 * : Setting name or dotted path, e.g. oopspamantispam_settings.oopspam_api_key.
	 *
	 * <value>
	 * : The value to set. Pass a JSON object/array to set structured data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam set oopspamantispam_settings.oopspam_api_key "abcdef123456"
	 *     wp oopspam set oopspamantispam_ratelimit_settings '{"oopspam_is_rt_enabled":"1","oopspamantispam_ratelimit_ip_limit":50}'
	 *     wp oopspam set oopspam_countryblocklist '["CN","RU"]'
	 *
	 * @subcommand set
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function set( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		if ( count( $args ) < 2 ) {
			WP_CLI::error( 'Usage: wp oopspam set <setting> <value>' );
		}

		$path  = $args[0];
		$value = $args[1];

		if ( ! OOPSpam_Settings_Transfer::set_setting( $path, $value ) ) {
			WP_CLI::error( sprintf( "Could not update '%s'. Check that the setting name is valid.", $path ) );
		}

		WP_CLI::success( sprintf( "Setting '%s' updated.", $path ) );
	}

	/**
	 * Set multiple OOPSpam settings at once from JSON.
	 *
	 * Accepts either a map of option keys to full values, or a map of dotted
	 * paths (option.subkey) to values.
	 *
	 * ## OPTIONS
	 *
	 * <json>
	 * : JSON object mapping settings to values.
	 *
	 * [--all-sites]
	 * : On a multisite network, apply the changes to every site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam set-many '{"oopspamantispam_settings.oopspam_spam_message":"Blocked","oopspamantispam_settings.oopspam_api_key":"abc123"}'
	 *     wp oopspam set-many '{"oopspam_countryblocklist":["CN"]}' --all-sites
	 *
	 * @subcommand set-many
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function set_many( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Usage: wp oopspam set-many <json>' );
		}

		$data = json_decode( $args[0], true );
		if ( null === $data || ! is_array( $data ) ) {
			WP_CLI::error( 'The JSON is not valid.' );
		}

		$all_sites = WP_CLI_Utils\get_flag_value( $assoc_args, 'all-sites', false );

		$this->for_each_site(
			function ( $blog_id ) use ( $data ) {
				$updated = array();

				foreach ( $data as $path => $value ) {
					if ( OOPSpam_Settings_Transfer::set_setting( $path, $value ) ) {
						$updated[] = $path;
					}
				}

				WP_CLI::success(
					sprintf( 'Site %d (%s): updated %s.', $blog_id, home_url(), $updated ? implode( ', ', $updated ) : 'nothing' )
				);
			},
			$all_sites
		);
	}

	/**
	 * Export OOPSpam settings to a JSON file (or stdout).
	 *
	 * The exported file can be imported on another site with
	 * `wp oopspam import`, or via the plugin's Tools tab.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Path to write the JSON export to. If omitted, the JSON is printed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam export /tmp/oopspam-settings.json
	 *     wp oopspam export > oopspam-settings.json
	 *
	 * @subcommand export
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		$file    = isset( $args[0] ) ? $args[0] : null;
		$payload = OOPSpam_Settings_Transfer::build_export();
		$json    = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( null !== $file ) {
			if ( false === file_put_contents( $file, $json ) ) {
				WP_CLI::error( sprintf( "Could not write to '%s'.", $file ) );
			}
			WP_CLI::success( sprintf( "Settings exported to '%s'.", $file ) );
			return;
		}

		WP_CLI::log( $json );
	}

	/**
	 * Import OOPSpam settings from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a JSON export produced by `wp oopspam export` or the plugin UI.
	 *
	 * [--replace]
	 * : Replace each imported option entirely. By default, existing settings
	 *   are preserved and imported values override them (merge).
	 *
	 * [--all-sites]
	 * : On a multisite network, import the settings into every site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam import /tmp/oopspam-settings.json
	 *     wp oopspam import /tmp/oopspam-settings.json --replace
	 *     wp oopspam import /tmp/oopspam-settings.json --all-sites
	 *
	 * @subcommand import
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Usage: wp oopspam import <file>' );
		}

		$file = $args[0];

		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( "Could not read '%s'.", $file ) );
		}

		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( null === $data ) {
			WP_CLI::error( 'The file does not contain valid JSON.' );
		}

		if ( ! OOPSpam_Settings_Transfer::is_valid_export( $data ) ) {
			WP_CLI::error( "The file does not look like an OOPSpam settings export. Use 'wp oopspam export' to create one." );
		}

		$replace   = WP_CLI_Utils\get_flag_value( $assoc_args, 'replace', false );
		$all_sites = WP_CLI_Utils\get_flag_value( $assoc_args, 'all-sites', false );

		$this->for_each_site(
			function ( $blog_id ) use ( $data, $replace ) {
				$result = OOPSpam_Settings_Transfer::import( $data, $replace );

				WP_CLI::log( sprintf( 'Site %d (%s):', $blog_id, home_url() ) );

				if ( $result['imported'] ) {
					WP_CLI::success( 'Imported: ' . implode( ', ', $result['imported'] ) );
				}
				if ( $result['unchanged'] ) {
					WP_CLI::log( 'Already up to date: ' . implode( ', ', $result['unchanged'] ) );
				}
				foreach ( $result['errors'] as $error ) {
					WP_CLI::warning( $error );
				}

				if ( ! $result['imported'] && ! $result['errors'] ) {
					WP_CLI::log( 'No changes were needed.' );
				}
			},
			$all_sites
		);
	}

	/**
	 * Reset OOPSpam settings to their default state.
	 *
	 * ## OPTIONS
	 *
	 * [--all-sites]
	 * : On a multisite network, reset the settings on every site.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oopspam reset
	 *     wp oopspam reset --yes --all-sites
	 *
	 * @subcommand reset
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function reset( $args, $assoc_args ) {
		$this->ensure_transfer_class();

		WP_CLI::confirm( 'Are you sure you want to reset all OOPSpam settings?', $assoc_args );

		$all_sites = WP_CLI_Utils\get_flag_value( $assoc_args, 'all-sites', false );

		$this->for_each_site(
			function ( $blog_id ) {
				$results = OOPSpam_Settings_Transfer::reset_settings();
				$count   = count( array_filter( $results ) );

				WP_CLI::success( sprintf( 'Site %d (%s): reset %d setting group(s).', $blog_id, home_url(), $count ) );
			},
			$all_sites
		);
	}

	/**
	 * Run a callback for the current site or, with $all_sites, every site
	 * in a multisite network.
	 *
	 * @param callable $callback  Callable receiving the blog ID.
	 * @param bool     $all_sites Whether to loop over all sites.
	 * @return void
	 */
	private function for_each_site( $callback, $all_sites ) {
		if ( ! $all_sites ) {
			$callback( get_current_blog_id() );
			return;
		}

		if ( ! is_multisite() ) {
			WP_CLI::warning( 'This is not a multisite network. The command ran for the current site only.' );
			$callback( get_current_blog_id() );
			return;
		}

		$sites = get_sites( array( 'number' => 0 ) );

		if ( empty( $sites ) ) {
			WP_CLI::warning( 'No sites found in the network.' );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			$callback( $site->blog_id );
			restore_current_blog();
		}
	}
}
