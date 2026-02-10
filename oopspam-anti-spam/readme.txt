=== OOPSpam Anti-Spam: Spam Protection for WordPress Forms & Comments (No CAPTCHA) ===
Contributors: oopspam
Link: https://www.oopspam.com/
Tags: anti-spam, form protection, security, contact forms, spam blocker
Requires at least: 3.6
Tested up to: 6.9
Stable tag: 1.2.62
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Protect your forms from spam with 99.9% accuracy - no CAPTCHA, no JavaScript, no tracking. Trusted by 3.5M+ websites.

== Description ==
[OOPSpam](https://www.oopspam.com/) is a modern anti-spam solution that uses advanced AI and machine learning to protect your WordPress forms and comments from spam. Our system has blocked over 1 billion spam attempts across 3.5M+ websites, maintaining 99.9% accuracy without compromising user privacy or accessibility.

Unlike traditional CAPTCHA solutions that can hurt your conversion rates, OOPSpam works silently in the background, analyzing submissions against our extensive database of 500M+ malicious IPs and emails to catch both bot and human spammers. 


### Why Choose OOPSpam?

**🚀 Zero Impact on User Experience**
- No CAPTCHA puzzles or challenges that hurt conversions
- Works silently in the background without JavaScript or tracking
- Maintains fast website performance with server-side processing

**🛡️ Intelligent Spam Prevention**
- Catch 99.9% of spam using advanced machine learning and contextual analysis
- Protect against both automated bots and human spammers
- Auto-detect spam patterns unique to your website's context
- Block spam from VPNs and known malicious sources
- Prevent WooCommerce card testing attacks with advanced checkout protection

**🔒 Privacy-First Design**
- GDPR-compliant with no data stored on our servers
- Optional IP and email analysis for maximum privacy
- All logs stored in your WordPress database
- Remove sensitive information from messages automatically

**🌍 Smart Geographic Controls**
- Target your relevant market by blocking specific countries
- Filter submissions by language to focus on your audience
- Prevent fraud and abuse from high-risk regions

**⚙️ Powerful Management Tools**
- View and manage spam entries with detailed detection reasons
- Export data for analysis or reporting
- Rate limiting to prevent abuse and click fraud
- Manual override options for complete control

**🏢 Perfect for Agencies**
- Use one API key across unlimited websites
- Centralized logging option in OOPSpam dashboard
- Consistent protection across all your client sites


### What Our Users Say

> "It's eliminated all spam, and even the need for CAPTCHA. Setup is quick and the interface is intuitive." - @gotmick

> "Very responsive support and dev team. Customer support was amazing, response time was immediate and issues were solved instantly." - @viv18germany

> "Pricing is perfect for agencies as they do tiers by actual # of API calls and no limit on the number of sites you can install this on." - @squarecandy

### By the Numbers
- **3.5M+** websites protected daily
- **1B+** spam attempts blocked
- **99.9%** detection accuracy
- **24/7** customer support
- **500M+** malicious IPs and emails in our database

### Works With Everything
The plugin seamlessly protects your **comments**, **site search**, and **all major form plugins**. No extra configuration needed - it just works!

### Supported form & comment solutions:

- WooCommerce Order & Registration
- BuddyPress
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
- SureCart
- QuForm
- HappyForms Pro
- Avada Forms
- MetForm
- ACF Frontend Forms


OOPSpam Anti-Spam WordPress plugin requires minimal configuration. Check out our [comprehensive WordPress guide](https://help.oopspam.com/wordpress/) for detailed setup instructions. To get started quickly, [get a key](https://app.oopspam.com/Identity/Account/Register) and paste it into the appropriate setting field under _Settings=>OOPSpam Anti-Spam_. If you have a contact form plugin, make sure you enable spam protection on the settings page.

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

After activating the plugin, follow these quick setup steps:

1. Register on the [OOPSpam Dashboard](https://app.oopspam.com/) and copy your API key
2. Go to _OOPSpam Anti-Spam=>Settings_ in your WordPress dashboard
3. Paste the key into the "My API Key" field
4. Select "OOPSpam Dashboard" from the "I got my API Key from" setting
5. If you're using a contact form plugin, make sure the "Activate Spam Protection" option is checked for that plugin

That's it! Your forms are now protected from spam. The plugin works automatically in the background with no additional configuration needed.

For advanced configuration options and detailed usage instructions, visit our [WordPress documentation](https://help.oopspam.com/wordpress/).

== Changelog ==
= 1.2.62 =
* **NEW:** [WooCommerce] Added "Block orders with specific billing addresses" setting to block orders matching known fraudulent addresses
* **FIX:** [WooCommerce] Fixed JSON parse error ("SyntaxError: Unexpected token") on block-based checkout when an order is blocked
= 1.2.61 =
* **IMPROVEMENT:** Better contextual spam detection guide
* **IMPROVEMENT:** New, clearer settings page design
* **IMPROVEMENT:** Simplified setting names for easier understanding
= 1.2.60 =
* **NEW:** Added notice to encourage users behind proxies (e.g., Cloudflare) to enable "Trust proxy headers" for accurate IP detection
* **IMPROVEMENT:** Better metadata in local logging for easier debugging
* **IMPROVEMENT:** Display clear reason for the API error
= 1.2.59 =
* **IMPROVEMENT:** Wrapped all debug error_log() calls in WP_DEBUG checks for better production performance
* **IMPROVEMENT:** Replaced wp_redirect() with wp_safe_redirect() for better security compliance
* **FIX:** Addressed "too many requests" errors when the API key is not provided
= 1.2.58 =
* **NEW:** [WooCommerce] Order origin checks now respect Manual Moderation -> Allowed IPs settings
* **IMPROVEMENT:** [WooCommerce] "Block orders with specific total amounts" setting now accepts multiple amounts (one per line)
* **FIX:** [WooCommerce] Prevented duplicate spam entries when blocking orders with specific total amounts
= 1.2.57 =
* **FIX:** Prevented rate limiting notices from appearing after upgrade
= 1.2.56 =
* **IMPROVEMENT:** Added missing countries to Blocked and Allowed Countries lists
* **IMPROVEMENT:** Manual Moderation settings now take precedence over rate limiting
* **FIX:** General code quality improvements and bug fixes
= 1.2.55 =
* **FIX:** Fixed "Do not analyze IP addresses" and "Do not analyze Email addresses" settings not working
* **FIX:** [Ninja Forms] Fixed spam error message not displaying
= 1.2.54 =
* **NEW:** Added support for ACF Frontend Forms
* **NEW:** Added "Email admin when marked as not spam" setting
* **NEW:** Added "Trust proxy headers" setting (required for proper IP detection behind proxies)
* **FIX:** Enhanced security to prevent IP spoofing attacks - requires new "Trust proxy headers" setting
* **FIX:** All the rate limiting features now require "Enable rate limiting" to be enabled
= 1.2.53 =
* **NEW:** Added support for MetForm
* **IMPROVEMENT:** [Forminator] Stop other actions from running when a spam detected
= 1.2.52 =
* **NEW:** Added support for Avada Forms
* **IMPROVEMENT:** Added missing Caribbean countries to the country lists.
* **IMPROVEMENT:** UX improvements to the Setup Wizard.
= 1.2.51 =
* **NEW:** [WooCommerce] Added "Block orders with specific total amount" setting to prevent card testing on older WooCommerce versions
* **FIX:** [WooCommerce] Stop payment processing immediately when validation fails
= 1.2.50 =
* **NEW:** Added "Refresh" button to manually update API usage statistics
* **NEW:** Added support for BuddyPress registration forms
* **IMPROVEMENT:** [WooCommerce] Enhanced order attribution validation to prevent bypass attempts in Classic Checkout
= 1.2.49 =
* **FIX:** Fixed issue where setup wizard would appear even when API key was already configured
= 1.2.48 =
* **NEW:** Added interactive setup wizard for easier configuration
* **NEW:** [WooCommerce] Added "Disable WooCommerce checkout via REST API" setting to prevent API-based attacks
* **NEW:** [WooCommerce] Added "Minimum session page views" setting to enhance protection against card testing
* **NEW:** [WooCommerce] Added "Require valid device type" setting to enhance protection against card testing
* **IMPROVEMENT:** Added support for IP ranges in "Manual Moderation -> Blocked IPs" setting (e.g., 192.168.1.0/24)
* **IMPROVEMENT:** Renamed "Form Ham Entries" to "Valid Entries" for better clarity
* **IMPROVEMENT:** Verified compatibility with PHP 8.4 and fixed API usage update issues
* **IMPROVEMENT:** [WooCommerce] Enhanced Order Attribution checks for more accurate spam detection
* **FIX:** Spam checks now skip customers with previously completed orders to prevent false positives
* **FIX:** Resolved issue where empty "Trusted Countries" setting incorrectly allowed submissions
= 1.2.47 =
* **NEW:** Added a new setting: "Trusted Countries (always bypasses spam checks)"
* **IMPROVEMENT:** WooCommerce orders now use our IP detection method to capture the real IP instead of proxy IPs
* **IMPROVEMENT:** UX improvements for geo-location settings
= 1.2.46 =
* **IMPROVEMENT:** Enhanced IP address detection for Elementor Forms integration
* **FIX:** Fixed custom spam messages not falling back to default when empty across all form integrations
= 1.2.45 =
* **NEW:** Added support for HappyForms
* **IMPROVEMENT:** Enhanced logging of block reasons in the comment system
= 1.2.44 =
* **NEW:** Added support for QuForm
* **FIX:** Prevent the pre-comment approval hook from executing multiple times
= 1.2.43 =
* **FIX:** [WooCommerce] Fixed undefined function error in scheduled payments by enforcing global namespace.
= 1.2.42 =
* **FIX:** [WooCommerce] Prevent Moneris payment details from being stored in raw logs.
= 1.2.41 =
* **NEW:** Added compatibility with the Gravity Forms Partial Entries Add-On.
= 1.2.40 =
* **FIX:** "The main content field ID (optional)" setting was not capturing multiple field data in Contact Form 7.
= 1.2.39 =
* **NEW:** Added support for SureCart
= 1.2.38 =
* **IMPROVEMENT:** Enhanced IP detection in the WordPress comment system to account for proxy usage
= 1.2.37 =
* **IMPROVEMENT:** Enhanced method for capturing user's IP address
= 1.2.36 =
* **NEW:** Introduced "Contextual Spam Detection" to analyze spam based on content and website context.
* **IMPROVEMENT:** Refined API usage metrics for tracking.
= 1.2.35 =
* **NEW:** Introduced the ability to filter Spam Entries by Form ID.
* **IMPROVEMENT:** Enhanced the user experience for displaying `Current usage`.
* **FIX:** Addressed issues with the `Delete` and `Email admin` actions in the Spam Entries table.
= 1.2.34 =
* **FIX:** Ensure sessions are initiated and terminated correctly only when the 'Minimum Time Between Page Load and Submission (in seconds)' setting is active.
= 1.2.33 =
* **NEW:** Introduced a new setting: 'Rate Limiting -> Minimum Time Between Page Load and Submission'
* **IMPROVEMENT:** Excluded rate limiting from internal search spam protection
* **IMPROVEMENT:** [Breakdance] Disabled email notifications for detected spam submissions
= 1.2.31 =
* **NEW:** [WooCommerce] Added "Payment methods to check origin" setting to restrict origin checks to selected payment methods.
* **NEW:** Automatically report comments as spam or ham to OOPSpam when flagged within the WordPress comment system.
* **NEW:** Introduced "Disable local logging" setting to disable logging in the Form Spam and Valid Entries tables.
* **NEW:** Added global settings for "Log submissions to OOPSpam" and "Disable local logging" using constants:
  - `define('OOPSPAM_DISABLE_LOCAL_LOGGING', true);`
  - `define('OOPSPAM_ENABLE_REMOTE_LOGGING', true);`
* **IMPROVEMENT:** Enhanced Spam Entries table to display submissions not analyzed due to rate limiting or API errors.
* **IMPROVEMENT:** Removed the review request notice for a cleaner user experience. (But please consider leaving a review <3)
* **IMPROVEMENT:** [SureForms] Added support for custom messages.
* **IMPROVEMENT:** [Gravity Forms] Replaced anonymous functions with named functions for better integration support.
= 1.2.29 =
* **NEW:** Added support for Multi-site/Network installations
* **NEW:** Added the ability to filter Spam Entries by detection reason
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
* NEW: Added bulk reporting functionality for both Spam Entries and Valid Entries tables
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
* NEW: View spam detection reasons in the Spam Entries table
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
* IMPROVEMENT: Truncate long messages in Valid Entries and Spam Entries tables
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
* FIX: Error when "Not Spam" is used in the Spam Entries table
= 1.2.4 =
* NEW: "Block disposable emails" setting
* FIX: Broken "Move spam comments to" setting
= 1.2.3 =
* NEW: Basic HTML support for error messages in all integrations
* NEW: Ability to set multiple recipients for `Email Admin` in the Spam Entries table
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
* NEW: `Export CSV` in Spam Entries & Valid Entries tables
* IMPROVEMENT: More reliable IP detection
* IMPROVEMENT: Confirmation prompt before emptying Ham and Spam Entries table
* IMPROVEMENT: Improved styling of the settings page
* IMPROVEMENT: Hide `Blocked countries` when `Do not analyze IP addresses` is enabled
= 1.1.61 =
* NEW: `Manual moderation` setting to manually block email, IP and exact keyword.
* NEW: `Email admin` setting under `Spam Entries` to send submission data to the website admin
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


== Frequently Asked Questions ==

= How does OOPSpam compare to Akismet? =

While Akismet focuses primarily on comment spam, OOPSpam offers comprehensive protection for all forms of submissions:
- Protects ALL form types (comments, contact forms, registration, etc.) out of the box
- No need to share user data with third parties
- Includes country blocking and language filtering
- Provides detailed analytics and reporting
- Works silently without impacting user experience
- One API key works across unlimited websites

= How does OOPSpam compare to CleanTalk? =

OOPSpam offers several advantages over CleanTalk:
- Higher accuracy (99.9%) with advanced machine learning
- No JavaScript required, improving site performance
- Better privacy with optional IP/email analysis
- More granular controls for geographic restrictions
- Unlimited websites with one API key
- 24/7 responsive support

= Do I need to solve CAPTCHA or other challenges? =

No! OOPSpam works completely in the background without any user interaction required. This means:
- No puzzles or challenges
- No impact on conversion rates
- Full accessibility compliance
- Better user experience

= How many API calls do I need? =

1 API call = 1 spam check

When someone submits a form on your website, that counts as one API call. The free plan includes 40 calls to test the service. Here's a general guide:
- The Freelance plan (100,000 API calls) typically covers ~100 websites
- Average website gets 20-50 form submissions daily
- To estimate your needs, count your monthly form submissions across all your websites

Contact us if you need help estimating your needs.

= Is OOPSpam GDPR compliant? =

Yes! OOPSpam is fully GDPR compliant:
- No data stored on our servers by default
- Optional IP and email analysis
- All logs stored in your WordPress database
- Ability to remove sensitive information from messages

== Screenshots ==

1. **Easy-to-Use Dashboard** - Access all spam protection features in one organized place
2. **Spam Management** - View and manage spam with clear explanations of why each entry was blocked
3. **Manual Controls** - Block specific IPs, emails, and keywords when needed
4. **Rate Limiting** - Stop spam bots by limiting how often forms can be submitted
5. **Privacy Controls** - Choose how your data is handled and stored
6. **One-Click Setup** - Enable protection for your forms and WooCommerce store instantly
7. **IP Blocking** - Block VPNs and cloud providers with a single click
8. **Contextual Spam Detection** - Use AI to identify spam based on your website's content (optional)