<?php

namespace OOPSPAM\UI;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


function empty_spam_entries(){

	 try {
		if ( ! is_user_logged_in() ) {
	        wp_send_json_error( array(
	            'error'   => true,
	            'message' => 'Access denied.',
	        ), 403 );
	    }
	
		// Verify the nonce
	    $nonce = $_POST['nonce'];
	    if ( ! wp_verify_nonce( $nonce, 'empty_spam_entries_nonce' ) ) {
	        wp_send_json_error( array(
	            'error'   => true,
	            'message' => 'CSRF verification failed.',
	        ), 403 );
	    }
	
	    global $wpdb; 
	    $table = $wpdb->prefix . 'oopspam_frm_spam_entries';
	
		$action_type = $_POST['action_type'];
	    if ($action_type === "empty-entries") {
	        $wpdb->query("TRUNCATE TABLE $table");
	        wp_send_json_success( array( 
	            'success' => true
	        ), 200 );
	    }
	 } catch (Exception $e) {
        // Handle the exception
        error_log('empty_spam_entries: ' . $e->getMessage());
	 }

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_empty_spam_entries', 'OOPSPAM\UI\empty_spam_entries' ); // executed when logged in

function export_spam_entries(){

    try {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                'error'   => true,
                'message' => 'Access denied.',
            ), 403 );
        }
    
        // Verify the nonce
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'export_spam_entries_nonce' ) ) {
            wp_send_json_error( array(
                'error'   => true,
                'message' => 'CSRF verification failed.',
            ), 403 );
        }
        
        global $wpdb; 
        $table = $wpdb->prefix . 'oopspam_frm_spam_entries';
        
        // Get column names securely
        $column_names = $wpdb->get_col($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table
        ));

        // Get rows securely
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i",
            $table
        ), ARRAY_A);

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
		$filename = 'spam_entries_export_' . date('Y-m-d_H-i') . '.csv';
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
        error_log('export_spam_entries: ' . $e->getMessage());
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}


add_action('wp_ajax_export_spam_entries', 'OOPSPAM\UI\export_spam_entries' ); // executed when logged in

