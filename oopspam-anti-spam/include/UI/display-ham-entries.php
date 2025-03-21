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
    $nonce = $_POST['nonce'];
    if ( ! wp_verify_nonce( $nonce, 'empty_ham_entries_nonce' ) ) {
        wp_send_json_error( array(
            'error'   => true,
            'message' => 'CSRF verification failed.',
        ), 403 );
    }

    global $wpdb; 
    $table = $wpdb->prefix . 'oopspam_frm_ham_entries';

	$action_type = $_POST['action_type'];
    if ($action_type === "empty-entries") {
        $wpdb->query($wpdb->prepare("TRUNCATE TABLE %i", $table));
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
        $nonce = $_POST['nonce'];
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
        error_log('export_ham_entries: ' . $e->getMessage());
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_export_ham_entries', 'OOPSPAM\UI\export_ham_entries' ); // executed when logged in

class Ham_Entries extends \WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Entry', 'sp' ), //singular name of the listed records
			'plural'   => __( 'Entries', 'sp' ), //plural name of the listed records
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
		$table = $wpdb->prefix . 'oopspam_frm_ham_entries';
	
		 // Start building the query
		 $where = array();
		 $values = array();
		 
		 // Add search condition if search term is provided
		 if (!empty($search)) {
			 $search_term = '%' . $wpdb->esc_like($search) . '%';
			 $where[] = "(form_id LIKE %s OR message LIKE %s OR ip LIKE %s OR email LIKE %s OR raw_entry LIKE %s)";
			 $values = array_merge($values, array($search_term, $search_term, $search_term, $search_term, $search_term));
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
					SELECT message, ip, email
					FROM $table
					WHERE id = %s
				",
				$id
			)
		);

		$submitReport  = oopspamantispam_report_OOPSpam($spamEntry->message, $spamEntry->ip, $spamEntry->email, true);

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

		return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $table));
	}


	/** Text displayed when no ham entry is available */
	public function no_items() {
		_e( 'No ham entries available.', 'sp' );
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

		$delete_nonce = wp_create_nonce( 'sp_delete_ham' );
		$report_nonce = wp_create_nonce( 'sp_report_ham' );

		// Limit the message to 80 characters
		$truncated_message = substr($item['message'], 0, 80);
		if (strlen($item['message']) > 80) {
			$truncated_message .= '...';
		}

		$title = '<span title="' . esc_attr($item['message']) . '">' . esc_html($truncated_message) . '</span>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&ham=%s&_wpnonce=%s">Delete</a>', sanitize_text_field( $_GET['page'] ), 'delete', absint( $item['id'] ), $delete_nonce ),
			'report' => sprintf( '<a style="color:#996800; %s" href="?page=%s&action=%s&ham=%s&_wpnonce=%s">Report as spam</a>', ($item['reported'] === '1' ? 'color: grey !important;pointer-events: none;
			cursor: default; opacity: 0.5;' : ''), sanitize_text_field( $_GET['page'] ), 'report', absint( $item['id'] ), $report_nonce )

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
			$this->items = self::get_ham_entries($per_page, $current_page, $_POST['s']);
		} else {
			$this->items = self::get_ham_entries( $per_page, $current_page, "" );
		}
	}

	public function process_bulk_action() {

		//Detect when a bulk action is being triggered...
		if ('bulk-report' === $this->current_action()) {
			$report_ids = isset($_POST['bulk-delete']) ? array_map('intval', $_POST['bulk-delete']) : [];
	
			if (!empty($report_ids)) {
				foreach ($report_ids as $id) {
					// Report each selected entry as spam
					self::report_ham_entry($id);
				}
				// Add a message to notify the user of success
				echo '<div class="updated"><p>Selected entries have been reported as spam.</p></div>';
			}
		}
		
		if ( 'report' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_GET['_wpnonce'] );

			if (!isset( $_GET['_wpnonce'] ) ||  !wp_verify_nonce( $nonce, 'sp_report_ham' ) ) {
				die( 'Not allowed!' );
			}
			else {
				self::report_ham_entry( absint( $_GET['ham'] ) );

				// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
				// add_query_arg() return the current url
				// wp_redirect( esc_url_raw(add_query_arg()) );
				wp_redirect( admin_url( 'admin.php?page=wp_oopspam_frm_ham_entries' ) );
				exit;
			}

		}
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_GET['_wpnonce'] );

			if (!isset( $_GET['_wpnonce'] ) ||  !wp_verify_nonce( $nonce, 'sp_delete_ham' ) ) {
				die( 'Not allowed!' );
			}
			else {
				self::delete_ham_entry( absint( $_GET['ham'] ) );

		                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
		                // add_query_arg() return the current url
		                // wp_redirect( esc_url_raw(add_query_arg()) );
                        wp_redirect( admin_url( 'admin.php?page=wp_oopspam_frm_ham_entries' ) );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_ham_entry( $id );
			}

			// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
		        // add_query_arg() return the current url
		        wp_redirect( esc_url_raw(add_query_arg()) );
			exit;
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
            __('Form Ham Entries', "oopspam"),
            __('Form Ham Entries', "oopspam"),
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
				<h2 style="padding-right:0.5em;"><?php _e("Ham (Not Spam) Entries", "oopspam"); ?></h2>
				<input type="button" id="empty-ham-entries" style="margin-right:0.5em;" class="button action" value="<?php _e("Empty the table", "oopspam"); ?>">
				<input type="button" id="export-ham-entries" class="button action" value="<?php _e("Export CSV", "oopspam"); ?>">
            </div>
			<div>
				<p><?php _e("All submissions are stored locally in your WordPress database.", "oopspam"); ?></p>
				<p><?php _e("In the below table you can view, delete, and report ham (not spam) entries.", "oopspam"); ?></p>
				<p><?php _e("If you believe any of these SHOULD be flagged as spam, please follow these steps to report them to us. This will improve spam detection for your use case.", "oopspam"); ?> </p>
				<ul>
					<li><?php _e("1. Hover on an entry", "oopspam"); ?></li>
					<li><?php _e('2. Click the <span style="color:#996800;">"Report as spam"</span> link', 'oopspam'); ?></li>
					<li><?php _e('3. Page will be refreshed and Status (first column) will display  <span style="color:#996800;">"Reported as spam"</span>', 'oopspam'); ?></li>
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