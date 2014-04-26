<?php
/*
 * Plugin Name: OA Dues Lookup
 * Plugin URI: https://github.com/justdave/oadueslookup
 * Description: Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues
 * Version: 1.0.4
 * Author: Dave Miller
 * Author URI: http://twitter.com/justdavemiller
 * Author Email: github@justdave.net
 * */

/*
 * Copyright (C) 2014 David D. Miller
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

add_action( 'admin_menu', 'oadueslookup_plugin_menu' );
add_action( 'parse_request', 'oadueslookup_url_handler' );
add_action( 'plugins_loaded', 'oadueslookup_update_db_check' );
register_activation_hook( __FILE__, 'oadueslookup_install' );
register_activation_hook( __FILE__, 'oadueslookup_install_data' );
add_action( 'wp_enqueue_scripts', 'oadueslookup_enqueue_css' );
add_action( 'init', 'oadueslookup_plugin_updater_init' );

function oadueslookup_enqueue_css() {
    wp_register_style( 'oadueslookup-style', plugins_url('style.css', __FILE__) );
    wp_enqueue_style('oadueslookup-style');
}

function oadueslookup_plugin_updater_init() {
    /* Load Plugin Updater */
    require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'includes/plugin-updater.php' );

    /* Updater Config */
    $config = array(
        'base'      => plugin_basename( __FILE__ ), //required
        'repo_uri'  => 'http://www.justdave.net/dave/',
        'repo_slug' => 'oadueslookup',
    );

    /* Load Updater Class */
    new OADuesLookup_Plugin_Updater( $config );
}

global $oadueslookup_db_version;
$oadueslookup_db_version = 2;

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

    $sql = "CREATE TABLE ${dbprefix}dues_data (
  bsaid            INT NOT NULL,
  max_dues_year    VARCHAR(4),
  dues_paid_date   DATE,
  level            VARCHAR(12),
  reg_audit_date   DATE,
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

    if ($installed_version < 2) {
        # Add a column for the Last Audit Date field
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN reg_audit_date DATE");
    }

    // insert next database revision update code immediately above this line.
    // don't forget to increment $oadueslookup_db_version at the top of the file.

    if ($installed_version < $oadueslookup_db_version ) {
        // updates are done, update the schema version to say we did them
        update_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }
}

function oadueslookup_update_db_check() {
    global $oadueslookup_db_version;
    if (get_site_option( 'oadueslookup_db_version' ) != $oadueslookup_db_version) {
        oadueslookup_install();
    }
    # do these here instead of in the starting data insert code because these
    # need to be created if they don't exist when the plugin gets upgraded,
    # too, not just on a new install.  add_option does nothing if the option
    # already exists, sets default value if it does not.
    add_option('oadueslookup_slug', 'oadueslookup');
    add_option('oadueslookup_dues_url', 'http://www.example.tld/paydues');
    add_option('oadueslookup_help_email', 'duesadmin@example.tld');
    add_option('oadueslookup_last_import', '1900-01-01');
    add_option('oadueslookup_last_update', '1900-01-01');
}

function oadueslookup_insert_sample_data() {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    $wpdb->query("INSERT INTO ${dbprefix}dues_data " .
        "(bsaid,    max_dues_year, dues_paid_date, level,        reg_audit_date, reg_audit_result) VALUES " .
        "('123453','2013',         '2012-11-15',   'Brotherhood','1900-01-01',   'No Match Found'), " .
        "('123454','2014',         '2013-12-28',   'Ordeal',     '1900-01-01',   'Not Registered'), " .
        "('123455','2014',         '2013-12-28',   'Brotherhood','1900-01-01',   'Registered'), " .
        "('123456','2013',         '2013-07-15',   'Ordeal',     '1900-01-01',   'Registered'), " .
        "('123457','2014',         '2013-12-18',   'Brotherhood','1900-01-01',   'No Match Found'), " .
        "('123458','2013',         '2013-03-15',   'Vigil',      '1900-01-01',   'Not Registered'), " .
        "('123459','2015',         '2014-03-15',   'Ordeal',     '1900-01-01',   '')"
    );
    $wpdb->query($wpdb->prepare("UPDATE ${dbprefix}dues_data SET reg_audit_date=%s", get_option('oadueslookup_last_update')));
}

