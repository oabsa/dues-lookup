<?php
/*
 * Plugin Name: OA Dues Lookup
 * Plugin URI: https://github.com/justdave/oadueslookup
 * Description: Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues
 * Version: 1.0
 * Author: Dave Miller
 * Author URI: http://twitter.com/justdavemiller
 * Author Email: github@justdave.net
 * */

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 David D. Miller
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

add_action( 'admin_menu', 'oadueslookup_plugin_menu' );
register_activation_hook( __FILE__, 'oadueslookup_install' );

global $oadueslookup_db_version;
$oadueslookup_db_version = 1;

function oadueslookup_create_table($ddl) {
    global $wpdb;
    $table = "";
    if (preg_match("/create table\s+(\w+)\s/i", $ddl, $match)) {
        $table = $match[1];
    } else {
        return false;
    }
    foreach ($wpdb->get_col("SHOW TABLES",0) as $tbl ) {
        if ($tbl == $table) {
            return true;
        }
    }
    // if we get here it doesn't exist yet, so create it
    $wpdb->query($ddl);
    // check if it worked
    foreach ($wpdb->get_col("SHOW TABLES",0) as $tbl ) {
        if ($tbl == $table) {
            return true;
        }
    }
    return false;
}

function oadueslookup_install() {
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

    $sql = "CREATE TABLE ${dbprefix}episode (
  bsaid            MEDIUMINT(10) NOT NULL,
  max_dues_year    VARCHAR(4),
  dues_paid_date   DATE,
  level            VARCHAR(12),
  reg_audit_result VARCHAR(15),
  PRIMARY KEY (bsaid)
);";
    oadueslookup_create_table( $sql );

    //
    // DATABSE UPDATE CODE
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
        add_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }

    // insert next database revision update code immediately above this line.
    // don't forget to increment $oadueslookup_db_version at the top of the file.

    if ($installed_version < $oadueslookup_db_version ) {
        // updates are done, update the schema version to say we did them
        update_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }
}

function oadueslookup_plugin_menu() {
    add_options_page( 'OA Dues Lookup', 'OA Dues Lookup', 'manage_options', 'oadueslookup', 'oadueslookup_options' );
}

function oadueslookup_options() {

    global $wpdb;

    $dbprefix = $wpdb->prefix . "oalm_";
    $hidden_field_name = 'oalm_submit_hidden';

    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    // =========================
    // form processing code here
    // =========================


    // ============================
    // screens and forms start here
    // ============================

    //
    // MAIN SETTINGS SCREEN
    //

    echo '<div class="wrap">';

    // header

    echo "<h2>" . __( 'OA Dues Lookup Settings', 'oadueslookup' ) . "</h2>";

    // settings form

    echo "<p>This is a settings page placeholder.</p>";

    echo "</div>";
} // END OF SETTINGS SCREEN

