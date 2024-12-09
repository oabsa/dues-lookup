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

# load WordPress libraries if run from the command line
if (!function_exists("wp_upload_dir")) {
    require_once dirname(__FILE__) . '/../../../../wp-load.php';
}

$filename = wp_upload_dir()['basedir'] . "/dues-lookup/import.xlsx";

if (!file_exists($filename)) {
    # if the file doesn't exist, there's nothing to do.
    exit();
}

$current_status = get_option('oadueslookup_import_status')['status'];
if ($current_status == "processing" || $current_status == "completed") {
    # another process is already handling it, so bail.
    exit();
}

## process the file

global $wpdb;
$dbprefix = $wpdb->prefix . "oalm_";

$import_status = [
	'status' => 'processing',
	'progress' => '0',
	'output' => ''
];
update_option('oadueslookup_import_status', $import_status);

require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';

$objReader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$objReader->setReadDataOnly(true);
$objReader->setLoadSheetsOnly(array("All"));
$objSpreadsheet = $objReader->load($filename);
$objWorksheet = $objSpreadsheet->getActiveSheet();
$columnMap = array(
'Member ID'               => 'bsaid',
'Dues Yr.'                => 'max_dues_year',
'Dues Pd. Dt.'            => 'dues_paid_date',
'Level'                   => 'level',
'Scouting Reg.'           => 'bsa_reg',
'Scouting Reg. Overidden' => 'bsa_reg_overridden',
'Scouting Verify Date'    => 'bsa_verify_date',
'Scouting Verify Status'  => 'bsa_verify_status',
);
$complete = 0;
$rowcount = $objWorksheet->getHighestRow();
$recordcount = 0;
$error_output = "";
$oadueslookup_last_import = get_option('oadueslookup_last_import');
foreach ($objWorksheet->getRowIterator() as $row) {
    $rowData = array();
    $import_status['progress'] = round(($row->getRowIndex()/$rowcount)*1000);
    update_option('oadueslookup_import_status', $import_status);
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
            # Make an empty temporary table based on the dues_data table
            $wpdb->query("CREATE TEMPORARY TABLE {$dbprefix}dues_data_temp (PRIMARY KEY (bsaid)) SELECT * FROM {$dbprefix}dues_data LIMIT 0");
            $oadueslookup_last_import = $wpdb->get_var("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d')");
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
                    $value = $oadueslookup_last_import;
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
        if ($wpdb->insert($dbprefix . "dues_data_temp", $rowData, array('%s','%s','%s','%s','%d','%d','%s','%s'))) {
            $recordcount++;
        }
    }
}
$error_output = ob_get_clean();
ob_start();

# NOTE: If you want to make all errors fatal, set the following to true.
# Note that this will also make it fail when there are duplicate BSA IDs
# (which is why it's false by default).
$treat_errors_as_fatal = false;

if ((!$treat_errors_as_fatal) || (!$error_output)) {
    # delete the contents of the live table and copy the contents of the temp table to it
    $wpdb->query("TRUNCATE TABLE {$dbprefix}dues_data");
    $wpdb->query("INSERT INTO {$dbprefix}dues_data SELECT * FROM {$dbprefix}dues_data_temp");
}
$error_output .= ob_get_clean();
if ((!$treat_errors_as_fatal) || (!$error_output)) {
    update_option('oadueslookup_last_import', $oadueslookup_last_import);
}
ob_start();
if (!$error_output) {
    ?><div class="updated"><p><strong>Import successful. Imported <?php esc_html_e($recordcount) ?> records.</strong></p></div><?php
} else {
    ?><div class="error"><p><strong>Import partially successful. Imported <?php esc_html_e($recordcount) ?> of <?php esc_html_e($row->getRowIndex() - 2) ?> records.</strong></p>
<p>Errors follow:</p>
    <?php echo $error_output ?>
</div><?php
}
$import_status['output'] = ob_get_clean();
$import_status['status'] = 'completed';
update_option('oadueslookup_import_status', $import_status);
update_option('oadueslookup_last_update', $wpdb->get_var("SELECT DATE_FORMAT(MAX(dues_paid_date), '%Y-%m-%d') FROM {$dbprefix}dues_data"));
