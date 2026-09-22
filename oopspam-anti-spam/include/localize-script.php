<?php

namespace OOPSPAM\UI;

// Localize the script with the nonce value
function enqueue_custom_scripts( $hook_suffix = '' ) {
    wp_enqueue_script( 'tom-select', plugin_dir_url( __FILE__ ) . 'libs/tom-select.complete.min.js', array( 'jquery' ), false, true);

    wp_enqueue_script( 'helper', plugin_dir_url( __FILE__ ) . 'helper.js', array( 'jquery', 'tom-select' ), false, true );

    // Localize the script with the nonce
    $localized_data = array(
        'emptySpamEntriesNonce' => wp_create_nonce('empty_spam_entries_nonce'),
        'emptyHamEntriesNonce' => wp_create_nonce('empty_ham_entries_nonce'),
        'exportSpamEntriesNonce' => wp_create_nonce('export_spam_entries_nonce'),
        'exportHamEntriesNonce' => wp_create_nonce('export_ham_entries_nonce'),
    );

    // Spam word lists are only needed on the settings page to pre-fill the
    // "Blocked keywords and phrases" field.
    if ( false !== strpos( (string) $hook_suffix, 'wp_oopspam_settings_page' ) ) {
        $localized_data['spamWords'] = wp_list_pluck( oopspam_get_spam_words(), 'words' );
    }

    wp_localize_script('helper', 'customScript', $localized_data);
}
add_action( 'admin_enqueue_scripts', 'OOPSPAM\UI\enqueue_custom_scripts' );