function oadueslookup_install_data() {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    oadueslookup_insert_sample_data();

}

function oadueslookup_user_page( &$wp ) {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    ob_start();
    if ( isset($_POST['bsaid']) ) {
        $bsaid = trim($_POST['bsaid']);
        if (preg_match('/^\d+$/', $bsaid)) {
            $results = $wpdb->get_row($wpdb->prepare("SELECT max_dues_year, dues_paid_date, level, reg_audit_date, reg_audit_result FROM ${dbprefix}dues_data WHERE bsaid = %d", array($bsaid)));
            if (!isset($results)) {
?>
<div class="oalm_dues_bad"><p>Your BSA Member ID <?php echo htmlspecialchars($bsaid) ?> was not found.</p></div>
<p>This can mean any of the following:</p>
<ul>
<li>You mistyped your ID</li>
<li>You are not a member of the lodge.</li>
<li><b>(most likely)</b> We don't have your BSA Member ID on your record or have the
incorrect ID on your record.</li>
</ul>
<p>You should fill out the "Update Contact Information Only" option on the <a
href="<?php echo get_option('oadueslookup_dues_url') ?>">Dues Form</a> and make sure to
supply your BSA Member ID on the form, then check back here in a week to see if
your status has updated.</p>
<?php
            } else {
                $max_dues_year = $results->max_dues_year;
                $dues_paid_date = $results->dues_paid_date;
                $level = $results->level;
                $reg_audit_date = $results->reg_audit_date;
                $reg_audit_result = $results->reg_audit_result;
                if ($reg_audit_result == "") { $reg_audit_result = "Not Checked"; }
?>
<table class="oalm_dues_table">
<tr><th>BSA Member ID</th><td class="oalm_value"><?php echo htmlspecialchars($bsaid) ?></td><td class="oalm_desc"></td></tr>
<tr><th>Dues Paid Thru</th><td class="oalm_value"><?php echo htmlspecialchars($max_dues_year) ?></td><td class="oalm_desc"><?php
                $thedate = getdate();
                if ($max_dues_year >= $thedate['year']) {
                    ?><span class="oalm_dues_good">Your dues are current.</span><?php
                    if (($reg_audit_result == "Not Registered") || ($reg_audit_result == "No Match Found")) {
                        ?><br><span class="oalm_dues_bad">However, your OA
                        membership is not currently valid because we could not
                        verify your BSA Membership status (see
                        below)</span><?php
                    }
                } else {
                    ?><span class="oalm_dues_bad">Your dues are not current.</span><?php
                    if (($reg_audit_result != "Not Registered") && ($reg_audit_result != "No Match Found")) {
                        ?><br><a href="<?php echo htmlspecialchars(get_option('oadueslookup_dues_url')) ?>">Click here to pay your dues online.</a><?php
                    }
                }
?></td></tr>
<tr><th>Last Dues Payment</th><td class="oalm_value"><?php echo htmlspecialchars($dues_paid_date) ?></td><td class="oalm_desc"></td></tr>
<tr><th>Your current honor/level</th><td class="oalm_value"><?php echo htmlspecialchars($level) ?></td><td class="oalm_desc"></td></tr>
<tr><th>BSA Membership Status</th><td class="oalm_value"><?php echo esc_html($reg_audit_result); if ($reg_audit_result != 'Registered') { echo "<br>as of<br>" . esc_html($reg_audit_date); } ?></td><td class="oalm_desc" style="text-align: left;"><?php
                switch ($reg_audit_result) {
                    case "Registered":
                        ?><span class="oalm_dues_good">You are currently an
                        active member of a Scouting unit.</span><br><?php
                        if ($max_dues_year >= $thedate['year']) {
                            ?>Your OA membership is thus valid.<?php
                        } else {
                            ?><span class="oalm_dues_bad">However, your OA
                            membership is not current because your dues are not
                            paid up. (see above)</span><?php
                        }
                        break;
                    case "Not Registered":
                        ?><span class="oalm_dues_bad">Your BSA registration has
                        expired, which means you are no longer listed as a
                        registered member of any Scouting unit, and also cannot
                        be a member of the OA.</span><br>You will need to join
                        a Scouting unit (troop, pack, crew, district, etc)
                        before you may renew your OA Membership. If you
                        <b>are</b> currently a member of a Scouting unit,
                        please have your unit chairperson check to make sure
                        your registration has been properly submitted to the
                        council. If you are a member of more than one unit,
                        please check with all of them, as only the "primary"
                        unit counts, and it's not always clear which one is
                        primary.<?php
                        break;
                    case "No Match Found":
                        ?><span class="oalm_dues_bad">Our most recent audit
                        could not find you in the BSA database.</span><br>This
                        almost always means the information we have on file for
                        you does not match what is on your unit's official
                        roster.  We must be able to verify your BSA membership
                        before you can renew your OA membership.  Please check
                        with your unit committee chairperson or advancement
                        chairperson to verify how they have you listed on the
                        unit roster.  The items which matter are:<ol><li>the
                        spelling and spacing of your last name,</li><li>your
                        birth date,</li><li>your gender, and</li><li>your BSA
                        Member ID.</li></ol>Once you've verified this
                        information, please submit it to us by using the
                        "Update Contact Information" option on the <a
                        href="<?php echo
                        htmlspecialchars(get_option('oadueslookup_dues_url')) ?>">dues
                        form.</a><?php
                        break;
                    case "Not Checked":
                        ?>This means one of the following things:<ul>
                        <li>You're new, and we haven't run a new audit against
                        the BSA database since you were put in the OA
                        database</li> <li>Your BSA Member ID was just recently
                        added to the OA database, and a new audit hasn't been
                        run yet.</li> <li>You haven't paid dues in over 3
                        years, so we didn't include you in the audit because we
                        thought you were inactive.</li></ul> <?php
                        break;
                }
                ?></td></tr>
                </table><?php
            }
?><br><p>Feel free to contact <a href="mailto:<?php echo htmlspecialchars(get_option('oadueslookup_help_email')) ?>?subject=Dues+question"><?php echo htmlspecialchars(get_option('oadueslookup_help_email')) ?></a> with any questions.</p>
<p><b>Database last updated:</b> <?php esc_html_e(get_option('oadueslookup_last_update')) ?></p>
<br><br><br>
<p>Check another BSA Member ID:</p>
<form method="POST" action="">
<label for="bsaid">BSA Member ID:</label> <input id="bsaid" name="bsaid" type="text" size="9">
<input type="submit" value="Go">
</form>
<?php
        } else {
?>
<div class="oalm_dues_bad"><p>Invalid BSA Member ID entered, please try again.</p></div>
<?php
        }
?>
<?php
    } else {
?>
<p>Enter your BSA Member ID to check your current dues status or pay your dues.</p>
<form method="POST" action="">
<label for="bsaid">BSA Member ID:</label> <input id="bsaid" name="bsaid" type="text" size="9">
<input type="submit" value="Go">
</form>
<br>
<p>You can find your Member ID at the bottom of your blue BSA Membership card:</p>
<p><img src="<?php echo plugins_url("BSAMemberCard.png", __FILE__) ?>" alt="Membership Card" style="border: 1px solid #ccc;"></p>
<p>If you can't find your membership card, your unit committee chairperson should be able to look it up on your unit recharter document, or your advancement chairperson can look it up in the Online Advancement System.</p>
<p>If you just came here to update your contact information, <a href="<?php echo htmlspecialchars(get_option('oadueslookup_dues_url')) ?>">click here</a>.</p>
<?php
    }
    return ob_get_clean();
}

