<?php
/*
 * Copyright (C) 2014-2021 David D. Miller
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

add_action( 'admin_menu', 'oadueslookup_add_import_menu', 10 );
function oadueslookup_add_import_menu() {
    add_submenu_page( "oa_tools", "Import Dues", "Import Dues", "manage_options", 'oadueslookup_import', 'oadueslookup_import', 50 );
}

function oadueslookup_import()
{

    global $wpdb;

    $dbprefix = $wpdb->prefix . "oalm_";
    $hidden_field_name = 'oalm_submit_hidden';

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // =========================
    // form processing code here
    // =========================

    if (isset($_FILES['oalm_file'])) {
        #echo "<h3>Processing file upload</h3>";
        #echo "<strong>Processing File:</strong> " . esc_html($_FILES['oalm_file']['name']) . "<br>";
        #echo "<strong>Type:</strong> " . esc_html($_FILES['oalm_file']['type']) . "<br>";
        if (preg_match('/\.xlsx$/', $_FILES['oalm_file']['name'])) {
            require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';
            #use PhpOffice\PhpSpreadsheet\Spreadsheet;
            #use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

            $objReader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $objReader->setReadDataOnly(true);
            $objReader->setLoadSheetsOnly(array("All"));
            $objSpreadsheet = $objReader->load($_FILES["oalm_file"]["tmp_name"]);
            $objWorksheet = $objSpreadsheet->getActiveSheet();
            $columnMap = array(
            'BSA ID'                => 'bsaid',
            'Dues Yr.'              => 'max_dues_year',
            'Dues Pd. Dt.'          => 'dues_paid_date',
            'Level'                 => 'level',
            'BSA Reg.'              => 'bsa_reg',
            'BSA Reg. Overidden'    => 'bsa_reg_overridden',
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
                    $cellIterator->setIterateOnlyExistingCells(false);
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
                        ?><div class="error"><p><strong>Import failed.</strong></p><p>Missing required columns: <?php esc_html_e(implode(", ", $missingColumns)) ?></div><?php
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
                    $cellIterator->setIterateOnlyExistingCells(false);
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
                        } else {
                            $value = $cell->getValue();
                        }
                        if (isset($columnMap[$columnName])) {
                            $rowData[$columnMap[$columnName]] = $value;
                        }
                    }
                    if ($wpdb->insert($dbprefix . "dues_data", $rowData, array('%s','%s','%s','%s','%d','%d','%s','%s'))) {
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

    // ============================
    // screens and forms start here
    // ============================

    //
    // MAIN SETTINGS SCREEN
    //

    echo '<div class="wrap">';

    // header

    echo "<h2>" . __('OA Dues Lookup - Dues Import', 'oadueslookup') . "</h2>";

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
if ($last_import == '1900-01-01') {
    echo "Never";
} else {
    esc_html_e($last_import);
}
?></p>
</form>
    <?php

    echo "</div>";
} // END OF SETTINGS SCREEN
