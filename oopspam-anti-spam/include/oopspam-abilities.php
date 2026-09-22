<?php
/**
 * OOPSpam Abilities API integration.
 *
 * Exposes curated OOPSpam Anti-Spam functionality through the WordPress
 * Abilities API (WordPress 6.9+). Abilities are machine-readable, schema
 * validated, permission-gated units of work that can be consumed by the
 * official MCP Adapter plugin (AI agents), the REST API, or other plugins.
 *
 * @package OOPSpam_Anti_Spam
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether OOPSpam abilities should be registered.
 *
 * Respects (in order of precedence):
 *  1. The OOPSPAM_ENABLE_ABILITIES constant (true = force on).
 *  2. The 'oopspam_are_abilities_enabled' filter.
 *  3. The saved setting 'oopspam_abilities_enabled' (admin opt-in). Default off.
 *
 * @return bool
 */
function oopspam_are_abilities_enabled() {
	if ( defined( 'OOPSPAM_ENABLE_ABILITIES' ) ) {
		$enabled = (bool) OOPSPAM_ENABLE_ABILITIES;
	} else {
		$misc    = get_option( 'oopspamantispam_misc_settings', array() );
		$enabled = isset( $misc['oopspam_abilities_enabled'] );
	}

	/**
	 * Filter whether the OOPSpam Abilities API integration is active.
	 *
	 * @param bool $enabled Whether abilities are enabled.
	 */
	return (bool) apply_filters( 'oopspam_are_abilities_enabled', $enabled );
}

/**
 * Builds a permission callback for an ability.
 *
 * Defaults to manage_options (admins only). The capability can be narrowed or
 * widened per ability using the filter below.
 *
 * @param string $ability_name The ability name, e.g. 'oopspam/check-submission'.
 * @return callable
 */
function oopspam_ability_permission_callback( $ability_name ) {
	return function () use ( $ability_name ) {
		$cap = apply_filters( 'oopspam_abilities_permission_cap', 'manage_options', $ability_name );

		if ( ! is_string( $cap ) || '' === trim( $cap ) ) {
			$cap = 'manage_options';
		}

		return current_user_can( $cap );
	};
}

/**
 * Mask an API key so it is never exposed in full.
 *
 * @param string $key Raw API key.
 * @return string Masked key.
 */
function oopspam_mask_api_key( $key ) {
	if ( ! is_string( $key ) || '' === $key ) {
		return '';
	}

	if ( strlen( $key ) <= 8 ) {
		return str_repeat( '*', strlen( $key ) );
	}

	return substr( $key, 0, 4 ) . '…' . substr( $key, -4 );
}

/**
 * Validate a manual-moderation email or wildcard pattern.
 *
 * Allows exact addresses (user@example.com) and local-part prefix wildcards
 * (*@example.com, spam*@example.com). Returns false for anything else.
 *
 * @param string $value The value to validate.
 * @return bool
 */
function oopspam_validate_blocklist_email( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( '' === $value || strlen( $value ) > 254 || preg_match( '/\s/', $value ) ) {
		return false;
	}

	$parts = explode( '@', $value, 2 );
	if ( 2 !== count( $parts ) || '' === $parts[1] ) {
		return false;
	}

	$local  = $parts[0];
	$domain = $parts[1];

	// A wildcard is only allowed as a prefix of the local part.
	if ( false !== strpos( $local, '*' ) && 0 !== strpos( $local, '*' ) ) {
		return false;
	}

	$local_check = str_replace( '*', '', $local );
	if ( ! preg_match( "/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]*$/", $local_check ) ) {
		return false;
	}

	// Domain must be a plausible real domain (no wildcards there).
	if ( false !== strpos( $domain, '*' ) || ! filter_var( 'a@' . $domain, FILTER_VALIDATE_EMAIL ) ) {
		return false;
	}

	return true;
}

/**
 * Validate a manual-moderation IP: exact IPv4/IPv6, CIDR, or dash range.
 *
 * @param string $value The value to validate.
 * @return bool
 */
