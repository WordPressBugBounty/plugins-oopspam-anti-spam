<?php
namespace OOPSPAM\Background;

class AsyncProcessor {
    public static function init() {
        add_action('wp_ajax_process_bulk_entries', array(__CLASS__, 'process_bulk_entries'));
    }

    public static function process_bulk_entries() {
        try {
            if (!current_user_can('edit_pages')) {
                wp_send_json_error('Permission denied');
            }

            check_ajax_referer('bulk-entries', 'nonce');

            $entry_ids = isset($_POST['entry_ids']) ? array_map('absint', $_POST['entry_ids']) : array();
            $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
            $entry_type = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';

            if (empty($entry_ids) || empty($action)) {
                wp_send_json_error('Invalid parameters: entry_ids=' . count($entry_ids) . ', action=' . $action);
            }

            // Process one entry at a time
            $current_id = array_shift($entry_ids);
            
            // Store processed IDs for bulk notification (spam entries marked as not spam only)
            $processed_ids = get_transient('oopspam_bulk_processed_ids') ?: array();
            
            if ($entry_type === 'spam') {
                if ($action === 'bulk-delete') {
                    \OOPSPAM\UI\Spam_Entries::delete_spam_entry($current_id);
                } elseif ($action === 'bulk-report') {
                    // For bulk report, we'll collect IDs and send one email at the end
                    $processed_ids[] = $current_id;
                    set_transient('oopspam_bulk_processed_ids', $processed_ids, 300); // 5 minutes expiry
                    
                    // Call report_spam_entry but disable individual email notification temporarily
                    add_filter('oopspam_disable_individual_not_spam_email', '__return_true');
                    $report_result = \OOPSPAM\UI\Spam_Entries::report_spam_entry($current_id);
                    remove_filter('oopspam_disable_individual_not_spam_email', '__return_true');
                    
                    if (!$report_result) {
                        error_log("AsyncProcessor: Failed to report entry $current_id as not spam");
                        // Still continue processing other entries
                    }
                }
            } else {
                if ($action === 'bulk-delete') {
                    \OOPSPAM\UI\Ham_Entries::delete_ham_entry($current_id);
                } elseif ($action === 'bulk-report') {
                    \OOPSPAM\UI\Ham_Entries::report_ham_entry($current_id);
                }
            }

            $is_complete = empty($entry_ids);
            
            // If this is the last entry and it's a bulk report for spam entries, send bulk notification
            if ($is_complete && $action === 'bulk-report' && $entry_type === 'spam' && !empty($processed_ids)) {
                \OOPSPAM\UI\Spam_Entries::notify_bulk_not_spam($processed_ids);
                delete_transient('oopspam_bulk_processed_ids');
            }

            wp_send_json_success(array(
                'remaining' => $entry_ids,
                'processed' => $current_id,
                'complete' => $is_complete
            ));

        } catch (Exception $e) {
            error_log('AsyncProcessor Error: ' . $e->getMessage());
            wp_send_json_error('Processing error: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('AsyncProcessor Fatal Error: ' . $e->getMessage());
            wp_send_json_error('Fatal error: ' . $e->getMessage());
        }
    }
}

AsyncProcessor::init();
