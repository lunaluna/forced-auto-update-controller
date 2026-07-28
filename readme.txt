=== Forced Auto Update Controller ===
Contributors: lunaluna_dev
Tags: update, auto-update, automatic updates, git, version control
Requires at least: 6.0
Tested up to: 7.0.2
Stable tag: 1.6.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Forced Auto update Controller is a plugin that allows you to enable automatic updates only when they match the specified domain pattern, even under version control systems such as Git and SVN.

== Description ==

Sites managed under version control (Git, SVN, etc.) usually disable
WordPress's automatic updates, because core intentionally skips them for
VCS checkouts. That protects your deployed codebase, but it also means
security patches for core, plugins, and themes silently stop applying.

Forced Auto Update Controller lets you specify one or more production
domain patterns. When the current site matches, the plugin overrides the
VCS check so automatic updates function as WordPress intends — while
leaving every other environment (local, staging, etc.) untouched. You can
also exclude specific plugins or themes from automatic updates, and
optionally hide the WordPress core update notification once auto-updates
are confirmed to be working.

== Installation ==
1. Upload the `forced-auto-update-controller` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the \'Plugins\' menu in WordPress.

== Frequently Asked Questions ==

= Does this plugin support WordPress Multisite? =

No. Multisite is not officially supported. This plugin evaluates its domain
pattern per-site (via `home_url`/`get_option('home')`), but some of the
settings it controls (e.g. `auto_update_core_minor`) are network-wide
options. Activating this plugin on an individual subsite of a network can
therefore affect the entire network in unintended ways. Use at your own
risk in a Multisite environment.

== Changelog ==

= 1.6.3 =
* Security: added the `Update URI: false` plugin header to prevent supply-chain hijack via unregistered wp.org slug collision (see CVE-2021-44223 class of issue).

= 1.6.2 =
* Fixed: environment check on activation now aborts via `wp_die()` (previous self-deactivation was a no-op and its warning notice was never shown).
* Fixed: `..._hide_wp_updates` option is now deleted on uninstall.
* Hardened: excluded plugin/theme lists are now validated against installed plugins/themes.

= 1.6.1 =
* Simplified `control_auto_update_core()` by removing the `null`-return logic that was incorrectly based on a non-existent `wp_is_auto_update_forced_for_type()` call. The `auto_update_core` filter is only used by `WP_Automatic_Updater::should_update()` (background execution) and has no effect on the UI.
* Hardened `is_production_domain()`: comparison is now case-insensitive via `strtolower()`, and the validation regex now accepts port numbers and multi-level paths.
* `sanitize_domain_pattern()` now stores the pattern in lowercase for consistency.
* Added diagnostic info below the domain pattern field on the settings page, showing the detected site domain and whether it matches the saved pattern.

= 1.6.0 =
* Removed `allow_major_auto_core_updates` filter to fix the major/minor auto-update toggle link not appearing on the WordPress updates page.
* Added `pre_site_option_auto_update_core_minor` and `pre_option_auto_update_core_minor` filters to force `auto_update_core_minor` option to `enabled` when the domain pattern matches.

= 1.5.1 =
* Fixed the toggle to enable whether or not to major update the core on the WordPress update page using `allow_major_auto_core_updates` filter.

= 1.5.0 =
* Fixed the toggle to enable whether or not to major update the core on the WordPress update page using `allow_major_auto_core_updates` filter.

= 1.4.0 =
* Fixed the toggle to enable whether or not to major update the core on the WordPress update page.
* Rename the file

= 1.3.0 =
* Fixed the toggle to enable whether or not to major update the core on the WordPress update page.

= 1.2.0 =
* Fixed the toggle to enable whether or not to major update the core on the WordPress update page.

= 1.1.5 =
* Added a checkbox to hide update information on the dashboard.

= 1.1.4 =
* Fixed a bug that prevented automatic updates from being enabled when the public URL contained a subdirectory.

= 1.1.3 =
* Added a settings page's link to the plugin actions.

= 1.1.2 =
* Updated texts and meta boxes.

= 1.1.1 =
* Fixed correct deletion of data when uninstalling this plugin.

= 1.1.0 =
* Added a function that allows you to select plugins and themes that are not subject to automatic updates.

= 1.0.4 =
* Fixed the hook name.

= 1.0.3 =
* Added a GitHub link to the plugin metadata.

= 1.0.2 =
* Fixed a fatal error.

= 1.0.1 =
* Added metabox to administrators' Dashboard.
* Some code format.

= 1.0.0 =
* Initial release.