function oadueslookup_url_handler( &$wp ) {
    if($wp->request == get_option('oadueslookup_slug')) {
        # http://stackoverflow.com/questions/17960649/wordpress-plugin-generating-virtual-pages-and-using-theme-template
        # Note that we don't need to do a template redirect as suggesting in
        # the example because all we do is load the template anyway. We can let
        # the real template code work like it's supposed to and only override
        # the content.
        add_filter('the_posts', 'oadueslookup_dummypost');
        remove_filter('the_content', 'wpautop');
    }
}

function oadueslookup_dummypost($posts) {
    // have to create a dummy post as otherwise many templates
    // don't call the_content filter
    global $wp, $wp_query;

    //create a fake post intance
    $p = new stdClass;
    // fill $p with everything a page in the database would have
    $p->ID = -1;
    $p->post_author = 1;
    $p->post_date = current_time('mysql');
    $p->post_date_gmt =  current_time('mysql', $gmt = 1);
    $p->post_content = oadueslookup_user_page($wp);
    $p->post_title = 'Lodge Dues Status';
    $p->post_excerpt = '';
    $p->post_status = 'publish';
    $p->ping_status = 'closed';
    $p->post_password = '';
    $p->post_name = get_option('oadueslookup_slug');
    $p->to_ping = '';
    $p->pinged = '';
    $p->modified = $p->post_date;
    $p->modified_gmt = $p->post_date_gmt;
    $p->post_content_filtered = '';
    $p->post_parent = 0;
    $p->guid = get_home_url('/' . $p->post_name); // use url instead?
    $p->menu_order = 0;
    $p->post_type = 'page';
    $p->post_mime_type = '';
    $p->comment_status = 'closed';
    $p->comment_count = 0;
    $p->filter = 'raw';
    $p->ancestors = array(); // 3.6

    // reset wp_query properties to simulate a found page
    $wp_query->is_page = TRUE;
    $wp_query->is_singular = TRUE;
    $wp_query->is_home = FALSE;
    $wp_query->is_archive = FALSE;
    $wp_query->is_category = FALSE;
    unset($wp_query->query['error']);
    $wp->query = array();
    $wp_query->query_vars['error'] = '';
    $wp_query->is_404 = FALSE;

    $wp_query->current_post = $p->ID;
    $wp_query->found_posts = 1;
    $wp_query->post_count = 1;
    $wp_query->comment_count = 0;
    // -1 for current_comment displays comment if not logged in!
    $wp_query->current_comment = null;
    $wp_query->is_singular = 1;

    $wp_query->post = $p;
    $wp_query->posts = array($p);
    $wp_query->queried_object = $p;
    $wp_query->queried_object_id = $p->ID;
    $wp_query->current_post = $p->ID;
    $wp_query->post_count = 1;

    return array($p);
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

if (isset($_FILES['oalm_file'])) {
    #echo "<h3>Processing file upload</h3>";
    #echo "<b>Processing File:</b> " . esc_html($_FILES['oalm_file']['name']) . "<br>";
    #echo "<b>Type:</b> " . esc_html($_FILES['oalm_file']['type']) . "<br>";
    if (preg_match('/\.xlsx$/',$_FILES['oalm_file']['name'])) {

        /** PHPExcel */
        include plugin_dir_path( __FILE__ ) . 'PHPExcel-1.8.0/Classes/PHPExcel.php';

        /** PHPExcel_Writer_Excel2007 */
        include plugin_dir_path( __FILE__ ) . 'PHPExcel-1.8.0/Classes/PHPExcel/Writer/Excel2007.php';

        $objReader = new PHPExcel_Reader_Excel2007();
        $objReader->setReadDataOnly(true);
        $objReader->setLoadSheetsOnly( array("Sheet") );
        $objPHPExcel = $objReader->load($_FILES["oalm_file"]["tmp_name"]);
        $objWorksheet = $objPHPExcel->getActiveSheet();
        $columnMap = array(
            'BSA ID'            => 'bsaid',
            'Max Dues Year'     => 'max_dues_year',
            'Dues Paid Date'    => 'dues_paid_date',
            'Level'             => 'level',
            'Reg. Audit Date'   => 'reg_audit_date',
            'Reg. Audit Result' => 'reg_audit_result',
        );
        $complete = 0;
        $recordcount = 0;
        $error_output = "";
        foreach ($objWorksheet->getRowIterator() as $row) {
            $rowData = array();
            if ($row->getRowIndex() == 1) {
                # this is the header row, grab the headings
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                foreach ($cellIterator as $cell) {
                    $cellValue = $cell->getValue();
                    if (isset($columnMap[$cellValue])) {
                        $rowData[$columnMap[$cellValue]] = 1;
                        #echo "Found column " . htmlspecialchars($cell->getColumn()) . " with title '" . htmlspecialchars($cellValue) . "'<br>" . PHP_EOL;
                    } else {
                        #echo "Discarding unknown column " . htmlspecialchars($cell->getColumn()) . " with title '" . htmlspecialchars($cellValue) . "'<br>" . PHP_EOL;
                    }
                }
                $missingColumns = array();
                foreach ($columnMap as $key => $value) {
                    if (!isset($rowData[$value])) {
                        $missingColumns[] = $key;
                    }
                }
                if ($missingColumns) {
                    ?><div class="error"><p><strong>Import failed.</strong></p><p>Missing required columns: <?php esc_html_e(implode(", ",$missingColumns)) ?></div><?php
                    $complete = 1; # Don't show "may have failed" box at the bottom
                    break;
                } else {
                    #echo "<b>Data format validated:</b> Importing new data...<br>" . PHP_EOL;
                    # we just validated that we have a good data file, nuke the existing data
                    $wpdb->show_errors();
                    ob_start();
                    $wpdb->query("TRUNCATE TABLE ${dbprefix}dues_data");
                    update_option('oadueslookup_last_import', $wpdb->get_var("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d')"));
                    # re-insert the test data
                    oadueslookup_insert_sample_data();
                    # now we're ready for the incoming from the rest of the file.
                }
            } else {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                foreach ($cellIterator as $cell) {
                    if (($cell->getColumn() == "A") && (preg_match("/^Count=/", $cell->getValue()))) {
                        $complete = 1;
                        $error_output = ob_get_clean();
                        if (!$error_output) {
                        ?><div class="updated"><p><strong>Import successful. Imported <?php esc_html_e($recordcount) ?> records.</strong></p></div><?php
                        } else {
                            ?><div class="error"><p><strong>Import partially successful. Imported <?php esc_html_e($recordcount) ?> of <?php esc_html_e($row->getRowIndex() - 2) ?> records.</strong></p>
                            <p>Errors follow:</p>
                            <?php echo $error_output ?>
                            </div><?php
                        }
                        update_option('oadueslookup_last_update', $wpdb->get_var("SELECT DATE_FORMAT(MAX(dues_paid_date), '%Y-%m-%d') FROM ${dbprefix}dues_data"));
                        break;
                    }
                    $columnName = $objWorksheet->getCell($cell->getColumn() . "1")->getValue();
                    $value = "";
                    if ($columnName == "Dues Paid Date") {
                        # this is a date field, and we have to work miracles to turn it into a mysql-compatible date
                        $date = $cell->getValue();
                        $dateint = intval($date);
                        $dateintVal = (int) $dateint;
                        $value = PHPExcel_Style_NumberFormat::toFormattedString($dateintVal, "YYYY-MM-DD");
                    } else if ($columnName == "Reg. Audit Date") {
                        # this is also a date field, but can be empty
                        $date = $cell->getValue();
                        if (!$date) {
                            $value = get_option('oadueslookup_last_import');
                        } else {
                            $dateint = intval($date);
                            $dateintVal = (int) $dateint;
                            $value = PHPExcel_Style_NumberFormat::toFormattedString($dateintVal, "YYYY-MM-DD");
                        }
                    } else {
                        $value = $cell->getValue();
                    }
                    if (isset($columnMap[$columnName])) {
                        $rowData[$columnMap[$columnName]] = $value;
                    }
                }
                if (!$complete) {
                    if ($wpdb->insert($dbprefix . "dues_data", $rowData, array('%s','%s','%s','%s','%s'))) {
                        $recordcount++;
                    }
                }
            }
        }
        if (!$complete) {
            $error_output = ob_get_clean();
            ?><div class="error"><p><strong>Import may have failed.</strong></p><p>Imported <?php esc_html_e($recordcount) ?> records, but end of file marker from OALM was not reached.</p><?php
            if ($error_output) {
                ?><p>Errors follow:</p>
                <?php echo $error_output;
            }
            ?></div><?php
        }
    } else {
        ?><div class="error"><p><strong>Invalid file upload.</strong> Not an XLSX file.</p></div><?php
    }
}

    //
    // HANDLE SETTINGS SCREEN UPDATES
    //

    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'oadueslookup-settings') {

        $slug = $_POST['oadueslookup_slug'];
        $dues_url = $_POST['oadueslookup_dues_url'];
        $help_email = $_POST['oadueslookup_help_email'];

        # $help_email is the only one that throws an error if it doesn't
        # validate.  The others we just silently fix so they're something
        # valid. The user will see the result on the form.
        if (!is_email($help_email)) {
            ?><div class="error"><p><strong>'<?php esc_html_e($help_email); ?>' is not a valid email address.</strong></p></div><?php
        } else {

            $foundchanges = 0;
            $slug = sanitize_title($slug);
            if ($slug != get_option('oadueslookup_slug')) {
                update_option('oadueslookup_slug', $slug);
                $foundchanges = 1;
            }

            $dues_url = esc_url_raw($dues_url);
            if ($dues_url != get_option('oadueslookup_dues_url')) {
                update_option('oadueslookup_dues_url', $dues_url);
                $foundchanges = 1;
            }

            if ($help_email != get_option('oadueslookup_help_email')) {
                update_option('oadueslookup_help_email', $help_email);
                $foundchanges = 1;
            }

            if ($foundchanges) {
                ?><div class="updated"><p><strong>Changes saved.</strong></p></div><?php
            }
        }

    }

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

