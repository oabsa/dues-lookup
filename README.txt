=== OA Dues Lookup ===
Contributors: Dave Miller, Steven Hall, Eric Silva
Tags: order of the arrow, BSA, OA, dues, boy scouts
Requires at least: 3.0.1
Tested up to: 4.0.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues

== Description ==
Provides a lookup form to allow OA members to enter their BSA Member ID and find out whether their dues are current.

Makes use of data exported from OA LodgeMaster, which needs to be periodically imported into the plugin.

See the Github page for detailed instructions and to file bug reports.
https://github.com/oa-bsa/dues-lookup/wiki

This plugin embeds the PHPExcel library intact with no modifications, which is licensed separately under LGPL.  PHPExcel can be found at
https://github.com/PHPOffice/PHPExcel

== Installation ==

1. Place the unpacked "osdueslookup" directory in the "/wp-content/plugins/" directory.
1. Activate the plugin through the "Plugins" menu in WordPress.
1. Go to "OA Dues Lookup" under "Settings" to find the settings and to upload data.

== Changelog ==

= 1.0.7 =
* Added Nataepu Shohpe-specific message that if your ID number wasn't found
  either your dues aren't paid or we have your ID wrong, since we have
  everyone's IDs now.  This really needs to be a config setting.
* Added option to include instructions for having to register before paying dues.
* Added separate Update Contact Information URL to add flexibility to other lodge's workflows.
* Replaced `<b>` elements with `<strong>` elements.
* Added message with last updated date to the "ID Not Found" response.

= 1.0.6 =
* Fix XLSX import to address export format changes in OA LodgeMaster 3.3.0.
  Make sure to check the "How to export" instructions on the wiki (linked from
  the admin page where you upload) as the export instructions from OALM have
  changed as well.

= 1.0.5 =
* Tweaks to the results text to be more friendly.

= 1.0.4 =
* Use the import date for the "registration audit date" in the sample data when creating/recreating it
* Add the contact info update link to the initial lookup page

= 1.0.3 =
* Relicensed to GPL to allow using GPL libraries
* Hook up plugin update system so Wordpress will tell you when there's a new version
* Show most-recently-recorded dues payment as the "Database Updated" date shown to end users, to avoid having data exported from OALM after a dues payment that hasn't been recorded in OALM yet has been made causing it to look like a dues payment got lost.
* Make online dues payment link say "Click here" so people realize it's a link.

= 1.0.2 =
* Trim spaces around submitted BSA Member IDs
* Use the correct URL from the Wordpress config for the part in front of the
  Dues Page Slug on the admin page
* Fix description for "No Match Found" audit status not showing up

= 1.0.1 =
* fix a conflict with some themes

= 1.0 =
* Initial release.

== Upgrade Notice ==
= 1.0 =
* Initial release.
