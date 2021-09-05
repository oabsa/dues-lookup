<?php
/*
 * Plugin Name: OA Dues Lookup
 * Plugin URI: https://github.com/oabsa/dues-lookup/
 * Description: Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues
 * Version: 2.1.3
 * Requires at least: 3.0.1
 * Requires PHP: 7.1
 * Author: Dave Miller
 * Author URI: http://twitter.com/justdavemiller
 * Author Email: github@justdave.net
 * GitHub Plugin URI: https://github.com/oabsa/dues-lookup
 * Primary Branch: main
 * Release Asset: true
 * */

/*
 * Copyright (C) 2014-2019 David D. Miller
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

include_once( __DIR__ . '/vendor/autoload.php' );
WP_Dependency_Installer::instance()->run( __DIR__ );
add_action('admin_menu', 'oadueslookup_plugin_menu');
add_action('plugins_loaded', 'oadueslookup_update_db_check');
add_action('wp_loaded', 'oadueslookup_update_shortcodes');
register_activation_hook(__FILE__, 'oadueslookup_install');
register_activation_hook(__FILE__, 'oadueslookup_install_data');
add_action('wp_enqueue_scripts', 'oadueslookup_enqueue_css');

function oadueslookup_enqueue_css()
{
    wp_register_style('oadueslookup-style', plugins_url('style.css', __FILE__));
    wp_enqueue_style('oadueslookup-style');
}

global $oadueslookup_db_version;
$oadueslookup_db_version = 3;

function oadueslookup_create_table($ddl)
{
    global $wpdb;
    $table = "";
    if (preg_match("/create table\s+(\w+)\s/i", $ddl, $match)) {
        $table = $match[1];
    } else {
        return false;
    }
    foreach ($wpdb->get_col("SHOW TABLES", 0) as $tbl) {
        if ($tbl == $table) {
            return true;
        }
    }
    // if we get here it doesn't exist yet, so create it
    $wpdb->query($ddl);
    // check if it worked
    foreach ($wpdb->get_col("SHOW TABLES", 0) as $tbl) {
        if ($tbl == $table) {
            return true;
        }
    }
    return false;
}

function oadueslookup_install()
{
    /* Reference: http://codex.wordpress.org/Creating_Tables_with_Plugins */

    global $wpdb;
    global $oadueslookup_db_version;

    $dbprefix = $wpdb->prefix . "oalm_";

    //
    // CREATE THE TABLES IF THEY DON'T EXIST
    //

    // This code checks if each table exists, and creates it if it doesn't.
    // No checks are made that the DDL for the table actually matches,
    // only if it doesn't exist yet. If the columns or indexes need to
    // change it'll need update code (see below).

    $sql = "CREATE TABLE ${dbprefix}dues_data (
  bsaid                 INT NOT NULL,
  max_dues_year         VARCHAR(4),
  dues_paid_date        DATE,
  level                 VARCHAR(12),
  bsa_reg               TINYINT(1),
  bsa_reg_overridden    TINYINT(1),
  bsa_verify_date       DATE,
  bsa_verify_status     VARCHAR(50),
  PRIMARY KEY (bsaid)
);";
    oadueslookup_create_table($sql);

    //
    // DATABASE UPDATE CODE
    //

    // Check the stored database schema version and compare it to the version
    // required for this version of the plugin.  Run any SQL updates required
    // to bring the DB schema into compliance with the current version.
    // If new tables are created, you don't need to do anything about that
    // here, since the table code above takes care of that.  All that needs
    // to be done here is to make any required changes to existing tables.
    // Don't forget that any changes made here also need to be made to the DDL
    // for the tables above.

    $installed_version = get_option("oadueslookup_db_version");
    if (empty($installed_version)) {
        // if we get here, it's a new install, and the schema will be correct
        // from the initialization of the tables above, so make it the
        // current version so we don't run any update code.
        $installed_version = $oadueslookup_db_version;
        add_option("oadueslookup_db_version", $oadueslookup_db_version);
    }

    if ($installed_version < 2) {
        # Add a column for the Last Audit Date field
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN reg_audit_date DATE");
    }

    if ($installed_version < 3) {
        # Drop the old registration audit fields for OALM 4.1.2 or below.
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data DROP COLUMN reg_audit_date");
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data DROP COLUMN reg_audit_result");
        # Add the columns for the BSA registration fields in OALM 4.2.0 and above.
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN bsa_reg TINYINT(1)");
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN bsa_reg_overridden TINYINT(1)");
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN bsa_verify_date DATE");
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN bsa_verify_status VARCHAR(50)");
    }

    // insert next database revision update code immediately above this line.
    // don't forget to increment $oadueslookup_db_version at the top of the file.

    if ($installed_version < $oadueslookup_db_version) {
        // updates are done, update the schema version to say we did them
        update_option("oadueslookup_db_version", $oadueslookup_db_version);
    }
}

function oadueslookup_update_db_check()
{
    global $oadueslookup_db_version;
    if (get_site_option("oadueslookup_db_version") != $oadueslookup_db_version) {
        oadueslookup_install();
    }
    # do these here instead of in the starting data insert code because these
    # need to be created if they don't exist when the plugin gets upgraded,
    # too, not just on a new install.  add_option does nothing if the option
    # already exists, sets default value if it does not.
    add_option('oadueslookup_dues_url', 'http://www.example.tld/paydues');
    add_option('oadueslookup_dues_register', '1');
    add_option('oadueslookup_dues_register_msg', 'You must register and login on the MyCouncil site before paying dues.');
    add_option('oadueslookup_update_url', 'http://www.example.tld/paydues');
    add_option('oadueslookup_update_option_text', 'Update Contact Information');
    add_option('oadueslookup_update_option_link_text', 'dues form');
    add_option('oadueslookup_help_email', 'duesadmin@example.tld');
    add_option('oadueslookup_last_import', '1900-01-01');
    add_option('oadueslookup_last_update', '1900-01-01');
    add_option('oadueslookup_max_dues_year', '2016');

}