class Spam_Entries extends \WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Entry', 'sp' ), //singular name of the listed records
			'plural'   => __( 'Entries', 'sp' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}


	/**
	 * Retrieve spam entries data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_spam_entries($per_page = 5, $page_number = 1, $search = "") {
		global $wpdb;
		$table = $wpdb->prefix . 'oopspam_frm_spam_entries';
		
		// Start building the query
		$where = array();
		$values = array();
		
		// Add search condition if search term is provided
		if (!empty($search)) {
			$search_term = '%' . $wpdb->esc_like($search) . '%';
			$where[] = "(form_id LIKE %s OR message LIKE %s OR ip LIKE %s OR email LIKE %s OR raw_entry LIKE %s)";
			$values = array_merge($values, array($search_term, $search_term, $search_term, $search_term, $search_term));
		}

		// Add reason filter if selected
		if (isset($_GET['filter_reason']) && !empty($_GET['filter_reason'])) {
			$where[] = "reason = %s";
			$values[] = sanitize_text_field($_GET['filter_reason']);
		}

		// Combine WHERE clauses
		$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

		// Add ordering
		$orderby = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'date';
		$order = !empty($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'DESC';
		
		// Calculate offset
		$offset = ($page_number - 1) * $per_page;

		// Prepare the complete query
		$query = $wpdb->prepare(
			"SELECT * FROM %i $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
			array_merge(
				array($table),
				$values,
				array($per_page, $offset)
			)
		);

		return $wpdb->get_results($query, 'ARRAY_A');
	}

	/**
	 * Get unique reasons for dropdown filter
	 */
	private function get_unique_reasons() {
		global $wpdb;
		$table = $wpdb->prefix . 'oopspam_frm_spam_entries';
		return $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT reason FROM %i WHERE reason IS NOT NULL AND reason != ''",
			$table
		));
	}

	/**
	 * Display the filter dropdown
	 */
	public function extra_tablenav($which) {
		if ($which === 'top') {
			$reasons = $this->get_unique_reasons();
			$current_reason = isset($_GET['filter_reason']) ? sanitize_text_field($_GET['filter_reason']) : '';
			?>
			<div class="alignleft actions">
				<select name="filter_reason">
					<option value=""><?php _e('All Reasons', 'sp'); ?></option>
					<?php foreach ($reasons as $reason): ?>
						<option value="<?php echo esc_attr($reason); ?>" <?php selected($current_reason, $reason); ?>>
							<?php echo esc_html($reason); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php submit_button(__('Filter', 'sp'), '', 'filter_action', false); ?>
			</div>
			<?php
		}
	}

	/**
	 * Delete a spam entry.
	 *
	 * @param int $id entry ID
	 */
	public static function delete_spam_entry( $id ) {
		global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_spam_entries';

		$wpdb->delete(
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

/**
 * Send an email notification with form submission details
 *
 * @param int $id entry ID
 */
public static function notify_spam_entry($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'oopspam_frm_spam_entries';
    $spamEntry = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT message, ip, email, raw_entry, date
            FROM $table
            WHERE id = %s",
            $id
        )
    );

    // Start building the email body
    $body = "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>";
    $body .= "<h2 style='color: #333;'>Form Submission Details</h2>";
    
    // Initialize sender email from database
    $sender_email = $spamEntry->email;
    
    // Process form fields
    if (!empty($spamEntry->raw_entry)) {
        $processed_fields = self::process_form_fields($spamEntry->raw_entry);
        
        if (!empty($processed_fields)) {
            $body .= "<table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
            $body .= "<tr style='background-color: #f5f5f5;'>
                        <th style='border: 1px solid #ddd; padding: 12px; text-align: left;'>Field</th>
                        <th style='border: 1px solid #ddd; padding: 12px; text-align: left;'>Value</th>
                    </tr>";
            
            foreach ($processed_fields as $field) {
                $formatted_value = self::format_field_value($field['value']);
                
                $body .= "<tr>
                            <td style='border: 1px solid #ddd; padding: 12px;'><strong>" . esc_html($field['name']) . "</strong></td>
                            <td style='border: 1px solid #ddd; padding: 12px;'>{$formatted_value}</td>
                        </tr>";
            }
            
            $body .= "</table>";
        }
    }
    
    // Add submission metadata
    $body .= "<div style='background-color: #f9f9f9; padding: 15px; margin-top: 20px; border-radius: 5px;'>";
    $body .= "<h3 style='color: #666; margin-top: 0;'>Submission Details</h3>";
    $body .= "<p style='margin: 5px 0;'><strong>IP Address:</strong> " . esc_html($spamEntry->ip) . "</p>";
    $body .= "<p style='margin: 5px 0;'><strong>Submission Time:</strong> " . esc_html($spamEntry->date) . "</p>";
    $body .= "</div>";
    
    $body .= "</div>";
    
    // Get the list of email addresses
    $to = get_option('oopspam_admin_emails');
    
    // If the option is empty, get the default admin email
    if (empty($to)) {
        $to = get_option('admin_email');
    }
    
    // Convert the email addresses to an array
    $to_array = is_string($to) ? explode(',', $to) : (array) $to;
    
    // Remove any invalid email addresses
    $to_array = array_filter($to_array, 'is_email');
    
    // Send emails
    if (!empty($to_array)) {
        $subject = "Form Submission Review Required - " . get_bloginfo('name');
        $sent_to = [];
        
        foreach ($to_array as $recipient) {
            $headers = [
                'From: ' . get_bloginfo('name') . ' <' . $recipient . '>',
                'Reply-To: ' . $sender_email,
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            $sent = wp_mail($recipient, $subject, $body, $headers);
            if ($sent) {
                $sent_to[] = $recipient;
            }
        }
        
        // Show success/failure message
        if (!empty($sent_to)) {
            $recipient_list = esc_js(implode(', ', $sent_to));
            echo "<script type='text/javascript'>alert('Notification sent to: " . $recipient_list . "');</script>";
        } else {
            echo "<script type='text/javascript'>alert('Failed to send notification.');</script>";
        }
    }
}

/**
 * Check if array is sequential (numeric keys) or associative
 */
private static function is_sequential_array($array) {
    if (!is_array($array)) {
        return false;
    }
    return array_keys($array) === range(0, count($array) - 1);
}

/**
 * Format field value for email display
 * 
 * @param mixed $value The field value to format
 * @return string Formatted value
 */
private static function format_field_value($value) {
    if (is_array($value)) {
        if (empty($value)) {
            return '-';
        }
        return implode(', ', array_map('esc_html', $value));
    }
    
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    
    if ($value === '' || $value === null) {
        return '-';
    }
    
    return esc_html($value);
}

/**
 * Prettify field name by converting various formats to readable text
 * 
 * @param string $field_name Raw field name
 * @return string Prettified field name
 */
private static function prettify_field_name($field_name) {
    // Remove common prefixes
    $field_name = preg_replace('/^(your[-_]|field[-_]|input[-_]|txt[-_]|frm[-_])/i', '', $field_name);
    
    // Convert snake_case and kebab-case to spaces
    $field_name = str_replace(['_', '-'], ' ', $field_name);
    
    // Handle special cases for common form fields
    $special_cases = [
        'wpcf7' => 'Contact Form',
        'fname' => 'First Name',
        'lname' => 'Last Name',
        'email' => 'Email Address',
        'tel' => 'Phone Number',
        'msg' => 'Message',
        'addr' => 'Address',
        'dob' => 'Date of Birth'
    ];
    
    foreach ($special_cases as $case => $replacement) {
        if (strcasecmp($field_name, $case) === 0) {
            return $replacement;
        }
    }
    
    // Capitalize first letter of each word
    $field_name = ucwords($field_name);
    
    // Clean up extra spaces
    return trim($field_name);
}

/**
 * Process form data for email display
 * 
 * @param mixed $raw_entry Form submission data
 * @return array Processed fields array
 */
private static function process_form_fields($raw_entry) {
    $processed_fields = [];
    
    // Handle different form submission formats
    if (is_string($raw_entry)) {
        $raw_entry = json_decode($raw_entry, true);
    }
    
    if (!is_array($raw_entry)) {
        return $processed_fields;
    }
    
    // Helper function to extract field data
    $extract_field = function($key, $value) {
        // Skip internal/technical fields
        $skip_prefixes = ['_', 'form_', 'post_', 'date_', 'is_', 'payment_', 'transaction_'];
        foreach ($skip_prefixes as $prefix) {
            if (strpos($key, $prefix) === 0) {
                return null;
            }
        }
        
        // Handle different value formats
        if (is_array($value)) {
            // Format 3: Object with value property
            if (isset($value['value'])) {
                return [
                    'name' => isset($value['label']) ? $value['label'] : 
                           (isset($value['key']) ? self::prettify_field_name($value['key']) : 
                            self::prettify_field_name($key)),
                    'value' => $value['value']
                ];
            }
            // Format 4: Array format
            if (isset($value['name']) && isset($value['value'])) {
                return [
                    'name' => $value['name'],
                    'value' => $value['value']
                ];
            }
        }
        
        // Format 1 & 2: Simple key-value pairs
        return [
            'name' => self::prettify_field_name($key),
            'value' => $value
        ];
    };
    
    // Process sequential arrays
    if (self::is_sequential_array($raw_entry)) {
        foreach ($raw_entry as $field) {
            if (is_array($field) && isset($field['name']) && isset($field['value'])) {
                $processed_fields[] = $extract_field($field['name'], $field['value']);
            }
        }
    } else {
        // Process associative arrays
        foreach ($raw_entry as $key => $value) {
            $field_data = $extract_field($key, $value);
            if ($field_data !== null) {
                $processed_fields[] = $field_data;
            }
        }
    }
    
    return array_filter($processed_fields);
}

	/**
	 * Report a spam entry as ham/not spam
	 *
	 * @param int $id entry ID
	 */
	public static function report_spam_entry( $id ) {
		global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_spam_entries';

		$spamEntry = $wpdb->get_row(
			$wpdb->prepare(
				"
					SELECT message, ip, email
					FROM $table
					WHERE id = %s
				",
				$id
			)
		);

		$submitReport  = oopspamantispam_report_OOPSpam($spamEntry->message, $spamEntry->ip, $spamEntry->email, false);

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

			// Add email to allowed emails if it doesn't already exist
			if (isset($spamEntry->email) && !empty($spamEntry->email)) {
				$allowed_emails = isset($manual_moderation_settings['mm_allowed_emails']) ? $manual_moderation_settings['mm_allowed_emails'] : '';
				$email_list = array_map('trim', explode("\n", $allowed_emails));
				if (!in_array($spamEntry->email, $email_list)) {
					$email_list[] = $spamEntry->email;
					$manual_moderation_settings['mm_allowed_emails'] = implode("\n", $email_list);
				}
			}

			// Add IP to allowed IPs if it doesn't already exist
			if (isset($spamEntry->ip) && !empty($spamEntry->ip)) {
				$allowed_ips = isset($manual_moderation_settings['mm_allowed_ips']) ? $manual_moderation_settings['mm_allowed_ips'] : '';
				$ip_list = array_map('trim', explode("\n", $allowed_ips));
				if (!in_array($spamEntry->ip, $ip_list)) {
					$ip_list[] = $spamEntry->ip;
					$manual_moderation_settings['mm_allowed_ips'] = implode("\n", $ip_list);
				}
			}

			// Update the settings only if changes were made
			if (isset($manual_moderation_settings['mm_allowed_emails']) || isset($manual_moderation_settings['mm_allowed_ips'])) {
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
        $table = $wpdb->prefix . 'oopspam_frm_spam_entries';

		$sql = $wpdb->prepare("SELECT COUNT(*) FROM %i WHERE 1=1", $table);
		$values = array();
		
		// Add reason filter if selected
		if (isset($_GET['filter_reason']) && !empty($_GET['filter_reason'])) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE reason = %s",
				$table,
				sanitize_text_field($_GET['filter_reason'])
			);
		}
		
		return $wpdb->get_var($sql);
	}


	/** Text displayed when no spam entry is available */
	public function no_items() {
		_e( 'No spam entries available.', 'sp' );
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
            case 'score':
            case 'raw_entry':
            case 'form_id':
			case 'reason':
            case 'date':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
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
		$delete_nonce = wp_create_nonce( 'sp_delete_spam' );
		$report_nonce = wp_create_nonce( 'sp_report_spam' );
		$notify_nonce = wp_create_nonce( 'sp_notify_spam' );
	
		// Limit the message to 80 characters
		$truncated_message = substr($item['message'], 0, 80);
		if (strlen($item['message']) > 80) {
			$truncated_message .= '...';
		}
	
		$title = '<span title="' . esc_attr($item['message']) . '">' . esc_html($truncated_message) . '</span>';
	
		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&spam=%s&_wpnonce=%s">Delete</a>', sanitize_text_field( $_GET['page'] ), 'delete', absint( $item['id'] ), $delete_nonce ),
			'report' => sprintf( '<a style="color:green; %s" href="?page=%s&action=%s&spam=%s&_wpnonce=%s">Not Spam</a>', ($item['reported'] === '1' ? 'color: grey !important;pointer-events: none;
			cursor: default; opacity: 0.5;' : ''), sanitize_text_field( $_GET['page'] ), 'report', absint( $item['id'] ), $report_nonce ),
			'notify' => sprintf( '<a href="?page=%s&action=%s&spam=%s&_wpnonce=%s">E-mail admin</a>', sanitize_text_field( $_GET['page'] ), 'notify', absint( $item['id'] ), $notify_nonce ),
		];
	
		return $title . $this->row_actions( $actions );
	}

	function column_raw_entry( $item ) {
		add_thickbox();
		$short_raw_entry = substr( $item['raw_entry'], 0, 50 );
		$json_string = $this->json_print( $item['raw_entry'] );
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
			return '<span style="color:green;">Reported as not spam</span>';
		}
		return '';
	}

    /**
	 *  Prettify JSON
	 *
	 * @return array
	 */
    function json_print($json) { return '<pre style=" white-space: pre-wrap;       /* Since CSS 2.1 */
        white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
        white-space: -pre-wrap;      /* Opera 4-6 */
        white-space: -o-pre-wrap;    /* Opera 7 */
        word-wrap: break-word;       /* Internet Explorer 5.5+ */">' . json_encode(json_decode($json), JSON_PRETTY_PRINT) . '</pre>'; }
 
	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'      => '<input type="checkbox" />',
			'reported'    => __( 'Status', 'sp' ),
			'message'    => __( 'Message', 'sp' ),
			'ip' => __( 'IP', 'sp' ),
			'email' => __( 'Email', 'sp' ),
			'score'    => __( 'Score', 'sp' ),
            'form_id'    => __( 'Form Id', 'sp' ),
            'raw_entry'    => __( 'Raw fields', 'sp' ),
			'reason'    => __( 'Reason', 'sp' ),
            'date'    => __( 'Date', 'sp' )
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
			'email' => array( 'email', false ),
			'reason' => array( 'reason', false )
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
			'bulk-report' => 'Report as ham'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'entries_per_page', 10 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //We have to calculate the total number of items
			'per_page'    => $per_page //We have to determine how many items to show on a page
		] );

		if (isset($_POST['page']) && isset($_POST['s'])) {
			$this->items = self::get_spam_entries($per_page, $current_page, $_POST['s']);
		} else {
			$this->items = self::get_spam_entries( $per_page, $current_page, "" );
		}
	}

	public function process_bulk_action() {

		//Detect when a bulk action is being triggered...
		if ('bulk-report' === $this->current_action()) {
			
			$report_ids = isset($_POST['bulk-delete']) ? array_map('intval', $_POST['bulk-delete']) : [];
	
			if (!empty($report_ids)) {
				foreach ($report_ids as $id) {
					// Report each selected entry as ham
					self::report_spam_entry($id);
				}
				// Add a message to notify the user of success
				echo '<div class="updated"><p>Selected entries have been reported as ham.</p></div>';
			}
		}
		if ( 'report' === $this->current_action() ) {

			// Verify the nonce.
			$nonce = esc_attr( $_GET['_wpnonce'] );

			if (!isset( $_GET['_wpnonce'] ) ||  !wp_verify_nonce( $nonce, 'sp_report_spam' ) ) {
				die( 'Not allowed!' );
			}
			else {
				self::report_spam_entry( absint( $_GET['spam'] ) );
				wp_redirect( admin_url( 'admin.php?page=wp_oopspam_frm_spam_entries' ) );
				exit;
			}

		}
		if ( 'delete' === $this->current_action() ) {

			// Verify the nonce.
			$nonce = esc_attr( $_GET['_wpnonce'] );

			if (!isset( $_GET['_wpnonce'] ) ||  !wp_verify_nonce( $nonce, 'sp_delete_spam' ) ) {
				die( 'Not allowed!' );
			}
			else {
				self::delete_spam_entry( absint( $_GET['spam'] ) );
                        wp_redirect( admin_url( 'admin.php?page=wp_oopspam_frm_spam_entries' ) );
				exit;
			}

		}
		if ( 'notify' === $this->current_action() ) {

			// Verify the nonce.
			$nonce = esc_attr( $_GET['_wpnonce'] );

			if (!isset( $_GET['_wpnonce'] ) ||  !wp_verify_nonce( $nonce, 'sp_notify_spam' ) ) {
				die( 'Not allowed!' );
			}
			else {
				self::notify_spam_entry( absint( $_GET['spam'] ) );
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_spam_entry( $id );
			}

			// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
		        // add_query_arg() return the current url
		        wp_redirect( esc_url_raw(add_query_arg()) );
			exit;
		}
	}

	function column_ip($item) {
        $ip = esc_html($item['ip']);
        $country = $this->get_country_by_ip($ip);
        return $ip . '<br>' . $country;
    }

	private function get_country_by_ip($ip) {
		// Ignore local IPs
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			return 'Local IP';
		}

		$response = wp_remote_get("https://reallyfreegeoip.org/json/{$ip}");
		if (is_wp_error($response)) {
			return '';
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		if (isset($data['country_code'])) {
			$country_code = strtolower($data['country_code']);
			$countries = oopspam_get_isocountries();
			return isset($countries[$country_code]) ? $countries[$country_code] : 'Unknown';
		}

		return '';
	}

}


