=== OOPSpam Anti-Spam ===
Contributors: oopspam
Link: http://www.oopspam.com/
Tags: spam, anti spam, anti-spam, spam protection, comments
Requires at least: 3.6
Tested up to: 6.6
Stable tag: 1.2.15
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Stop bots and manual spam from reaching you in comments & contact forms. All with high accuracy, accessibility, and privacy. 

== Description ==
The OOPSpam WordPress plugin is a modern spam filter that uses machine learning to analyze messages, checking each submission against our extensive database of 500 million IPs and emails to effectively detect and block spam.

It uses the [OOPSpam API](https://www.oopspam.com/), which protects over 3.5M websites daily. 

Features:

* Customize the "sensitivity level" of spam filtering so you don't miss important messages
* Checks messages using a machine learning model
* Country restrictions
* Language restrictions
* Automatically checks against multiple IP & email blacklists
* Automatically detects spam word patterns
* Manual moderation to block email, IP and by exact keywords
* [Privacy] `Do not analyze IP addresses` setting
* [Privacy] `Do not analyze Emails` setting. 
* [Privacy] `Remove sensitive information from messages` setting
* Form Spam Entries: view spam entries, delete, send submission to website admin or report them to us.
* Form Ham Entries: view not spam entries, delete or report them to us.
* and many custom rules, advanced analysis to detect and mark as spam

The value we bring:

* Fast, Lightweight & Accessible
* No cookies, no challenges, no javascript, no tracking
* High accuracy (%99.9)
* Use one API key with unlimited websites
* No data stored on our servers. All your data is stored in your local WordPress database.
* Privacy by design
* Well tested so it will NOT break your website with every update
* Transparent and responsible company. We have an active vulnerability disclosure program.
* Support (24 hour response)


The plugin filters both **comments**, **site search**, and **contact form submissions**.

### Supported form & comment solutions:

- WooCommerce Order & Registration
- Elementor Forms
- Ninja Forms
- Gravity Forms
- Kadence Form Block and Form (Adv) Block
- Fluent Forms
- Breakdance Forms
- WS Form
- WPDiscuz
- Forminator
- WPForms
- Formidable Forms
- Contact Form 7
- Bricks Forms
- Toolset Forms
- Piotnet Forms 
- GiveWP Donation Forms
- MailPoet
- Beaver Builder Contact Form
- Ultimate Member
- MemberPress
- Paid Memberships Pro


OOPSpam Anti-Spam Wordpress plugin requires minimal configuration. The only thing you need to do is to [get a key](https://app.oopspam.com/Identity/Account/Register) and paste it into the appropriate setting field under _Settings=>OOPSpam Anti-Spam_. If you have a contact form plugin, make sure you enable spam protection on the settings page.

**Please note**: This is a premium plugin. You need an [OOPSpam Anti-Spam API key](https://app.oopspam.com/Identity/Account/Register) to use the plugin. Each account comes with 40 free spam checks per month.
If you already use OOPSpam on other platforms, you can use the same API key for this plugin.

== Installation ==
You can install OOPSpam Anti-Spam plugin both from your WordPress admin dashboard and manually.

### INSTALL OOPSpam Anti-Spam FROM WITHIN WORDPRESS

1. Visit the plugins page within your dashboard and select ‘Add New’;
2. Search for ‘oopspam’;
3. Activate OOPSpam Anti-Spam from your Plugins page;
4. Go to _OOPSpam Anti-Spam=>Settings_

### INSTALL OOPSpam Anti-Spam MANUALLY

1. Upload the ‘oopspam-anti-spam’ folder to the /wp-content/plugins/ directory;
2. Activate the OOPSpam Anti-Spam plugin through the ‘Plugins’ menu in WordPress;
3. Go to _OOPSpam Anti-Spam=>Settings_

### AFTER ACTIVATION
    
Using the plugin requires you to have an OOPSpam API key. You can get one from [here](https://app.oopspam.com/).
Once you have a key, copy it and paste into OOPSpam API key field under _OOPSpam Anti-Spam=>Settings_

== Changelog ==
= 1.2.15 =
* NEW: Added support for Kadence Form (Advanced) Block
* NEW: Automatically send flagged spam comments to OOPSpam for reporting
= 1.2.14 =
* NEW: Added `oopspam_woo_disable_honeypot` hook to disable honeypot in WooCommerce
* IMPROVEMENT: Reorganized privacy settings under the Privacy tab for better clarity
* IMPROVEMENT: General UX enhancements for a smoother experience
* FIX: Resolved issue where WooCommerce blockings were not logged
= 1.2.13 =
* NEW: View spam detection reasons in the Form Spam Entries table
* NEW: Report entries flagged as spam in Gravity Forms to OOPSpam
* NEW: Report entries flagged as not spam in Gravity Forms to OOPSpam
* IMPROVEMENT: Admin comments bypass spam checks
= 1.2.12 =
* NEW: `Block messages containing URLs` setting
= 1.2.11 =
* NEW: Paid Memberships Pro support
= 1.2.10 =
* FIX: Broken `The main content field ID (optional)` setting
= 1.2.9 =
* NEW: MemberPress integration
* IMPROVEMENT: Detect Cloudflare proxy in IP detection
= 1.2.8 =
* NEW: Integrated spam submission routing to Gravity Forms' Spam folder
* NEW: Introduced Allowed IPs and Emails settings in Manual Moderation
* NEW: Implemented automatic allowlisting of email and IP when an entry is marked as ham (not spam)
* IMPROVEMENT: Enhanced GiveWP integration to capture donor email addresses
* IMPROVEMENT: Optimized content analysis in GiveWP by combining comment, first name, and last name fields
* FIX: Prevent duplicate entries in Blocked Emails and IPs settings
= 1.2.7 =
* NEW: Automatic local blocking of email and IP when an item is reported as spam
* IMPROVEMENT: Truncate long messages in Form Ham Entries and Form Spam Entries tables
* IMPROVEMENT: Clean up manual moderation data from the database when plugin is uninstalled
* FIX: Correct usage of <label> elements in the settings fields for improved accessibility
* FIX: Resolve dynamic property deprecation warnings
= 1.2.6 =
* NEW: [Fluent Forms] Specify content field by Form ID and Field Name pair
* NEW: [Fluent Forms] Combine multiple field values for the 'The main content field' setting
* FIX: [Fluent Forms] Fix error when there is no textarea in a form
= 1.2.5 =
* NEW: [WS Form] Specify content field by Form ID and Field ID pair
* NEW: [WS Form] Combine multiple field values for the 'The main content field' setting
* FIX: Error when "Not Spam" is used in the Form Spam Entries table
= 1.2.4 =
* NEW: "Block disposable emails" setting
* FIX: Broken "Move spam comments to" setting
= 1.2.3 =
* NEW: Basic HTML support for error messages in all integrations
* NEW: Ability to set multiple recipients for `Email Admin` in the Form Spam Entries table
* NEW: [Gravity Forms] Specify content field by Form ID and Field ID pair
* NEW: [Gravity Forms] Combine multiple field values for the `The main content field` setting
* IMPROVEMENT: Improved security and accessibility by migrating to a modern <select> UI control library
= 1.2.2 =
* NEW: [Gravity Forms] Better compatibility with Gravity Perks Limit Submissions
* IMPROVEMENT: [Gravity Forms] Display error message at top of form instead of next to field
= 1.2.1 =
* NEW: [Elementor Forms] Specify content field by Form ID and Field ID pair
* NEW: [Elementor Forms] Combine multiple field values for the `The main content field` setting
* NEW: Wildcard support for manual email blocking (e.g. *@example.com)
= 1.2 =
* NEW: [WPForms] Specify content field by Form ID and Field ID pair
* NEW: [WPForms] Combine multiple field values for the `The main content field` setting
* FIX: Prevent email notifications for spam comments
* FIX: Send email from site admin instead of form submitter in `E-mail admin` setting
= 1.1.64/65 =
* IMPROVEMENT: [WPForms] Use Field Name/Label in `The main content field ID (optional)` setting
= 1.1.63 =
* NEW: Display a custom error message in Contact Form 7
= 1.1.62 =
* NEW: `Don't protect these forms` setting. Ability to exclude a form from spam protection
* NEW: `Export CSV` in Form Spam Entries & Form Ham Entries tables
* IMPROVEMENT: More reliable IP detection
* IMPROVEMENT: Confirmation prompt before emptying Ham and Spam Entries table
* IMPROVEMENT: Improved styling of the settings page
* IMPROVEMENT: Hide `Blocked countries` when `Do not analyze IP addresses` is enabled
= 1.1.61 =
* NEW: `Manual moderation` setting to manually block email, IP and exact keyword.
* NEW: `Email admin` setting under `Form Spam Entries` to send submission data to the website admin
* FIX: Load plugin Javascript and CSS files only in the plugin settings
= 1.1.60 =
* IMPROVEMENT: WS Form integration uses new pre-submission hook. No need to add an action anymore
* NEW: WS Form Spam Message error field
* NEW: Ultimate Member support
= 1.1.59 =
* FIX: Error when reporting false positives/negatives
= 1.1.58 =
* NEW: `Log submissions to OOPSpam` setting. Allows you to view logs in the OOPSpam Dashboard
= 1.1.57 =
* FIX: WooCommerce spam filtering applied even when spam protection was off
= 1.1.56 =
* NEW: `The main content field ID` setting now supports multiple ids (separated by commas)
* NEW: Beaver Builder contact form support
= 1.1.55 =
* IMPROVEMENT: A better way to prevent empty messages from passing through
= 1.1.54 =
* NEW: Trackback and Pingback protection
* NEW: WP comment logs are available under the Form Spam/Ham Entries tables.
= 1.1.53 =
* FIX: WP_Query warning in the search protection
= 1.1.52 =
* MISC: Compatibility tested with WP 6.4
= 1.1.51 =
* IMPROVEMENT: Bricks Form integration doesn't require to add custom action.
= 1.1.50 =
* NEW: Breakdance Forms support
* FIX: Failed nonce verification in cron jobs that empty spam/ham entries


== Screenshots ==
1. OOPSpam admin settings
2. Spam Entries from contact forms
3. Manual moderation settings


== Frequently Asked Questions ==

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of this plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/oopspam-anti-spam). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.