?>

<h3 style="border-bottom: 1px solid black;">Import data from OALM</h3>
<p>Export file from OALM Must contain at least the following columns:<br>
BSA ID, Max Dues Year, Dues Paid Date, Level, Reg. Audit Date, Reg. Audit Result<br>
Any additional columns will be ignored.</p>
<p><a href="http://github.com/justdave/oadueslookup/wiki">How to create the export file in OALM</a></p>
<form action="" method="post" enctype="multipart/form-data">
<label for="oalm_file">Click Browse, then select the xlsx file exported from OALM's "Export Members", then click "Upload":</label><br>
<input type="file" name="oalm_file" id="oalm_file" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
<input type="submit" class="button button-primary" name="submit" value="Upload"><br>
<p><b>Last import:</b> <?php 
    $last_import = get_option('oadueslookup_last_import');
    if ($last_import == '1900-01-01') { echo "Never"; }
    else { esc_html_e($last_import); }
?></p>
</form>
<h3 style="border-bottom: 1px solid black;">Lookup Page Settings</h3>
<form name="oadueslookup-settings" method="post" action="">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="oadueslookup-settings">
<table class="form-table">
<tbody>
<tr>
  <th scope="row"><label for="oadueslookup_slug">Dues Page Slug</label></th>
  <td><code><?php echo esc_html(get_option("home")); ?>/</code><input id="oadueslookup_slug" name="oadueslookup_slug" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_slug")); ?>">
  <p class="description">The name appended to your Site URL to reach the lookup page.</p>
  </td>
</tr>
<tr>
  <th scope="row"><label for="oadueslookup_dues_url">Dues Payment URL</label></th>
  <td><input id="oadueslookup_dues_url" name="oadueslookup_dues_url" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_dues_url")); ?>">
  <p class="description">The URL to send members to for actually paying their dues.</p>
  </td>
</tr>
<tr>
  <th scope="row"><label for="oadueslookup_help_email">Help Email</label></th>
  <td><input id="oadueslookup_help_email" name="oadueslookup_help_email" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_help_email")); ?>">
  <p class="description">The email address for members to ask questions.</p>
  </td>
</tr>
</tbody>
</table>
<p class="submit"><input id="submit" class="button button-primary" type="submit" value="Save Changes" name="submit"></p>
</form>
<?php

    echo "</div>";
} // END OF SETTINGS SCREEN

