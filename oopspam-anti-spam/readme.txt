=== OOPSpam Anti-Spam ===
Contributors: oopspam
Link: http://www.oopspam.com/
Tags: spam, anti spam, anti-spam, spam protection, comments
Requires at least: 3.6
Tested up to: 6.7
Stable tag: 1.2.34
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Stop bots and manual spam from reaching you in comments & contact forms. All with high accuracy, accessibility, and privacy. 

== Description ==
The OOPSpam WordPress plugin is a modern spam filter that uses machine learning to analyze messages, checking each submission against our extensive database of 500 million IPs and emails to effectively detect and block spam.

It uses the [OOPSpam API](https://www.oopspam.com/), which protects over 3.5M websites daily. 


### Features:  
- **Spam Filtering Sensitivity**: Customize the "sensitivity level" to ensure important messages aren't missed.  
- **Machine Learning Spam Detection**: Messages are analyzed using advanced machine learning models.  
- **Country Restrictions**: Block or allow submissions based on geographic location.  
- **Language Restrictions**: Restrict submissions based on language.  
- **Blacklist Integration**: Automatically checks messages against multiple IP and email blacklists.  
- **Spam Word Detection**: Identifies and blocks messages containing common spam patterns.  
- **Manual Moderation**: Block email, IP, or specific keywords manually.  
- **Privacy Features**:  
  - `Do not analyze IP addresses`  
  - `Do not analyze Emails`  
  - `Remove sensitive information from messages`  
- **Form Spam Entries Management**: View, delete, send submissions to website admins, or report them.  
- **Form Ham Entries Management**: View non-spam entries, delete, or report them.  
- **Rate Limiting**: Control submission rates to prevent abuse and click frauds.
- **IP Filtering**: Block submissions from Cloud Providers and VPNs.


The value we bring:

* Fast, Lightweight & Accessible
* No cookies, no challenges, no JavaScript, no tracking.
* High accuracy (%99.9)
* Use one API key across unlimited websites.
* No data stored on our servers. All your data is stored in your local WordPress database.
* Privacy by design
* Thoroughly tested to ensure compatibility and stability with every update.
* Operated by a company with an active vulnerability disclosure program.
* Enjoy 24-hour response times for any assistance you need.


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
- Jetpack Forms
- MC4WP: Mailchimp for WordPress
- SureForms


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
= 1.2.34 =
* **FIX:** Ensure sessions are initiated and terminated correctly only when the 'Minimum Time Between Page Load and Submission (in seconds)' setting is active.
= 1.2.33 =
* **NEW:** Introduced a new setting: 'Rate Limiting -> Minimum Time Between Page Load and Submission'
* **IMPROVEMENT:** Excluded rate limiting from internal search spam protection
* **IMPROVEMENT:** [Breakdance] Disabled email notifications for detected spam submissions
= 1.2.31 =
* **NEW:** [WooCommerce] Added "Payment methods to check origin" setting to restrict origin checks to selected payment methods.
* **NEW:** Automatically report comments as spam or ham to OOPSpam when flagged within the WordPress comment system.
* **NEW:** Introduced "Disable local logging" setting to disable logging in the Form Spam and Form Ham Entries tables.
* **NEW:** Added global settings for "Log submissions to OOPSpam" and "Disable local logging" using constants:
  - `define('OOPSPAM_DISABLE_LOCAL_LOGGING', true);`
  - `define('OOPSPAM_ENABLE_REMOTE_LOGGING', true);`
* **IMPROVEMENT:** Enhanced Form Spam Entries table to display submissions not analyzed due to rate limiting or API errors.
* **IMPROVEMENT:** Removed the review request notice for a cleaner user experience. (But please consider leaving a review <3)
* **IMPROVEMENT:** [SureForms] Added support for custom messages.
* **IMPROVEMENT:** [Gravity Forms] Replaced anonymous functions with named functions for better integration support.
= 1.2.29 =
* **NEW:** Added support for Multi-site/Network installations
* **NEW:** Added the ability to filter Form Spam Entries by detection reason
* **IMPROVEMENT:** Manually blocked IPs and emails now take precedence over manually allowed ones
* **FIX:** Prevented storing password field values in logs during WooCommerce registration
= 1.2.28 =
* **NEW:** Added IP Filtering options to block VPNs and Cloud Providers
* **NEW:** Ability to define the global API key in wp-config.php using `define( 'OOPSPAM_API_KEY', 'YOUR_KEY' )`
* **IMPROVEMENT:** Added quick links to "Add countries in Africa" & "Add countries in the EU" in the country blocking settings
* **IMPROVEMENT:** Enhanced IP detection for WordPress comments
* **FIX:** Resolved issue with textarea field detection in Fluent Forms
* **FIX:** Fixed array validation issue
= 1.2.27 =
* **NEW:** Added North Korea to the list of supported countries
* **IMPROVEMENT:** [WooCommerce] Enhanced blocking of orders from unknown origins for both the Legacy API and the classic checkout
* **IMPROVEMENT:** [Kadence] Prevented email notifications in the Kadence Advanced Form Block
* **FIX:** Resolved error occurring during rate limiting deactivation
= 1.2.26 =
* **NEW:** Added integration support for SureForms plugin
* **NEW:** [WooCommerce] Added option to toggle honeypot field protection
* **IMPROVEMENT:** [Fluent Forms] Implemented more reliable IP address detection
* **FIX:** Added fallback handling for missing API request headers
= 1.2.25 =
* **IMPROVEMENT:** [Gravity Forms] Enhanced method for capturing user's IP address
* **FIX:** Resolved conflict with Breakdance
= 1.2.24 =
* **IMPROVEMENT:** Enhanced method to prevent naming collisions with other plugins
* **IMPROVEMENT:** [Jetpack Forms] Spam submissions are now categorized under Feedback->Spam
* **IMPROVEMENT:** [Jetpack Forms] Improved handling of `textarea` fields
* **FIX:** [Gravity Forms] Privacy settings were not being respected
= 1.2.23 =
* **NEW:** Added a new rate-limiting setting: "Restrict submissions per Google Ads lead"
= 1.2.22 =
* **NEW:** Added support for MC4WP: Mailchimp for WordPress
* **FIX:** Added prefixes to functions to prevent conflicts with other plugins
= 1.2.21 =
- **IMPROVEMENT:** [WooCommerce] Exclude honeypot field detection when allowed in Manual Moderation settings.  
- **IMPROVEMENT:** [WooCommerce] Enhanced honeypot field functionality for better accuracy.  
- **IMPROVEMENT:** Form Spam and Ham Entries tables now display the country name associated with an IP address.  
- **IMPROVEMENT:** Minor UX enhancements for Allowed and Blocked Country settings.  
= 1.2.20 =
* **NEW:** Added support for Jetpack Form
* **IMPROVEMENT:** Form Spam and Ham Entries tables now delete entries older than the selected interval instead of completely clearing the entire table
= 1.2.19 =
* **IMPROVEMENT:** Extended WS Form support to include the Lite version
* **FIX:** Removed an unnecessary query during the rate limit table creation
= 1.2.18 =
* NEW: [WooCommerce] "Block orders from unknown origin" setting for the Block Checkout
= 1.2.17 =
* NEW: Added bulk reporting functionality for both Form Spam Entries and Form Ham Entries tables
* IMPROVEMENT: [WooCommerce] Enhanced detection of spam targeting the WooCommerce Block Checkout
* IMPROVEMENT: Resolved layout shifts caused by notices from other plugins
* IMPROVEMENT: [WooCommerce] Removed first name validation to prevent false positives
= 1.2.16 =
* NEW: Rate limiting for submissions per IP and email per hour
* NEW: [Forminator] Specify content field by Form ID and Field ID pair
* NEW: [Forminator] Combine multiple field values for the `The main content field` setting
* IMPROVEMENT: [GiveWP] Reject donations with invalid payment gateways
* IMPROVEMENT: Enhanced honeypot implementation in WooCommerce
* IMPROVEMENT: Use WooCommerce’s internal function for IP detection
* IMPROVEMENT: Improved formatting and added more data to admin email notifications
* IMPROVEMENT: Added Sucuri proxy header support in IP detection
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
4. Rate Limiting settings
5. Privacy settings