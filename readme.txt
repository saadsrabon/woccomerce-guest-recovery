=== Guest Customer Recovery & Marketing Suite ===
Contributors: gcrmteam
Tags: woocommerce, guest customers, abandoned cart, email marketing, whatsapp
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce guest customer recovery, abandoned cart tracking, bulk email/WhatsApp marketing, segmentation, campaigns, workflows, and analytics.

== Description ==

* Guest customer database under WooCommerce menu
* CSV and Excel export
* Bulk email and WhatsApp campaigns
* Abandoned cart tracking and automated recovery
* Customer segments with AND/OR filters
* Campaign manager and marketing workflows
* Revenue intelligence dashboard with Chart.js
* GDPR export/erase support
* HPOS compatible

== Installation ==

1. Upload `guest-customer-recovery-marketing-suite` to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Ensure WooCommerce is active
4. Configure settings under WooCommerce → GCRM Settings

== Updates (GitHub Releases) ==

Default update source: https://github.com/saadsrabon/woccomerce-guest-recovery

1. Bump `Version` in the main plugin file and `GCRM_VERSION`.
2. Create a GitHub Release with tag `v1.0.x` and attach a zip whose root folder is `guest-customer-recovery-marketing-suite`.
3. Client sites check **WooCommerce → GCRM Settings → Check for updates** or wait for automatic checks.

See RELEASE.md in the plugin folder for full publish steps.

== Frequently Asked Questions ==

= Does this require Composer? =

No. The plugin uses a built-in PSR-4 autoloader and native CSV/XLSX export.

= Is HPOS supported? =

Yes. The plugin declares compatibility with WooCommerce custom order tables.

== Changelog ==

= 1.0.0 =
* Initial release