function oadueslookup_update_shortcodes()
{
    # In version 2.1, we replaced the URL trap with a shortcode.
    # This code converts from the old way to the new way.
    $lookup_slug = get_option('oadueslookup_slug', 'it was not set');
    if (!($lookup_slug === 'it was not set')) {
        $post = wp_insert_post(array(
            'post_type' => 'page',
            'post_name' => $lookup_slug,
            'post_status' => 'publish',
            'post_title' => 'OA Dues Lookup',
            'post_content' => "<!-- wp:shortcode -->\n" .
                              "[oadueslookup]\n" .
                              "<!-- /wp:shortcode -->\n"
        ));
        delete_option('oadueslookup_slug');
        add_option('oadueslookup_oldslug', $lookup_slug);
    }

}

function oadueslookup_insert_sample_data()
{
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    $wpdb->query("INSERT INTO ${dbprefix}dues_data " .
        "(bsaid,    max_dues_year, dues_paid_date, level,        bsa_reg,   bsa_reg_overridden, bsa_verify_date, bsa_verify_status) VALUES " .
        "('123453','2013',         '2012-11-15',   'Brotherhood','1',       '0',                '1900-01-01',   'BSA ID Not Found'), " .
        "('123454','2014',         '2013-12-28',   'Ordeal',     '1',       '0',                '1900-01-01',   'BSA ID Not Found'), " .
        "('123455','2014',         '2013-12-28',   'Brotherhood','1',       '0',                '1900-01-01',   'BSA ID Verified'), " .
        "('123456','2013',         '2013-07-15',   'Ordeal',     '1',       '0',                '1900-01-01',   'BSA ID Verified'), " .
        "('123457','2014',         '2013-12-18',   'Brotherhood','0',       '0',                '1900-01-01',   'BSA ID Found - Data Mismatch'), " .
        "('123458','2013',         '2013-03-15',   'Vigil',      '1',       '0',                '1900-01-01',   'BSA ID Not Found'), " .
        "('123459','2015',         '2014-03-15',   'Ordeal',     '0',       '0',                '1900-01-01',   'Never Run')");
    $wpdb->query($wpdb->prepare("UPDATE ${dbprefix}dues_data SET bsa_verify_date=%s", get_option('oadueslookup_last_update')));
}

function oadueslookup_install_data()
{
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    oadueslookup_insert_sample_data();
}

# Let admin users know about version 2.1 shortcode migration
add_action( 'admin_notices', 'oadueslookup_admin_notices' );
function oadueslookup_admin_notices() {
    $lookup_slug = get_option('oadueslookup_oldslug', 'it was not set');
    if (!($lookup_slug === 'it was not set')) {
        ?><div class="updated">
        <div>
        <p>Your OA Dues Lookup page at <a href="<?php echo esc_html(get_option("home")) . "/" . esc_html($lookup_slug) ?>"><?php echo esc_html(get_option("home")) . "/" . esc_html($lookup_slug) ?></a> was converted from a specially-handled URL to a real WordPress Page, which contains the <code>[oadueslookup]</code> shortcode for the form. You can now use that shortcode on any page to show the dues lookup form.</p>
        </div>
        <div style="float: right;"><a href="?dismiss=oadl_shortcode_update">Dismiss</a></div>
        <div style="clear: both;"></div>
        </div><?php
    }
}
add_action( 'admin_init', 'oadueslookup_dismiss_admin_notices' );
function oadueslookup_dismiss_admin_notices() {
    if ( array_key_exists( 'dismiss', $_GET ) && 'oadl_shortcode_update' === $_GET['dismiss'] ) {
        delete_option('oadueslookup_oldslug');
    }
}

## BEGIN OA TOOLS MENU CODE

# This code is designed to be used in any OA-related plugin. It conditionally
# Adds an "OA Tools" top-level menu in the WP Admin if it doesn't already
# exist. Any OA-related plugins can then add submenus to it.
# NOTE: if you copy this to another plugin, you also need to copy the
# referenced SVG file.

if (!function_exists('oa_tools_add_menu')) {
    add_action( 'admin_menu', 'oa_tools_add_menu', 9 );
    function oa_tools_add_menu() {
        $oa_tools_icon = file_get_contents("img/oa_trademark.svg", true);
        global $menu;
        $menu_exists = false;
        foreach($menu as $k => $item) {
            if ($item[2] == 'oa_tools') {
                $menu_exists = true;
            }
        }
        if (!$menu_exists) {
            add_menu_page( "OA Tools", "OA Tools", 'none', 'oa_tools', 'oadueslookup_tools_menu', 'data:image/svg+xml;base64,' . base64_encode($oa_tools_icon), 3 );
        }
    }
    function oadueslookup_tools_menu() {
        # this is a no-op, the page can be blank. It's going to go to the first
        # submenu anyway when it's picked.
    }
}

## END OA TOOLS MENU CODE

require_once("includes/user-facing-lookup-page.php");
require_once("includes/management-options-page.php");
require_once("includes/dues-import.php");