class OOPSpam_Spam {

	// class instance
	static $instance;

	// Spam entries WP_List_Table object
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

        add_submenu_page( 'wp_oopspam_settings_page', __('Settings', "oopspam"),  __('Settings', "oopspam"), 'manage_options', 'wp_oopspam_settings_page');

        $hook =  add_submenu_page(
            'wp_oopspam_settings_page',
            __('Form Spam Entries', "oopspam"),
            __('Form Spam Entries', "oopspam"),
            'edit_pages',
            'wp_oopspam_frm_spam_entries',
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
				<h2 style="padding-right:0.5em;"><?php _e("Spam Entries", "oopspam"); ?></h2>
				<input type="button" id="empty-spam-entries" style="margin-right:0.5em;" class="button action" value="<?php _e("Empty the table", "oopspam"); ?>">
				<input type="button" id="export-spam-entries" class="button action" value="<?php _e("Export CSV", "oopspam"); ?>">
            </div>
			<div>
				<p><?php _e("All submissions are stored locally in your WordPress database.", "oopspam"); ?></p>
				<p><?php _e("In the below table you can view, delete, and report spam entries.", "oopspam"); ?></p>
				<p><?php _e("If you believe any of these should NOT be flagged as spam, please follow these steps to report them to us. This will improve spam detection for your use case.  ", "oopspam"); ?> </p>
				<ul>
					<li><?php _e("1. Hover on an entry", "oopspam"); ?></li>
					<li><?php _e('2. Click the <span style="color:green;">"Not Spam"</span> link', 'oopspam'); ?></li>
					<li><?php _e('3. Page will be refreshed and Status (first column) will display  <span style="color:green;">"Reported as not spam"</span>', 'oopspam'); ?></li>
				</ul>
			</div>
			<div id="entries">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="get"> <!-- Changed from post to get -->
								<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']) ?>" />
								<?php 
								$this->entries_obj->prepare_items();
								$this->entries_obj->search_box('search', 'search_id');
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
			'label'   => 'Spam Entries',
			'default' => 10,
			'option'  => 'entries_per_page'
		];

		add_screen_option( $option, $args );

		$this->entries_obj = new Spam_Entries();
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
	OOPSpam_Spam::get_instance();
} );