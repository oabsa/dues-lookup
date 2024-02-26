# OA Dues Lookup

*Contributors:* Dave Miller, Steven Hall, Eric Silva

*Tags:* Order of the Arrow, BSA, OA, dues, Boy Scouts

## Wordpress Compatibility

*Requires at least:* 3.0.1
*Tested up to:* 5.9

## PHP Compatibility

*Requires at least:* 8.0.x
*Tested up to:* 8.2.x

*License:* GPLv2 or later

*License URI:* http://www.gnu.org/licenses/gpl-2.0.html

Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues.

## Description

Provides a lookup form to allow OA members to enter their BSA Member ID and find out whether their dues are current.

Makes use of data exported from OA LodgeMaster, which needs to be periodically imported into the plugin.

See the Github page for detailed instructions and to file bug reports.
https://github.com/oabsa/dues-lookup/wiki

This plugin embeds the [PhpSpreadsheet library](https://github.com/PHPOffice/PhpSpreadsheet) (and its dependencies) intact with no modifications, which is licensed separately under LGPL.

**NOTE: as of June 2021, LodgeMaster now offers a Member Portal in which members can see this on their profile. Depending on your desires, this plugin may no longer be needed.**

What this plugin still does that LodgeMaster's Member Portal does not yet do:
* Link to the site to pay your dues - [LodgeMaster Feature Request](https://oalodgemaster.featureupvote.com/suggestions/187770/add-pay-dues-link-to-existing-council-systems-in-member-portal)
* Show the member whether their BSA Registration is valid - [LodgeMaster Feature Request](https://oalodgemaster.featureupvote.com/suggestions/279184/add-bsa-registration-status-and-last-checked-date-to-profile-page-in-member-port)
* Allow someone other than the member to look up their dues status if they know their BSA ID (like Scoutmasters) -- LodgeMaster integration with the Internet Advancement System is supposed to be coming in the next year or so, then they'll be able to do that there.

## Installation

1. Place the unpacked "dues-lookup" directory in the "/wp-content/plugins/" directory.
1. Activate the plugin through the "Plugins" menu in WordPress.
1. Go to "OA Tools" > "Dues Lookup Settings" to find the settings
1. Go to "OA Tools" > "Import Dues" to upload data.
1. Put the `[oadueslookup]` shortcode on the page you want the lookup form to appear on.

**NOTE:** if you do a git check out from GitHub or download the source Zip file, you will need to run `composer install` inside the dues-lookup directory to install the dependencies before you can use it.