function oopspam_validate_blocklist_ip( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( '' === $value || strlen( $value ) > 45 ) {
		return false;
	}

	// Exact IP.
	if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
		return true;
	}

	// CIDR, e.g. 192.168.1.0/24 or 2001:db8::/32.
	if ( false !== strpos( $value, '/' ) ) {
		list( $ip, $mask ) = array_map( 'trim', explode( '/', $value, 2 ) );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || ! ctype_digit( $mask ) ) {
			return false;
		}
		$max = ( false === strpos( $ip, ':' ) ) ? 32 : 128;
		$n   = (int) $mask;
		return $n >= 0 && $n <= $max;
	}

	// Dash range, e.g. 192.168.1.1-192.168.1.10.
	if ( false !== strpos( $value, '-' ) ) {
		list( $start, $end ) = array_map( 'trim', explode( '-', $value, 2 ) );
		return filter_var( $start, FILTER_VALIDATE_IP ) && filter_var( $end, FILTER_VALIDATE_IP );
	}

	return false;
}

/**
 * Execute callback: OOPSpam status / configuration snapshot.
 *
 * @return array
 */
function oopspam_ability_status() {
	$configured = ( function_exists( 'oopspamantispam_checkIfValidKey' ) && oopspamantispam_checkIfValidKey() );

	$api_key    = '';
	$key_source = 'none';

	if ( defined( 'OOPSPAM_API_KEY' ) ) {
		$api_key    = OOPSPAM_API_KEY;
		$key_source = 'constant';
	} elseif ( function_exists( 'oopspamantispam_get_key' ) ) {
		$api_key = oopspamantispam_get_key();
	}

	$settings = get_option( 'oopspamantispam_settings', array() );
	if ( 'none' === $key_source && isset( $settings['oopspam_api_key_source'] ) && ! empty( $settings['oopspam_api_key_source'] ) ) {
		$key_source = $settings['oopspam_api_key_source'];
	}

	$version = '';
	if ( function_exists( 'get_plugin_data' ) ) {
		$main_file = dirname( dirname( __FILE__ ) ) . '/oopspam-antispam.php';
		$data      = get_plugin_data( $main_file, false, false );
		if ( isset( $data['Version'] ) ) {
			$version = $data['Version'];
		}
	}

	return array(
		'configured'                   => (bool) $configured,
		'api_key_set'                  => (bool) $api_key,
		'api_key_masked'               => oopspam_mask_api_key( $api_key ),
		'api_key_source'               => (string) $key_source,
		'spam_score_threshold'         => function_exists( 'oopspamantispam_get_spamscore_threshold' ) ? (int) oopspamantispam_get_spamscore_threshold() : 0,
		'rate_limiting_enabled'        => function_exists( 'oopspam_isRateLimitingEnabled' ) ? (bool) oopspam_isRateLimitingEnabled() : false,
		'woocommerce_protection'       => function_exists( 'oopspam_is_spamprotection_enabled' ) ? (bool) oopspam_is_spamprotection_enabled( 'woo' ) : false,
		'wordpress_version'            => get_bloginfo( 'version' ),
		'plugin_version'               => (string) $version,
	);
}

/**
 * Execute callback: analyze a submission with the full OOPSpam pipeline.
 *
 * @param array $input Validated input.
 * @return array|\WP_Error
 */
