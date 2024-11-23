#### 2.5.1 / 2024-11-22

* Security updates to embedded spreadsheet parser

#### 2.5 / 2024-10-16

* Page load performance improvements
* Security updates to embedded spreadsheet parser

#### 2.4 / 2024-02-26

* PHP 8.2 compatibility fixes
* PHP 8.0 minimum requirement
* Get the description and changelog working in the update/details dialogs. For whatever reason the remote file is only checked if it already exists locally, so the benefit from doing this will take 2 releases to show up. Once it does, the Description and Changes tabs in the plugin details popup will populate correctly.

#### 2.3 / 2022-02-10

* Processing imported dues data is now done in a temporary table in the database, and only copied to the live lookup table after all of the data has been successfully processed. This way if someone tries to do a lookup while the data is still processing, it will actually work.

#### 2.2 / 2021-12-09

* Moved configuration and import to separate menu items within the new OA Tools top-level menu instead of in Settings. The OA Tools menu is designed to be portable so any OA-related WordPress plugins can make use of it.
* The Dues Import screen now features a drag-n-drop upload widget, so you can drag your xlsx file out of a Finder/Explorer window onto it to upload (or just click to get a filepicker like you used to).
* There is now a progress widget which will show you the upload progress and then the processing progress while the import file is processed. No more sitting there watching the loading indicator spin on your browser wondering how far along it is!
* Now supports setting up a cron job to do the import processing in the background outside of the web server process. Since many web hosting providers have a 30 second execution limit for PHP, this may be necessary if your lodge's membership is large enough that the processing always gets killed off by the server before it finishes. See the bottom of the Dues Lookup Settings page for details.
* Now requires PHP 7.3 due to upstream dependencies.

#### 2.1.3 / 2021-06-19

* Git Updater was choking on the primary branch not being "master" (it's "main") in our git repo.
* You may have to download this update manually because of this, but it should work from now on.

#### 2.1.2 / 2021-06-06

* Fixes a dependency issue with GitHub Updater (it was renamed to Git Updater)
* Update to newer PHPSpreadsheet library

#### 2.1.1 / 2020-04-08

* Fixes a bug in the upgrade process from 2.0.0->2.1.0 where the newly created page was created as a draft instead of published (if you already upgraded to 2.1.0 this won't have any effect and the damage was already done)

#### 2.1.0 / 2020-03-13

* Lots of code cleanup on the back end.
* Changed the update mechanism to use GitHub Updater instead of Autohosted.
* Uses a shortcode to place the dues lookup form instead of creating a fake page at a configured URL. A page containing the shortcode at the URL you previously configured will automatically be created when upgrading from an older version.

#### 2.0.0 / 2019-02-17

* Updated to support the BSA registration and verification fields in OA Lodgemaster 4.2.0 and later.
* Convert from PHPExcel (which has been discontinued and is no longer
  supported) to its successor project PhpSpreadsheet

#### 1.0.7 / 2014-12-14

* Added Nataepu Shohpe-specific message that if your ID number wasn't found
  either your dues aren't paid or we have your ID wrong, since we have
  everyone's IDs now.  This really needs to be a config setting.
* Added option to include instructions for having to register before paying dues.
* Added separate Update Contact Information URL to add flexibility to other lodge's workflows.
* Replaced `<b>` elements with `<strong>` elements.
* Added message with last updated date to the "ID Not Found" response.

#### 1.0.6 / 2014-08-13

* Fix XLSX import to address export format changes in OA LodgeMaster 3.3.0.
  Make sure to check the "How to export" instructions on the wiki (linked from
  the admin page where you upload) as the export instructions from OALM have
  changed as well.

#### 1.0.5 / 2014-05-08

* Tweaks to the results text to be more friendly.

#### 1.0.4 / 2014-04-26

* Use the import date for the "registration audit date" in the sample data when creating/recreating it
* Add the contact info update link to the initial lookup page

#### 1.0.3 / 2014-04-20

* Relicensed to GPL to allow using GPL libraries
* Hook up plugin update system so Wordpress will tell you when there's a new version
* Show most-recently-recorded dues payment as the "Database Updated" date shown to end users, to avoid having data exported from OALM after a dues payment that hasn't been recorded in OALM yet has been made causing it to look like a dues payment got lost.
* Make online dues payment link say "Click here" so people realize it's a link.

#### 1.0.2 / 2014-04-18

* Trim spaces around submitted BSA Member IDs
* Use the correct URL from the Wordpress config for the part in front of the
  Dues Page Slug on the admin page
* Fix description for "No Match Found" audit status not showing up

#### 1.0.1 / 2014-04-18

* fix a conflict with some themes

#### 1.0 / 2014-04-17

* Initial release.
