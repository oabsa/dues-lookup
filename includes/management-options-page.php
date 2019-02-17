<?php
/*
 * Copyright (C) 2014-2018 David D. Miller
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
    #echo "<strong>Processing File:</strong> " . esc_html($_FILES['oalm_file']['name']) . "<br>";
    #echo "<strong>Type:</strong> " . esc_html($_FILES['oalm_file']['type']) . "<br>";
    if (preg_match('/\.xlsx$/',$_FILES['oalm_file']['name'])) {

        require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
        #use PhpOffice\PhpSpreadsheet\Spreadsheet;
        #use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

        $objReader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $objReader->setReadDataOnly(true);
        $objReader->setLoadSheetsOnly( array("All") );
        $objSpreadsheet = $objReader->load($_FILES["oalm_file"]["tmp_name"]);
        $objWorksheet = $objSpreadsheet->getActiveSheet();
        $columnMap = array(
            'BSA ID'                => 'bsaid',
            'Dues Yr.'              => 'max_dues_year',
            'Dues Pd. Dt.'          => 'dues_paid_date',
            'Level'                 => 'level',
            'BSA Reg.'              => 'bsa_reg',
            'BSA Reg. Overridden'   => 'bsa_reg_overridden',
            'BSA Verify Date'       => 'bsa_verify_date',
            'BSA Verify Status'     => 'bsa_verify_status',
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
                    #echo "<strong>Data format validated:</strong> Importing new data...<br>" . PHP_EOL;
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
                    $columnName = $objWorksheet->getCell($cell->getColumn() . "1")->getValue();
                    $value = "";
                    if ($columnName === "Dues Pd. Dt.") {
                        # this is a date field, and we have to work miracles to turn it into a mysql-compatible date
                        $date = $cell->getValue();
                        $dateint = intval($date);
                        $dateintVal = (int) $dateint;
                        $value = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::toFormattedString($dateintVal, "YYYY-MM-DD");
                    } elseif ($columnName === "BSA Verify Date") {
                        # this is also a date field, but can be empty
                        $date = $cell->getValue();
                        if (!$date) {
                            $value = get_option('oadueslookup_last_import');
                        } else {
                            $dateint = intval($date);
                            $dateintVal = (int) $dateint;
                            $value = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::toFormattedString($dateintVal, "YYYY-MM-DD");
                        }
                    } elseif ($columnName === 'BSA Reg.') {
                        $bool = $cell->getValue();

                    } elseif ($columnName === 'BSA Reg. Overridden') {

                    } else {
                        $value = $cell->getValue();
                    }
                    if (isset($columnMap[$columnName])) {
                        $rowData[$columnMap[$columnName]] = $value;
                    }
                }
                if ($wpdb->insert($dbprefix . "dues_data", $rowData, array('%s','%s','%s','%d','%d','%s','%s'))) {
                    $recordcount++;
                }
            }
        }
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
        $max_dues_year = $_POST['oadueslookup_max_dues_year'];
        $dues_register = $_POST['oadueslookup_dues_register'];
        $dues_register_msg = $_POST['oadueslookup_dues_register_msg'];
        $update_url = $_POST['oadueslookup_update_url'];
        $update_link_text = $_POST['oadueslookup_update_option_link_text'];
        $update_option_text = $_POST['oadueslookup_update_option_text'];
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

            $max_dues_year = intval($max_dues_year);
            if ($max_dues_year != get_option('oadueslookup_max_dues_year')) {
                update_option('oadueslookup_max_dues_year', $max_dues_year);
                $foundchanges = 1;
            }

            if ($dues_register != get_option('oadueslookup_dues_register')) {
                update_option('oadueslookup_dues_register', $dues_register);
                $foundchanges = 1;
            }

            $dues_register_msg = sanitize_text_field($dues_register_msg);
            if ($dues_register_msg != get_option('oadueslookup_dues_register_msg')) {
                update_option('oadueslookup_dues_register_msg', $dues_register_msg);
                $foundchanges = 1;
            }

            $update_url = esc_url_raw($update_url);
            if ($update_url != get_option('oadueslookup_update_url')) {
                update_option('oadueslookup_update_url', $update_url);
                $foundchanges = 1;
            }

            $update_link_text = sanitize_text_field($update_link_text);
            if ($update_link_text != get_option('oadueslookup_update_option_link_text')) {
                update_option('oadueslookup_update_option_link_text', $update_link_text);
                $foundchanges = 1;
            }

            $update_option_text = sanitize_text_field($update_option_text);
            if ($update_option_text != get_option('oadueslookup_update_option_text')) {
                update_option('oadueslookup_update_option_text', $update_option_text);
                $foundchanges = 1;
            }

            $help_email = sanitize_email($help_email);
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

<h3 class="oalm">Import data from OALM</h3>
<p>Export file from OALM Must contain at least the following columns:<br>
BSA ID, Dues Yr., Dues Pd. Dt., Level, BSA Reg., BSA Reg. Overidden, BSA Verify Date, BSA Verify Status<br>
Any additional columns will be ignored.</p>
<p><a href="http://github.com/justdave/oadueslookup/wiki">How to create the export file in OALM</a></p>
<form action="" method="post" enctype="multipart/form-data">
<label for="oalm_file">Click Browse, then select the xlsx file exported from OALM's "Export Members", then click "Upload":</label><br>
<input type="file" name="oalm_file" id="oalm_file" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
<input type="submit" class="button button-primary" name="submit" value="Upload"><br>
<p><strong>Last import:</strong> <?php
    $last_import = get_option('oadueslookup_last_import');
    if ($last_import == '1900-01-01') { echo "Never"; }
    else { esc_html_e($last_import); }
?></p>
</form>
<h3 class="oalm">Lookup Page Settings</h3>
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
  <th scope="row"><label for="oadueslookup_max_dues_year">Max dues year that can be paid</label></th>
  <td><input id="oadueslookup_max_dues_year" name="oadueslookup_max_dues_year" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_max_dues_year")); ?>">
  <p class="description">If the member's dues year is less than this, they will be prompted to pay dues anyway, even if they are otherwise current. This can be used to allow members to pay the following year for example.</p>
  </td>
</tr>
<tr>
    <th scope="row"><label for="oadueslookup_dues_register">Registration Required?</label></th>
    <td><input id="oadueslookup_dues_register" name="oadueslookup_dues_register" class="code" type="checkbox" value="1"<?php checked( 1 == esc_html(get_option('oadueslookup_dues_register'))); ?>">
        <p class="description">Does the dues payment site require the user to register before paying?</p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="oadueslookup_dues_register_msg">Registration Required Message</label></th>
    <td><input id="oadueslookup_dues_register_msg" name="oadueslookup_dues_register_msg" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_dues_register_msg")); ?>">
        <p class="description">The instruction text to display informing the user that they need to register before paying dues.</p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="oadueslookup_update_url">Update Contact Info URL</label></th>
    <td><input id="oadueslookup_update_url" name="oadueslookup_update_url" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_update_url")); ?>">
        <p class="description">The URL to send members to for updating their contact information.</p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="oadueslookup_update_option_link_text">Update Contact Link Info Text</label></th>
    <td><input id="oadueslookup_update_option_link_text" name="oadueslookup_update_option_link_text" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_update_option_link_text")); ?>">
        <p class="description">The text to appear in the hyperlink to the Update Contact Information URL.</p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="oadueslookup_update_option_text">Section Label</label></th>
    <td><input id="oadueslookup_update_option_text" name="oadueslookup_update_option_text" class="regular-text code" type="text" value="<?php echo esc_html(get_option("oadueslookup_update_option_text")); ?>">
        <p class="description">The label or option on the Update Contact Information page to direct the user to.</p>
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