function oopspam_ability_check_submission( $input ) {
	$input = is_array( $input ) ? $input : array();

	$content = isset( $input['content'] ) ? (string) $input['content'] : '';
	$ip      = isset( $input['ip'] ) ? trim( (string) $input['ip'] ) : '';
	$email   = isset( $input['email'] ) ? trim( (string) $input['email'] ) : '';

	if ( '' === $content ) {
		return new WP_Error( 'oopspam_missing_content', __( 'The content field is required.', 'oopspam-anti-spam' ) );
	}

	if ( '' !== $email && ! is_email( $email ) ) {
		return new WP_Error( 'oopspam_invalid_email', __( 'The provided email address is not valid.', 'oopspam-anti-spam' ) );
	}

	if ( '' !== $ip && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return new WP_Error( 'oopspam_invalid_ip', __( 'The provided IP address is not valid.', 'oopspam-anti-spam' ) );
	}

	if ( ! function_exists( 'oopspam_check_spam' ) ) {
		return new WP_Error( 'oopspam_unavailable', __( 'The OOPSpam detection function is unavailable.', 'oopspam-anti-spam' ) );
	}

	$result = oopspam_check_spam(
		$ip,
		$email,
		$content,
		array(
			'log'      => ! empty( $input['log'] ),
			'form_id'  => isset( $input['form_id'] ) ? sanitize_text_field( (string) $input['form_id'] ) : 'Abilities API',
			'raw_data' => '',
		)
	);

	return array(
		'is_spam' => isset( $result['isSpam'] ) ? (bool) $result['isSpam'] : false,
		'score'   => isset( $result['Score'] ) ? (int) $result['Score'] : -2,
		'reason'  => isset( $result['Reason'] ) ? (string) $result['Reason'] : '',
	);
}

/**
 * Execute callback: aggregate spam/ham statistics.
 *
 * @return array
 */
