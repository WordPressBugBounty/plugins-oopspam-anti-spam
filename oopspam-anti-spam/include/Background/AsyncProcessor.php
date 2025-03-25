<?php
namespace OOPSPAM\Background;

class AsyncProcessor {
    public static function init() {
        add_action('wp_ajax_process_bulk_entries', array(__CLASS__, 'process_bulk_entries'));
    }

    public static function process_bulk_entries() {
        if (!current_user_can('edit_pages')) {
            wp_send_json_error('Permission denied');
        }

        check_ajax_referer('bulk-entries', 'nonce');

        $entry_ids = isset($_POST['entry_ids']) ? array_map('absint', $_POST['entry_ids']) : array();
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $entry_type = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';

        if (empty($entry_ids) || empty($action)) {
            wp_send_json_error('Invalid parameters');
        }

        // Process one entry at a time
        $current_id = array_shift($entry_ids);
        
        if ($entry_type === 'spam') {
            if ($action === 'bulk-delete') {
                \OOPSPAM\UI\Spam_Entries::delete_spam_entry($current_id);
            } elseif ($action === 'bulk-report') {
                \OOPSPAM\UI\Spam_Entries::report_spam_entry($current_id);
            }
        } else {
            if ($action === 'bulk-delete') {
                \OOPSPAM\UI\Ham_Entries::delete_ham_entry($current_id);
            } elseif ($action === 'bulk-report') {
                \OOPSPAM\UI\Ham_Entries::report_ham_entry($current_id);
            }
        }

        wp_send_json_success(array(
            'remaining' => $entry_ids,
            'processed' => $current_id,
            'complete' => empty($entry_ids)
        ));
    }
}

AsyncProcessor::init();
