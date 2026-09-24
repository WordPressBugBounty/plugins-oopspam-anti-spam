<?php
/**
 * OOPSpam Anti-Spam – Settings Export / Import
 *
 * Shared logic used by both the WP-CLI command (`wp oopspam ...`) and the
 * admin "Tools" tab to export, import, and manage plugin settings.
 *
 * @package OOPSpam_Anti_Spam
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OOPSpam_Settings_Transfer' ) ) {

	/**
	 * Handles exporting and importing OOPSpam settings.
	 */
	class OOPSpam_Settings_Transfer {

		/**
		 * Option keys managed by the plugin that are part of a settings transfer.
		 *
		 * @return string[]
		 */
		public static function get_option_keys() {
			return array(
				'oopspamantispam_settings',
				'oopspamantispam_privacy_settings',
				'oopspamantispam_ratelimit_settings',
				'oopspamantispam_ipfiltering_settings',
				'oopspamantispam_misc_settings',
				'oopspamantispam_contextai_settings',
				'manual_moderation_settings',
				'oopspam_countryallowlist',
				'oopspam_countryblocklist',
				'oopspam_country_always_allow',
				'oopspam_languageallowlist',
				'oopspam_admin_emails',
			);
		}

		/**
		 * Option keys that store a flat list of values (country/language codes
		 * and admin email addresses). On import these must be merged by union,
		 * not concatenated, to avoid duplicate entries.
		 *
		 * @return string[]
		 */
		public static function get_list_option_keys() {
			return array(
				'oopspam_countryallowlist',
				'oopspam_countryblocklist',
				'oopspam_country_always_allow',
				'oopspam_languageallowlist',
				'oopspam_admin_emails',
			);
		}

		/**
		 * Read the plugin version from the main plugin header.
		 *
		 * @return string
		 */
		public static function get_plugin_version() {
			static $version = null;

			if ( null === $version ) {
				$version = '1.2.83';

				if ( function_exists( 'get_plugin_data' ) ) {
					$data = get_plugin_data( dirname( dirname( __FILE__ ) ) . '/oopspam-antispam.php', false, false );
					if ( ! empty( $data['Version'] ) ) {
						$version = $data['Version'];
					}
				}
			}

			return $version;
		}

		/**
		 * Collect the current value of every managed option.
		 *
		 * @return array<string,mixed>
		 */
		public static function collect_settings() {
			$settings = array();

			foreach ( self::get_option_keys() as $key ) {
				$settings[ $key ] = get_option( $key );
			}

			return $settings;
		}

		/**
		 * Build the full export payload.
		 *
		 * @return array
		 */
		public static function build_export() {
			return array(
				'plugin'      => 'oopspam-anti-spam',
				'version'     => self::get_plugin_version(),
				'exported_at' => gmdate( 'c' ),
				'settings'    => self::collect_settings(),
			);
		}

		/**
		 * Check whether a payload looks like a valid OOPSpam export.
		 *
		 * @param mixed $data Decoded payload.
		 * @return bool
		 */
		public static function is_valid_export( $data ) {
			if ( ! is_array( $data ) ) {
				return false;
			}

			// Accept the full wrapper ({"plugin":..., "settings": {...}}) or a bare settings map.
			if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
				return true;
			}

			$known = self::get_option_keys();
			foreach ( $known as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Recursively sanitize a single value.
		 *
		 * @param mixed $value Value to sanitize.
		 * @return mixed
		 */
		public static function sanitize_value( $value ) {
			if ( is_array( $value ) ) {
				$out = array();
				foreach ( $value as $k => $v ) {
					$out[ $k ] = self::sanitize_value( $v );
				}
				return $out;
			}

			if ( is_bool( $value ) ) {
				return $value;
			}

			if ( is_int( $value ) ) {
				return absint( $value );
			}

			if ( is_float( $value ) ) {
				return (float) $value;
			}

			if ( is_string( $value ) ) {
				$trimmed = trim( $value );

				// Preserve JSON strings (e.g. Forminator field mappings) untouched.
				if ( ( 0 === strpos( $trimmed, '{' ) || 0 === strpos( $trimmed, '[' ) ) && null !== json_decode( $trimmed ) ) {
					return $trimmed;
				}

				return sanitize_text_field( $value );
			}

			return '';
		}

		/**
		 * Sanitize a full settings map (option key => value).
		 *
		 * @param array $settings Raw settings map.
		 * @return array
		 */
		public static function sanitize_settings( $settings ) {
			if ( ! is_array( $settings ) ) {
				return array();
			}

			$sanitized = array();

			foreach ( self::get_option_keys() as $key ) {
				if ( ! array_key_exists( $key, $settings ) ) {
					continue;
				}

				if ( 'manual_moderation_settings' === $key ) {
					// Textareas hold one entry per line – keep line breaks.
					$value = is_array( $settings[ $key ] ) ? $settings[ $key ] : array();
					$out   = array();
					foreach ( $value as $field => $text ) {
						$out[ $field ] = is_string( $text ) ? sanitize_textarea_field( $text ) : self::sanitize_value( $text );
					}
					$sanitized[ $key ] = $out;
					continue;
				}

				$value = self::sanitize_value( $settings[ $key ] );

				// Flat list options must never contain duplicates.
				if ( in_array( $key, self::get_list_option_keys(), true ) && is_array( $value ) ) {
					$value = array_values( array_unique( $value ) );
				}

				$sanitized[ $key ] = $value;
			}

			return $sanitized;
		}

		/**
		 * Import settings into the database.
		 *
		 * @param mixed $data    Decoded payload (full export or a bare settings map).
		 * @param bool  $replace When true, replace each option entirely. When false,
		 *                       existing option keys are preserved and imported values override them.
		 * @return array{imported: string[], unchanged: string[], errors: string[]}
		 */
		public static function import( $data, $replace = false ) {
			$result = array(
				'imported'  => array(),
				'unchanged' => array(),
				'errors'    => array(),
			);

			if ( ! is_array( $data ) ) {
				$result['errors'][] = __( 'Invalid import data.', 'oopspam-anti-spam' );
				return $result;
			}

			$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : $data;
			$settings = self::sanitize_settings( $settings );

			foreach ( self::get_option_keys() as $key ) {
				if ( ! array_key_exists( $key, $settings ) ) {
					continue;
				}

				$incoming = $settings[ $key ];
				$existing = get_option( $key );

				if ( ! $replace ) {
					// Merge: keep existing keys, let the imported values win.
					if ( is_array( $existing ) && is_array( $incoming ) ) {
						if ( in_array( $key, self::get_list_option_keys(), true ) ) {
							// Flat lists (country/language/email): union, never append duplicates.
							$incoming = array_values( array_unique( array_merge( $existing, $incoming ) ) );
						} else {
							// Associative maps: existing keys preserved, imported values override.
							$incoming = array_merge( $existing, $incoming );
						}
					} elseif ( null === $existing && '' === $incoming ) {
						$incoming = null;
					}
				}

				// Never wipe the API usage counter on the main settings.
				if ( 'oopspamantispam_settings' === $key && is_array( $existing ) && isset( $existing['oopspam_api_key_usage'] ) ) {
					if ( is_array( $incoming ) ) {
						$incoming['oopspam_api_key_usage'] = $existing['oopspam_api_key_usage'];
					} else {
						$incoming = $existing;
					}
				}

				if ( null === $incoming ) {
					// A null value means "unset" – keep whatever exists.
					$result['unchanged'][] = $key;
					continue;
				}

				if ( $existing === $incoming ) {
					$result['unchanged'][] = $key;
					continue;
				}

				$updated = update_option( $key, $incoming );

				if ( false === $updated && get_option( $key ) === $incoming ) {
					// update_option() returns false when the value did not change.
					$result['unchanged'][] = $key;
				} elseif ( false === $updated ) {
					$result['errors'][] = sprintf(
						/* translators: %s: option key. */
						__( 'Failed to update %s.', 'oopspam-anti-spam' ),
						$key
					);
				} else {
					$result['imported'][] = $key;
				}
			}

			return $result;
		}

		/**
		 * Set a single option (or a nested key inside an array option).
		 *
		 * Supports dotted paths, e.g. "oopspamantispam_settings.oopspam_api_key".
		 *
		 * @param string $path  Option name or "option_name.subkey" path.
		 * @param mixed  $value New value.
		 * @return bool
		 */
		public static function set_setting( $path, $value ) {
			$parts = explode( '.', $path, 2 );
			$key   = $parts[0];

			if ( ! in_array( $key, self::get_option_keys(), true ) ) {
				return false;
			}

			// If a JSON string was passed, decode it so nested data can be set.
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( null !== $decoded && json_last_error() === JSON_ERROR_NONE ) {
					$value = $decoded;
				}
			}

			$value = self::sanitize_value( $value );

			if ( ! isset( $parts[1] ) ) {
				return update_option( $key, $value );
			}

			$current = get_option( $key, array() );
			if ( ! is_array( $current ) ) {
				$current = array();
			}

			$current[ $parts[1] ] = $value;

			return update_option( $key, $current );
		}

		/**
		 * Return a single option value (or a nested key) if it exists.
		 *
		 * @param string $path Option name or "option_name.subkey" path.
		 * @return mixed|null Null when not found.
		 */
		public static function get_setting( $path ) {
			$parts = explode( '.', $path, 2 );
			$key   = $parts[0];

			if ( ! in_array( $key, self::get_option_keys(), true ) ) {
				return null;
			}

			$value = get_option( $key );

			if ( isset( $parts[1] ) ) {
				if ( is_array( $value ) && array_key_exists( $parts[1], $value ) ) {
					return $value[ $parts[1] ];
				}
				return null;
			}

			return $value;
		}

		/**
		 * Reset every managed option back to its default state.
		 *
		 * @return array<string,bool> option key => whether it was reset.
		 */
		public static function reset_settings() {
			$results = array();

			foreach ( self::get_option_keys() as $key ) {
				$default = self::get_default( $key );
				$results[ $key ] = update_option( $key, $default );
			}

			return $results;
		}

		/**
		 * Default value used when resetting an option.
		 *
		 * @param string $key Option key.
		 * @return mixed
		 */
		public static function get_default( $key ) {
			switch ( $key ) {
				case 'oopspamantispam_settings':
					return array(
						'oopspam_api_key'        => '',
						'oopspam_spam_message'   => '',
						'oopspam_is_activated'   => '1',
					);
				default:
					return array();
			}
		}

		/**
		 * Mask an API key so it can safely be printed in logs/status output.
		 *
		 * @param string $key API key.
		 * @return string
		 */
		public static function mask_api_key( $key ) {
			if ( empty( $key ) ) {
				return '';
			}

			$length = strlen( $key );
			if ( $length <= 6 ) {
				return str_repeat( '*', $length );
			}

			return substr( $key, 0, 4 ) . str_repeat( '*', max( 4, $length - 8 ) ) . substr( $key, -4 );
		}
	}
}
