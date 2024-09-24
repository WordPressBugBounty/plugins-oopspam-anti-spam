<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
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

add_action('wp_ajax_empty_spam_entries', 'empty_spam_entries' ); // executed when logged in

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
        $column_names = $wpdb->get_col("DESC $table");
        $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

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


add_action('wp_ajax_export_spam_entries', 'export_spam_entries' ); // executed when logged in

class Spam_Entries extends WP_List_Table {

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
	
		// If search term is provided, construct the search query
		if (!empty($search)) {
			$likeQ = '%';
			$search = $likeQ . $wpdb->esc_like($search) . $likeQ;
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table
					WHERE form_id LIKE %s OR 
					message LIKE %s OR 
					ip LIKE %s OR 
					email LIKE %s OR
					raw_entry LIKE %s",
					$search,
					$search,
					$search,
					$search,
					$search
				),
				'ARRAY_A'
			);
		}
	
		// Build the base query
		$sql = "SELECT * FROM $table";
	
		// Check and sanitize orderby parameter
		if (!empty($_GET['orderby'])) {
			$orderby = sanitize_sql_orderby($_GET['orderby']); 
			$order = !empty($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'ASC'; 
			$sql .= " ORDER BY $orderby $order";
		} else {
			$sql .= ' ORDER BY date DESC'; // Default ordering by date
		}
	
		// Add LIMIT and OFFSET for pagination
		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ($page_number - 1) * $per_page;
	
		// Run the query
		$result = $wpdb->get_results($sql, 'ARRAY_A');
	
		return $result;
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
	 * Send an email to admin email
	 *
	 * @param int $id entry ID
	 */
	public static function notify_spam_entry( $id ) {
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
	
		$email = $spamEntry->email;
		$message = $spamEntry->message;
		$body = "E-mail: " . $email . " <br><br>" . "Message " . $message;
		
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
				
		// Check if there are valid email addresses
		if (!empty($to_array) && is_email($email)) {
			$subject = "A new submission from " . get_bloginfo('name');
			$sent_to = array();
		
			// Send the email to each recipient
			foreach ($to_array as $recipient) {
				$headers = 'From: ' . $recipient . "\r\n" .
						   'Reply-To: ' . $email . "\r\n" .
						   'Content-Type: ' . 'text/html' . "\r\n";
				$sent = wp_mail($recipient, $subject, $body, $headers);
				if ($sent) {
					$sent_to[] = $recipient;
				}
			}
		
			if (!empty($sent_to)) {
				$recipient_list = implode(', ', $sent_to);
				echo "<script type='text/javascript'>alert('Email is sent to: " . $recipient_list . "');</script>";
			} else {
				echo "<script type='text/javascript'>alert('Failed to send email.');</script>";
			}
		}
		
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

		$sql = "SELECT COUNT(*) FROM $table";

		return $wpdb->get_var( $sql );
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
			'bulk-delete' => 'Delete'
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

}


class SP_Plugin {

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
		<div class="wrap">
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
							<form method="post">
								<?php $this->entries_obj->prepare_items(); ?>
									<input type="hidden" name="page" value="wp_oopspam_frm_spam_entries" />
									<?php $this->entries_obj->search_box('search', 'search_id'); ?>
								<?php $this->entries_obj->display(); ?>
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
	SP_Plugin::get_instance();
} );