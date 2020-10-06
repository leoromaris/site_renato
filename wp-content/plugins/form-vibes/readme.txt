=== Form Vibes - Database Manager for Forms ===
Contributors: wpvibes, webtechstreet, satishprajapati, sharukhajm
Tags: elementor, elementor form db, elementor db manager, lead capture, page-builder, contact form 7, contact form 7 db, caldera form, contact form, beaver builder form
Requires at least: 5.0
Tested up to: 5.4
Stable tag: trunk
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lead Capturing in Database and Graphical Reports for Elementor Pro, Contact Form 7 form submissions.

== Description ==

Form Vibes let's you save the form submissions form various form plugin in database. It also provide a graphical analytics report that allows you to visualize how different forms are performing over a period of time.

= Supported Plugins =
* Elementor Pro
* Contact Form 7
* Caldera Forms
* Beaver Builder
* More coming soon...

https://www.youtube.com/watch?v=n4EVJlnf4OE

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress

== Frequently Asked Questions ==
** Does it need any configuration **
No, it is plug 'n' play plugin. Just install the plugin and it will start capturing and displaying reports from support form plugins.
You can visit settings page to see some basic config options. 

== Screenshots ==

1. **Form Submission**
2. **Advanced Date Range Filtering**
3. **Graphical Report** Analyze the performance of the Form
4. **Graphical Report** Day, Month and Week based graph
5. **Gaphical Report with advanced date range filtering**




== Changelog ==

= 1.2.3 = 
* Fixed: Submissions not loading if field id is blank in Elementor Pro Forms. 

= 1.2.2 = 
* Fixed issue cause by log request uri on some servers.
* Fixed issue in caldera forms reporting.
* Modified some code to add compatibility with PHP 7.0

= 1.2.1 = 
* Fixed issue: Previous release was blocking admin notices.
* Fixed css issues.  

= 1.2 = 
* Code restructuring for better UX and performance
* Added option to refresh submission list without reloading
* Added option to refresh auto refresh list and analytics


= 1.1.3 =
* Fixed compatibility issues with WordPress 5.4
* Fixed issue with submission not visible in admin in some cases

= 1.1.2 =
* Tweak - Hide empty/non existing old forms from the Form list.
* Tweak - Some other UX improvements and code optimization.

= 1.1.1 =
* Fixed - Issue with database table create in plugin installation
* Fixed - Issue with csv export due to special characters in some languages
* Tweak - Added option to enable/disable Export Reason feature.

= 1.1.0 =
* New - Beaver Builder Form integration.
* Tweak - Added option to specify reason for exporting. Information about who exported the days and why will be save in database.
* Tweak - Added ability to save page url and user agent (optional).
* Tweak - Some other UX improvements and code optimization.

= 1.0.2 =
* Fixed - Elementor forms data not loading due to malformed json response.

= 1.0.1 =

* Fixed - Wrong column order in csv export
* Fixed - Issue with saving data of Elementor Global Form widgets
* Some other minor performance and ui enhancements.

= 1.0.0 =

* Added Dashboard Widget
* Added option to delete saved entries
* Added option to control whether to save IP Address or not

= 0.1.2 =

* Fixed issue with submissions not saving in some case. (Thanks to [@idatus](https://wordpress.org/support/users/idatus/))
* Fixed issue with saving multi data field like checkbox, radio field etc.

= 0.1.1 =

* Fixed issue in saving submissions with long text.

= 0.1.0 =

Initial Plugin Launch