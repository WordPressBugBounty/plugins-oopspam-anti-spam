<?php

namespace OOPSPAM\UI;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


function empty_ham_entries(){

	 if ( ! is_user_logged_in() ) {
        wp_send_json_error( array(
            'error'   => true,
            'message' => 'Access denied.',
        ), 403 );
    }

	// Verify the nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if ( ! wp_verify_nonce( $nonce, 'empty_ham_entries_nonce' ) ) {
        wp_send_json_error( array(
            'error'   => true,
            'message' => 'CSRF verification failed.',
        ), 403 );
    }

    global $wpdb; 
    $table = $wpdb->prefix . 'oopspam_frm_ham_entries';

	$action_type = isset($_POST['action_type']) ? $_POST['action_type'] : '';
    if ($action_type === "empty-entries") {
        $wpdb->query("TRUNCATE TABLE " . esc_sql($table));
        wp_send_json_success( array( 
            'success' => true
        ), 200 );
    }

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_empty_ham_entries', 'OOPSPAM\UI\empty_ham_entries' ); // executed when logged in

function export_ham_entries(){

    try {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                'error'   => true,
                'message' => 'Access denied.',
            ), 403 );
        }
    
        // Verify the nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'export_ham_entries_nonce' ) ) {
            wp_send_json_error( array(
                'error'   => true,
                'message' => 'CSRF verification failed.',
            ), 403 );
        }
        
        global $wpdb; 
        $table = $wpdb->prefix . 'oopspam_frm_ham_entries';
        
        // Get column names securely
        $column_names = $wpdb->get_col($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table
        ));

        // Get rows securely
        $rows = $wpdb->get_results(
            "SELECT * FROM " . esc_sql($table), ARRAY_A);

        // Filter out columns to ignore (e.g., 'id')
        $columns_to_ignore = array('id', 'reported');
        $filtered_column_names = array_diff($column_names, $columns_to_ignore);

        // Create CSV content
        $csv_output = fopen('php://temp/maxmemory:'. (5*1024*1024), 'w');
        if ($csv_output === FALSE) {
            die('Failed to open temporary file');
        }

        // Write the filtered column names as the header row
        fputcsv($csv_output, $filtered_column_names);

        if (!empty($rows)) {
            foreach ($rows as $record) {
                // Prepare the output record based on the filtered column names
                $output_record = array();
                foreach ($filtered_column_names as $column) {
                    // Check if the column exists in the record
                    if (isset($record[$column])) {
                        $output_record[] = $record[$column];
                    } else {
                        $output_record[] = ''; // If column does not exist, use empty string
                    }
                }
                fputcsv($csv_output, $output_record);
            }
        }

        fseek($csv_output, 0);
		$filename = 'ham_entries_export_' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Output CSV content
        while (!feof($csv_output)) {
            echo fread($csv_output, 8192);
        }

        fclose($csv_output);
        exit;

    } catch (Exception $e) {
        // Handle the exception
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('export_ham_entries: ' . $e->getMessage());
        }
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_export_ham_entries', 'OOPSPAM\UI\export_ham_entries' ); // executed when logged in

