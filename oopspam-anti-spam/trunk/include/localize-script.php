<?php
// Localize the script with the nonce value
function enqueue_custom_scripts() {
    wp_enqueue_script( 'tom-select', plugin_dir_url( __FILE__ ) . 'libs/tom-select.complete.min.js', array( 'jquery' ), false, true);

    wp_enqueue_script( 'helper', plugin_dir_url( __FILE__ ) . 'helper.js', array( 'jquery', 'tom-select' ), false, true );


    // Localize the script with the nonce
    wp_localize_script('helper', 'customScript', array(
        'emptySpamEntriesNonce' => wp_create_nonce('empty_spam_entries_nonce'),
        'emptyHamEntriesNonce' => wp_create_nonce('empty_ham_entries_nonce'),
        'exportSpamEntriesNonce' => wp_create_nonce('export_spam_entries_nonce'),
        'exportHamEntriesNonce' => wp_create_nonce('export_ham_entries_nonce'),
    ));
}
add_action( 'admin_enqueue_scripts', 'enqueue_custom_scripts' );