function oopspam_ability_get_stats() {
	global $wpdb;

	$spam_table = $wpdb->prefix . 'oopspam_frm_spam_entries';
	$ham_table  = $wpdb->prefix . 'oopspam_frm_ham_entries';
	$since_today = gmdate( 'Y-m-d 00:00:00' );

	$count = function ( $table, $since = '' ) use ( $wpdb ) {
		if ( '' !== $since ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE date >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	};

	return array(
		'spam_total' => $count( $spam_table ),
		'ham_total'  => $count( $ham_table ),
		'spam_today' => $count( $spam_table, $since_today ),
		'ham_today'  => $count( $ham_table, $since_today ),
	);
}

/**
 * Execute callback: return recent spam entries.
 *
 * @param array $input Validated input.
 * @return array
 */
function oopspam_ability_list_recent_spam( $input ) {
	global $wpdb;

	$input  = is_array( $input ) ? $input : array();
	$limit  = isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 20;
	$search = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';

	$table = $wpdb->prefix . 'oopspam_frm_spam_entries';
	$sql   = "SELECT id, form_id, message, ip, email, score, reason, date FROM {$table}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$args  = array();

	if ( '' !== $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$sql .= ' WHERE email LIKE %s OR ip LIKE %s OR reason LIKE %s OR message LIKE %s';
		$args = array( $like, $like, $like, $like );
	}

	$sql .= ' ORDER BY id DESC LIMIT %d';
	$args[] = $limit;

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

	$entries = array();
	foreach ( (array) $rows as $row ) {
		$entries[] = array(
			'id'      => (int) $row['id'],
			'form_id' => isset( $row['form_id'] ) ? (string) $row['form_id'] : '',
			'ip'      => isset( $row['ip'] ) ? (string) $row['ip'] : '',
			'email'   => isset( $row['email'] ) ? (string) $row['email'] : '',
			'score'   => isset( $row['score'] ) ? (int) $row['score'] : 0,
			'reason'  => isset( $row['reason'] ) ? (string) $row['reason'] : '',
			'message' => isset( $row['message'] ) ? (string) $row['message'] : '',
			'date'    => isset( $row['date'] ) ? (string) $row['date'] : '',
		);
	}

	return array(
		'total'   => count( $entries ),
		'entries' => $entries,
	);
}

/**
 * Execute callback: read the current manual-moderation lists.
 *
 * @return array
 */
function oopspam_ability_list_moderation_lists() {
	$keys = array(
		'blocked_emails' => 'mm_blocked_emails',
		'allowed_emails' => 'mm_allowed_emails',
		'blocked_ips'    => 'mm_blocked_ips',
		'allowed_ips'    => 'mm_allowed_ips',
		'blocked_keywords' => 'mm_blocked_keywords',
	);

	$out = array();
	foreach ( $keys as $label => $option_key ) {
		$out[ $label ] = function_exists( 'oopspam_get_manual_moderation_entries' )
			? oopspam_get_manual_moderation_entries( $option_key )
			: array();
	}

	return $out;
}

/**
 * Execute callback: report a submission to the OOPSpam API as spam/ham (feedback).
 *
 * @param array $input Validated input.
 * @return array|\WP_Error
 */
function oopspam_ability_report_submission( $input ) {
	$input = is_array( $input ) ? $input : array();

	if ( ! function_exists( 'oopspamantispam_checkIfValidKey' ) || ! oopspamantispam_checkIfValidKey() ) {
		return new WP_Error( 'oopspam_api_key_missing', __( 'OOPSpam is not configured with an API key.', 'oopspam-anti-spam' ) );
	}

	$content = isset( $input['content'] ) ? sanitize_textarea_field( (string) $input['content'] ) : '';
	$ip      = isset( $input['ip'] ) ? trim( (string) $input['ip'] ) : '';
	$email   = isset( $input['email'] ) ? trim( (string) $input['email'] ) : '';
	$is_spam = ! empty( $input['is_spam'] );

	if ( '' !== $email && ! is_email( $email ) ) {
		return new WP_Error( 'oopspam_invalid_email', __( 'The provided email address is not valid.', 'oopspam-anti-spam' ) );
	}

	if ( '' !== $ip && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return new WP_Error( 'oopspam_invalid_ip', __( 'The provided IP address is not valid.', 'oopspam-anti-spam' ) );
	}

	$metadata = '';
	if ( isset( $input['metadata'] ) && is_array( $input['metadata'] ) ) {
		$metadata = wp_json_encode( $input['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	$result = oopspamantispam_report_OOPSpam( $content, $ip, $email, $is_spam, $metadata );

	if ( false === $result || ! is_string( $result ) ) {
		return new WP_Error( 'oopspam_report_failed', __( 'Unable to report to the OOPSpam API.', 'oopspam-anti-spam' ) );
	}

	return array(
		'success' => true,
		'message' => $result,
	);
}

/**
 * Shared executor for email moderation lists (blocked/allowed emails).
 *
 * @param array  $input     Validated input.
 * @param string $option    Option key: mm_blocked_emails|mm_allowed_emails.
 * @param string $action    'add'|'remove'.
 * @return array|\WP_Error
 */
function oopspam_ability_moderate_email( $input, $option, $action ) {
	$value = isset( $input['email'] ) ? strtolower( trim( (string) $input['email'] ) ) : '';

	if ( ! oopspam_validate_blocklist_email( $value ) ) {
		return new WP_Error(
			'oopspam_invalid_email',
			__( 'Invalid email. Use an exact address or a local-part wildcard such as *@example.com.', 'oopspam-anti-spam' )
		);
	}

	$fn   = ( 'remove' === $action ) ? 'oopspam_remove_manual_moderation_entry' : 'oopspam_add_manual_moderation_entry';
	$ok   = function_exists( $fn ) ? call_user_func( $fn, $option, $value, true ) : false;
	$done = (bool) $ok;

	return array(
		'success' => $done,
		'already' => ( 'add' === $action && ! $done ),
		'message' => $done
			? ( ( 'remove' === $action )
				? sprintf( __( 'Email %s was removed from the list.', 'oopspam-anti-spam' ), $value )
				: sprintf( __( 'Email %s was added to the list.', 'oopspam-anti-spam' ), $value ) )
			: ( ( 'add' === $action )
				? sprintf( __( 'Email %s was already on the list.', 'oopspam-anti-spam' ), $value )
				: sprintf( __( 'Email %s was not found on the list.', 'oopspam-anti-spam' ), $value ) ),
	);
}

/**
 * Shared executor for IP moderation lists (blocked/allowed IPs).
 *
 * @param array  $input  Validated input.
 * @param string $option Option key: mm_blocked_ips|mm_allowed_ips.
 * @param string $action 'add'|'remove'.
 * @return array|\WP_Error
 */
function oopspam_ability_moderate_ip( $input, $option, $action ) {
	$value = isset( $input['ip'] ) ? trim( (string) $input['ip'] ) : '';

	if ( ! oopspam_validate_blocklist_ip( $value ) ) {
		return new WP_Error(
			'oopspam_invalid_ip',
			__( 'Invalid IP. Provide a single IP, a CIDR block (e.g. 192.168.1.0/24), or a range.', 'oopspam-anti-spam' )
		);
	}

	$fn   = ( 'remove' === $action ) ? 'oopspam_remove_manual_moderation_entry' : 'oopspam_add_manual_moderation_entry';
	$ok   = function_exists( $fn ) ? call_user_func( $fn, $option, $value, false ) : false;
	$done = (bool) $ok;

	return array(
		'success' => $done,
		'already' => ( 'add' === $action && ! $done ),
		'message' => $done
			? ( ( 'remove' === $action )
				? sprintf( __( 'IP %s was removed from the list.', 'oopspam-anti-spam' ), $value )
				: sprintf( __( 'IP %s was added to the list.', 'oopspam-anti-spam' ), $value ) )
			: ( ( 'add' === $action )
				? sprintf( __( 'IP %s was already on the list.', 'oopspam-anti-spam' ), $value )
				: sprintf( __( 'IP %s was not found on the list.', 'oopspam-anti-spam' ), $value ) ),
	);
}

/**
 * Register the OOPSpam ability category.
 *
 * @return void
 */
function oopspam_register_ability_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'oopspam',
		array(
			'label'       => __( 'OOPSpam Anti-Spam', 'oopspam-anti-spam' ),
			'description' => __( 'Spam detection and moderation abilities provided by the OOPSpam Anti-Spam plugin.', 'oopspam-anti-spam' ),
		)
	);
}

/**
 * Register all OOPSpam abilities.
 *
 * @return void
 */
function oopspam_register_oopspam_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	// Curated tool registry. Each entry maps 1:1 to a wp_register_ability() call.
	$tools = array(
		'oopspam/status' => array(
			'label'             => __( 'OOPSpam Status', 'oopspam-anti-spam' ),
			'description'       => __( 'Returns the OOPSpam configuration and health snapshot: whether it is configured, the (masked) API key, spam score threshold, and which protections are active.', 'oopspam-anti-spam' ),
			'input_schema'      => array( 'type' => 'object', 'properties' => array() ),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'configured'            => array( 'type' => 'boolean' ),
					'api_key_set'           => array( 'type' => 'boolean' ),
					'api_key_masked'        => array( 'type' => 'string' ),
					'api_key_source'        => array( 'type' => 'string' ),
					'spam_score_threshold'  => array( 'type' => 'integer' ),
					'rate_limiting_enabled' => array( 'type' => 'boolean' ),
					'woocommerce_protection'=> array( 'type' => 'boolean' ),
					'wordpress_version'     => array( 'type' => 'string' ),
					'plugin_version'        => array( 'type' => 'string' ),
				),
			),
			'execute_callback'  => 'oopspam_ability_status',
			'annotations'       => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
		),

		'oopspam/check-submission' => array(
			'label'             => __( 'Check Submission', 'oopspam-anti-spam' ),
			'description'       => __( 'Analyzes content, an IP, and an email through the full OOPSpam pipeline (local moderation lists, rate limiting, country/language filters, and the OOPSpam API) and returns whether it is spam with a score and reason. Does not log results unless log is set to true.', 'oopspam-anti-spam' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'content' => array( 'type' => 'string', 'description' => __( 'The message or form content to check.', 'oopspam-anti-spam' ) ),
					'ip'      => array( 'type' => 'string', 'description' => __( 'Submitter IP address. Optional - leave empty to skip IP-based checks.', 'oopspam-anti-spam' ) ),
					'email'   => array( 'type' => 'string', 'description' => __( 'Submitter email address. Optional.', 'oopspam-anti-spam' ) ),
					'log'     => array( 'type' => 'boolean', 'default' => false, 'description' => __( 'Whether to store the verdict in the Spam/Ham entries log. Default false.', 'oopspam-anti-spam' ) ),
					'form_id' => array( 'type' => 'string', 'description' => __( 'Form identifier, used only when log is true.', 'oopspam-anti-spam' ) ),
				),
				'required' => array( 'content' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'is_spam' => array( 'type' => 'boolean' ),
					'score'   => array( 'type' => 'integer' ),
					'reason'  => array( 'type' => 'string' ),
				),
				'required' => array( 'is_spam', 'score' ),
			),
			'execute_callback'  => 'oopspam_ability_check_submission',
			'annotations'       => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		),

		'oopspam/get-stats' => array(
			'label'             => __( 'Get Spam Stats', 'oopspam-anti-spam' ),
			'description'       => __( 'Returns the number of spam and ham (false positive) entries tracked by OOPSpam, both all-time and today.', 'oopspam-anti-spam' ),
			'input_schema'      => array( 'type' => 'object', 'properties' => array() ),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'spam_total' => array( 'type' => 'integer' ),
					'ham_total'  => array( 'type' => 'integer' ),
					'spam_today' => array( 'type' => 'integer' ),
					'ham_today'  => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'  => 'oopspam_ability_get_stats',
			'annotations'       => array( 'readonly' => true, 'destructive' => false, 'idempotent' => false ),
		),

		'oopspam/list-recent-spam' => array(
			'label'             => __( 'List Recent Spam', 'oopspam-anti-spam' ),
			'description'       => __( 'Returns the most recent entries OOPSpam blocked, with the reason, score, email, IP, and form that triggered them.', 'oopspam-anti-spam' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20, 'description' => __( 'Maximum number of entries to return (1-100).', 'oopspam-anti-spam' ) ),
					'search' => array( 'type' => 'string', 'description' => __( 'Optional filter by email, IP, reason, or message.', 'oopspam-anti-spam' ) ),
				),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'total'   => array( 'type' => 'integer' ),
					'entries' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'additionalProperties' => true ) ),
				),
				'required' => array( 'total', 'entries' ),
			),
			'execute_callback'  => 'oopspam_ability_list_recent_spam',
			'annotations'       => array( 'readonly' => true, 'destructive' => false, 'idempotent' => false ),
		),

		'oopspam/list-moderation-lists' => array(
			'label'             => __( 'List Moderation Lists', 'oopspam-anti-spam' ),
			'description'       => __( 'Returns the current manual moderation lists: blocked and allowed emails, IPs, and blocked keywords and phrases.', 'oopspam-anti-spam' ),
			'input_schema'      => array( 'type' => 'object', 'properties' => array() ),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'blocked_emails'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'allowed_emails'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'blocked_ips'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'allowed_ips'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'blocked_keywords' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'execute_callback'  => 'oopspam_ability_list_moderation_lists',
			'annotations'       => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
		),

		'oopspam/report-submission' => array(
			'label'             => __( 'Report Submission', 'oopspam-anti-spam' ),
			'description'       => __( 'Sends feedback to the OOPSpam API classifying a submission as spam or ham. Use this to report false positives or missed spam and help improve detection. This only reports; it does not modify moderation lists.', 'oopspam-anti-spam' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'content'  => array( 'type' => 'string', 'description' => __( 'The original message content.', 'oopspam-anti-spam' ) ),
					'ip'       => array( 'type' => 'string', 'description' => __( 'The submitter IP address.', 'oopspam-anti-spam' ) ),
					'email'    => array( 'type' => 'string', 'description' => __( 'The submitter email address.', 'oopspam-anti-spam' ) ),
					'is_spam'  => array( 'type' => 'boolean', 'description' => __( 'true to report as spam, false to report as ham (not spam).', 'oopspam-anti-spam' ) ),
					'metadata' => array( 'type' => 'object', 'additionalProperties' => true, 'description' => __( 'Optional extra context (e.g. form_id, page URL).', 'oopspam-anti-spam' ) ),
				),
				'required' => array( 'is_spam' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
				'required' => array( 'success' ),
			),
			'execute_callback'  => 'oopspam_ability_report_submission',
			'annotations'       => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		),
	);

	// Email / IP moderation tools (blocked & allowed lists, add & remove).
	$moderation = array(
		'block-email'  => array( __( 'Block Email', 'oopspam-anti-spam' ), __( 'Adds an email address (or wildcard like *@example.com) to the OOPSpam blocked list.', 'oopspam-anti-spam' ), 'email', 'mm_blocked_emails', 'add' ),
		'unblock-email'=> array( __( 'Unblock Email', 'oopspam-anti-spam' ), __( 'Removes an email address (or wildcard) from the OOPSpam blocked list.', 'oopspam-anti-spam' ), 'email', 'mm_blocked_emails', 'remove' ),
		'allow-email'  => array( __( 'Allow Email', 'oopspam-anti-spam' ), __( 'Adds an email address (or wildcard) to the OOPSpam allowed list so it is never flagged.', 'oopspam-anti-spam' ), 'email', 'mm_allowed_emails', 'add' ),
		'unallow-email'=> array( __( 'Unallow Email', 'oopspam-anti-spam' ), __( 'Removes an email address (or wildcard) from the OOPSpam allowed list.', 'oopspam-anti-spam' ), 'email', 'mm_allowed_emails', 'remove' ),
		'block-ip'     => array( __( 'Block IP', 'oopspam-anti-spam' ), __( 'Adds an IP, CIDR block, or IP range to the OOPSpam blocked list.', 'oopspam-anti-spam' ), 'ip', 'mm_blocked_ips', 'add' ),
		'unblock-ip'   => array( __( 'Unblock IP', 'oopspam-anti-spam' ), __( 'Removes an IP, CIDR block, or IP range from the OOPSpam blocked list.', 'oopspam-anti-spam' ), 'ip', 'mm_blocked_ips', 'remove' ),
		'allow-ip'     => array( __( 'Allow IP', 'oopspam-anti-spam' ), __( 'Adds an IP, CIDR block, or IP range to the OOPSpam allowed list.', 'oopspam-anti-spam' ), 'ip', 'mm_allowed_ips', 'add' ),
		'unallow-ip'   => array( __( 'Unallow IP', 'oopspam-anti-spam' ), __( 'Removes an IP, CIDR block, or IP range from the OOPSpam allowed list.', 'oopspam-anti-spam' ), 'ip', 'mm_allowed_ips', 'remove' ),
	);

	foreach ( $moderation as $slug => $config ) {
		list( $label, $description, $type, $option, $action ) = $config;

		$prop_key = ( 'email' === $type ) ? 'email' : 'ip';
		$prop     = array( 'type' => 'string' );
		if ( 'email' === $type ) {
			$prop['description'] = __( 'Email address or wildcard pattern (e.g. *@example.com).', 'oopspam-anti-spam' );
		} else {
			$prop['description'] = __( 'IP address, CIDR block, or range.', 'oopspam-anti-spam' );
		}

		$tools[ 'oopspam/' . $slug ] = array(
			'label'            => $label,
			'description'      => $description,
			'input_schema'     => array(
				'type'       => 'object',
				'properties' => array( $prop_key => $prop ),
				'required'   => array( $prop_key ),
			),
			'output_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'already' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
				'required' => array( 'success', 'message' ),
			),
			'execute_callback' => ( 'email' === $type )
				? function ( $input ) use ( $option, $action ) {
					return oopspam_ability_moderate_email( $input, $option, $action );
				}
				: function ( $input ) use ( $option, $action ) {
					return oopspam_ability_moderate_ip( $input, $option, $action );
				},
			'annotations'      => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		);
	}

	foreach ( $tools as $name => $tool ) {
		wp_register_ability(
			$name,
			array(
				'label'               => $tool['label'],
				'description'         => $tool['description'],
				'category'            => 'oopspam',
				'input_schema'        => $tool['input_schema'],
				'output_schema'       => $tool['output_schema'],
				'execute_callback'    => $tool['execute_callback'],
				'permission_callback' => oopspam_ability_permission_callback( $name ),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'annotations'  => $tool['annotations'],
				),
			)
		);
	}
}

// Wire up registration only when the Abilities API exists AND the site opted in.
if ( function_exists( 'wp_register_ability' ) && oopspam_are_abilities_enabled() ) {
	add_action( 'wp_abilities_api_categories_init', 'oopspam_register_ability_category' );
	add_action( 'wp_abilities_api_init', 'oopspam_register_oopspam_abilities' );
}