class Ham_Entries extends \WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Entry',  'oopspam-anti-spam' ), //singular name of the listed records
			'plural'   => __( 'Entries',  'oopspam-anti-spam' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}

	/**
	 * Retrieve ham entries data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_ham_entries($per_page = 5, $page_number = 1, $search = "") {
		global $wpdb;
		
		// Validate and sanitize input parameters
		$per_page = absint($per_page);
		$page_number = absint($page_number);
		$search = sanitize_text_field($search);
		
		$table = $wpdb->prefix . 'oopspam_frm_ham_entries';
		
		 // Start building the query
		 $where = array();
		 $values = array();
		 
		 // Add search condition if search term is provided
		 if (!empty($search)) {
			 // Use separate placeholders for each LIKE condition
			 $search_term = '%' . $wpdb->esc_like($search) . '%';
			 $where[] = "(form_id LIKE %s OR message LIKE %s OR ip LIKE %s OR email LIKE %s OR raw_entry LIKE %s)";
			 $values = array_merge($values, array_fill(0, 5, $search_term));
		 }
	 
		 // Combine WHERE clauses
		 $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
	 
		 // Add ordering (already sanitized with sanitize_sql_orderby)
		 $orderby = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'date';
		 $order = !empty($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'DESC';
		 
		 // Calculate offset
		 $offset = ($page_number - 1) * $per_page;
	 
		 // Build the complete SQL query
		 $sql = "SELECT * FROM " . esc_sql($table) . " $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
		 
		 // Prepare the query with parameters
		 $query = $wpdb->prepare(
			 $sql,
			 array_merge(
				 $values,
				 array($per_page, $offset)
			 )
		 );
	 
		 return $wpdb->get_results($query, 'ARRAY_A');
	}


	/**
	 * Delete a ham entry.
	 *
	 * @param int $id entry ID
	 */
	public static function delete_ham_entry( $id ) {
		global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_ham_entries';

		$wpdb->delete(
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/**
	 * Report a ham entry as spam
	 *
	 * @param int $id entry ID
	 */
	public static function report_ham_entry( $id ) {
		global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_ham_entries';

		$spamEntry = $wpdb->get_row(
			$wpdb->prepare(
				"
					SELECT message, ip, email, raw_entry
					FROM $table
					WHERE id = %s
				",
				$id
			)
		);

		// Pass raw_entry as metadata for fraud detection analysis
		$metadata = isset($spamEntry->raw_entry) ? $spamEntry->raw_entry : '';
		$submitReport  = oopspamantispam_report_OOPSpam($spamEntry->message, $spamEntry->ip, $spamEntry->email, true, $metadata);

		if ($submitReport === "success") {
			$wpdb->update( 
				$table, 
				array(
					'reported' => true
				), 
				array( 'ID' => $id ), 
				array( 
					'%d' 
				), 
				array( '%d' ) 
			);

			 // Get the current settings
			$manual_moderation_settings = get_option('manual_moderation_settings', array());

			// Add email to blocked emails if it doesn't already exist
			if (isset($spamEntry->email) && !empty($spamEntry->email)) {
				$blocked_emails = isset($manual_moderation_settings['mm_blocked_emails']) ? $manual_moderation_settings['mm_blocked_emails'] : '';
				$email_list = array_map('trim', explode("\n", $blocked_emails));
				if (!in_array($spamEntry->email, $email_list)) {
					$email_list[] = $spamEntry->email;
					$manual_moderation_settings['mm_blocked_emails'] = implode("\n", $email_list);
				}
			}

			// Add IP to blocked IPs if it doesn't already exist
			if (isset($spamEntry->ip) && !empty($spamEntry->ip)) {
				$blocked_ips = isset($manual_moderation_settings['mm_blocked_ips']) ? $manual_moderation_settings['mm_blocked_ips'] : '';
				$ip_list = array_map('trim', explode("\n", $blocked_ips));
				if (!in_array($spamEntry->ip, $ip_list)) {
					$ip_list[] = $spamEntry->ip;
					$manual_moderation_settings['mm_blocked_ips'] = implode("\n", $ip_list);
				}
			}

			// Update the settings only if changes were made
			if (isset($manual_moderation_settings['mm_blocked_emails']) || isset($manual_moderation_settings['mm_blocked_ips'])) {
				update_option('manual_moderation_settings', $manual_moderation_settings);
			}
		}
	}

	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'oopspam_frm_ham_entries';
		
		$where = array();
		$values = array();
		
		$sql = "SELECT COUNT(*) FROM " . esc_sql($table);
		
		// Add search condition if search term is provided
		if (!empty($_REQUEST['s'])) {
			$search = sanitize_text_field($_REQUEST['s']);
			$search_term = '%' . $wpdb->esc_like($search) . '%';
			$where[] = "(form_id LIKE %s OR message LIKE %s OR ip LIKE %s OR email LIKE %s OR raw_entry LIKE %s)";
			$values = array_merge($values, array_fill(0, 5, $search_term));
		}
		
		// Combine WHERE clauses
		if (!empty($where)) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}
		
		// Use prepare only if there are values to prepare
		if (!empty($values)) {
			return $wpdb->get_var($wpdb->prepare($sql, $values));
		} else {
			return $wpdb->get_var($sql);
		}
	}


	/** Text displayed when no ham entry is available */
	public function no_items() {
		esc_html_e( 'No ham entries available.', 'oopspam-anti-spam' );
	}

	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'reported':
			case 'message':
			case 'ip':
			case 'email':
            case 'raw_entry':
            case 'form_id':
            case 'date':
				return $item[ $column_name ];
			case 'score':
				return $this->column_score($item);
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	function column_score( $item ) {
        $score = intval($item['score']);
        
        // Score meanings:
        // -1: Rate limit reached
        // -2: Generic API/connection error
        // -3: API key disabled
        // -4: API key invalid
        // -5: API key missing
        // -6: Connection timeout
        // -7: Server error (5xx)
        // -8: Bad request (400)
        // -9: Unauthorized (401)
        // -10: Not found (404)
        
        $error_labels = array(
            -1 => 'Rate limit reached',
            -2 => 'Connection error',
            -3 => 'API key disabled',
            -4 => 'Invalid API key',
            -5 => 'API key missing',
            -6 => 'Connection timeout',
            -7 => 'Server error',
            -8 => 'Bad request',
            -9 => 'Unauthorized',
            -10 => 'API endpoint not found'
        );
        
        if ($score === -1) {
            return '<span title="This entry was automatically allowed because the API rate limit was reached" style="color: #856404; background-color: #fff3cd; padding: 3px 8px; border-radius: 3px;">Rate Limited</span>';
        } else if ($score < 0) {
            $error_label = isset($error_labels[$score]) ? $error_labels[$score] : 'Unknown error';
            return '<span title="This entry was automatically allowed due to an API error" style="color: #721c24; background-color: #f8d7da; padding: 3px 8px; border-radius: 3px; cursor: help;">API Error: ' . esc_html($error_label) . '</span>';
        }
        
        return esc_html($score);
    }

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
		);
	}
	

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_message( $item ) {

		$delete_nonce = wp_create_nonce( 'sp_delete_ham' );
		$report_nonce = wp_create_nonce( 'sp_report_ham' );

		 // Check if message is null and provide default value
		$message = isset($item['message']) ? $item['message'] : '';
		
		// Limit the message to 80 characters
		$truncated_message = substr($message, 0, 80);
		if (strlen($message) > 80) {
			$truncated_message .= '...';
		}

		$title = '<span title="' . esc_attr($message) . '">' . esc_html($truncated_message) . '</span>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&ham=%s&_wpnonce=%s">Delete</a>', sanitize_text_field( $_GET['page'] ), 'delete', absint( $item['id'] ), $delete_nonce ),
			'report' => sprintf( '<a style="color:#996800; %s" href="?page=%s&action=%s&ham=%s&_wpnonce=%s">Report as spam</a>', ($item['reported'] === '1' ? 'color: grey !important;pointer-events: none;
			cursor: default; opacity: 0.5;' : ''), sanitize_text_field( $_GET['page'] ), 'report', absint( $item['id'] ), $report_nonce )

		];

		return $title . $this->row_actions( $actions );
	}

	function column_raw_entry( $item ) {
		add_thickbox();
		// Check if raw_entry is null and provide default value
		$raw_entry = isset($item['raw_entry']) ? $item['raw_entry'] : '';
		$short_raw_entry = substr($raw_entry, 0, 50);
		$json_string = $this->json_print($raw_entry);
		$dialog_id = 'my-raw-entry-' . $item['id'];
		$actions = [
			'seemore' => sprintf(
				'<div id=%s style="display:none;">
					<p>%s</p>
				</div><a href="#TB_inline?&width=600&height=550&inlineId=%s" class="thickbox">see more</a>',
				$dialog_id,
				wp_kses_post( $json_string ), // Perform HTML encoding
				$dialog_id
			)
		];
		return esc_html( $short_raw_entry ) . $this->row_actions( $actions ); // Perform HTML encoding
	}

	function column_reported( $item ) {
        if ($item['reported'] === '1') {
			return '<span style="color:#996800;">Reported as spam</span>';
		}
		return '';
	}

    function column_ip($item) {
        $ip = esc_html($item['ip']);
        $country = $this->get_country_by_ip($ip);
        return $ip . '<br>' . $country;
    }

    private function get_country_by_ip($ip) {

		if (empty($ip)) {
			return '';
		}
        // Ignore local IPs
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local IP';
        }

        $args = array(
            'timeout' => 5,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => true
        );

        $response = wp_remote_get("https://reallyfreegeoip.org/json/{$ip}", $args);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('IP Geolocation Error: ' . $response->get_error_message());
            }
            return '';
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('IP Geolocation Error: Non-200 response code');
            }
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return '';
        }

        $data = json_decode($body, true);
        if (isset($data['country_code'])) {
            $country_code = strtolower($data['country_code']);
            $countries = oopspam_get_isocountries();
            return isset($countries[$country_code]) ? $countries[$country_code] : 'Unknown';
        }

        return '';
    }

    /**
	 *  Prettify JSON
	 *
	 * @return array
	 */
    function json_print($json) { 
		// Check if json is null or empty
		if ($json === null || $json === '') {
			return '<pre style="white-space: pre-wrap;">No data</pre>';
		}
		return '<pre style="white-space: pre-wrap;       /* Since CSS 2.1 */
        white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
        white-space: -pre-wrap;      /* Opera 4-6 */
        white-space: -o-pre-wrap;    /* Opera 7 */
        word-wrap: break-word;       /* Internet Explorer 5.5+ */">' . json_encode(json_decode($json), JSON_PRETTY_PRINT) . '</pre>'; 
	}
 
	
	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'      => '<input type="checkbox" />',
			'reported'    => __( 'Status',  'oopspam-anti-spam' ),
			'message'    => __( 'Message',  'oopspam-anti-spam' ),
			'ip' => __( 'IP',  'oopspam-anti-spam' ),
			'email' => __( 'Email',  'oopspam-anti-spam' ),
			'score'    => __( 'Score',  'oopspam-anti-spam' ),
            'form_id'    => __( 'Form Id',  'oopspam-anti-spam' ),
            'raw_entry'    => __( 'Raw fields',  'oopspam-anti-spam' ),
            'date'    => __( 'Date',  'oopspam-anti-spam' )
		];

		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'date' => array( 'date', true ),
			'reported' => array( 'reported', false ),
            'score' => array( 'score', false ),
            'form_id' => array( 'form_id', false ),
            'ip' => array( 'ip', false ),
			'email' => array( 'email', false )
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete',
			'bulk-report' => 'Report as Spam'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();

		// Handle individual actions
        $action = $this->current_action();
        if (isset($_GET['ham']) && isset($_GET['_wpnonce'])) {
            $entry_id = absint($_GET['ham']);
            
            switch($action) {
                case 'report':
                    if (wp_verify_nonce($_GET['_wpnonce'], 'sp_report_ham')) {
                        self::report_ham_entry($entry_id);
                        wp_safe_redirect(remove_query_arg(['action', 'ham', '_wpnonce']));
                        exit;
                    }
                    break;
                    
                case 'delete':
                    if (wp_verify_nonce($_GET['_wpnonce'], 'sp_delete_ham')) {
                        self::delete_ham_entry($entry_id);
                        wp_safe_redirect(remove_query_arg(['action', 'ham', '_wpnonce']));
                        exit;
                    }
                    break;
            }
        }

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page = $this->get_items_per_page('entries_per_page', 10);
		$current_page = $this->get_pagenum();
		
		// Get search query from either POST or GET
		$search = '';
		if (!empty($_REQUEST['s'])) {
			$search = sanitize_text_field($_REQUEST['s']);
			// Limit search term length to prevent excessive long queries
			$search = substr($search, 0, 100);
		}
		
		$total_items = self::record_count();
		
		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page
		]);

		$this->items = self::get_ham_entries($per_page, $current_page, $search);
	}

	public function process_bulk_action() {
    // Security check
    if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce'])) {
        $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_UNSAFE_RAW);
        $nonce = sanitize_text_field($nonce);
        if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
            wp_die('Security check failed!');
        }
    }

    $action = $this->current_action();
    if (in_array($action, ['bulk-delete', 'bulk-report'])) {
        $entry_ids = isset($_POST['bulk-delete']) ? array_map('absint', $_POST['bulk-delete']) : array();
        if (!empty($entry_ids)) {
            // Add JavaScript for async processing
            add_action('admin_footer', function() use ($entry_ids, $action) {
                ?>
                <style>
                    .oopspam-progress {
                        background: #f0f0f1;
                        border: 1px solid #c3c4c7;
                        padding: 10px;
                        margin: 10px 0;
                        border-radius: 4px;
                        display: none;
                    }
                    .oopspam-progress.active {
                        display: block;
                    }
                </style>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    let remainingIds = <?php echo json_encode($entry_ids); ?>;
                    let processed = 0;
                    let total = remainingIds.length;
                    
                    // Add progress div after the description and before the table
                    $('<div id="oopspam-progress" class="oopspam-progress"><div class="progress-text"></div></div>')
                        .insertBefore('.wp-list-table');
                    
                    let $progress = $('#oopspam-progress');
                    let $progressText = $progress.find('.progress-text');
                    
                    function updateProgress(processed, total) {
                        $progress.addClass('active');
                        $progressText.html('Processing: ' + processed + ' of ' + total + ' entries... (' + Math.round((processed/total) * 100) + '%)');
                    }
                    
                    function processNextEntry() {
                        updateProgress(processed, total);
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'process_bulk_entries',
                                nonce: '<?php echo wp_create_nonce('bulk-entries'); ?>',
                                entry_ids: remainingIds,
                                bulk_action: '<?php echo $action; ?>',
                                entry_type: 'ham'
                            },
                            success: function(response) {
                                if (response.success) {
                                    processed++;
                                    remainingIds = response.data.remaining;
                                    
                                    if (response.data.complete) {
                                        $progressText.html('Processing complete! Reloading page...');
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1000);
                                    } else {
                                        processNextEntry();
                                    }
                                } else {
                                    $progressText.html('Error occurred during processing.');
                                }
                            },
                            error: function() {
                                $progressText.html('Error occurred during processing.');
                            }
                        });
                    }
                    
                    processNextEntry();
                });
                </script>
                <?php
            });
        }
    }
}

}


class OOPSpam_Ham {

	// class instance
	static $instance;

	// ham entries WP_List_Table object
	public $entries_obj;

	// class constructor
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', array($this, 'plugin_menu') );
	}


	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {

        $hook =  add_submenu_page(
            'wp_oopspam_settings_page',
            __('Valid Entries', "oopspam-anti-spam"),
            __('Valid Entries', "oopspam-anti-spam"),
            'edit_pages',
            'wp_oopspam_frm_ham_entries',
            [ $this, 'plugin_settings_page' ] );

        add_action( "load-$hook", [ $this, 'screen_option' ] );
	}


	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
		?>
		<div class="oopspam-wrap">
			
            <div style="display:flex; flex-direction:row; align-items:center; justify-content:flex-start;">
				<h2 style="padding-right:0.5em;"><?php esc_html_e("Valid Entries", "oopspam-anti-spam"); ?></h2>
				<input type="button" id="empty-ham-entries" style="margin-right:0.5em;" class="button action" value="<?php esc_attr_e("Empty the table", "oopspam-anti-spam"); ?>">
				<input type="button" id="export-ham-entries" class="button action" value="<?php esc_attr_e("Export CSV", "oopspam-anti-spam"); ?>">
            </div>
			<div>
				<p><?php esc_html_e("All submissions are stored locally in your WordPress database.", "oopspam-anti-spam"); ?></p>
				<p><?php esc_html_e("In the below table you can view, delete, and report approved entries.", "oopspam-anti-spam"); ?></p>
				<p><?php esc_html_e("If you believe any of these SHOULD be flagged as spam, please follow these steps to report them to us. This will improve spam detection for your use case.", "oopspam-anti-spam"); ?> </p>
				<ul>
					<li><?php esc_html_e("1. Hover on an entry", "oopspam-anti-spam"); ?></li>
					<li><?php echo wp_kses(__('2. Click the <span style="color:#996800;">"Report as spam"</span> link', 'oopspam-anti-spam'), array('span' => array('style' => array()))); ?></li>
					<li><?php echo wp_kses(__('3. Page will be refreshed and Status (first column) will display  <span style="color:#996800;">"Reported as spam"</span>', 'oopspam-anti-spam'), array('span' => array('style' => array()))); ?></li>
				</ul>
			</div>
			<div id="entries">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php 
								$this->entries_obj->prepare_items();
								$this->entries_obj->search_box('Search Entries', 'search_id');
								wp_nonce_field('bulk-' . $this->entries_obj->_args['plural']);
								$this->entries_obj->display(); 
								?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
	<?php
	}

	/**
	 * Screen options
	 */
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Ham Entries',
			'default' => 10,
			'option'  => 'entries_per_page'
		];

		add_screen_option( $option, $args );

		$this->entries_obj = new Ham_Entries();
	}


	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}


add_action( 'plugins_loaded', function () {
	OOPSpam_Ham::get_instance();
} );