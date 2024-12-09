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

add_action( 'admin_enqueue_scripts', 'oadueslookup_admin_enqueue_scripts' );
function oadueslookup_admin_enqueue_scripts() {
    $screen = get_current_screen();
    if ($screen->id == 'oa-tools_page_oadueslookup_import') {
        wp_enqueue_script('oalm-upload-widget-js', plugins_url('js/upload-widget.js?v=1', dirname(__FILE__)));
        wp_enqueue_style( 'oalm-upload-widget-css', plugins_url('css/upload-widget.css?v=1', dirname(__FILE__)));
        wp_localize_script( 'oalm-upload-widget-js', 'oalm', array(
            'wp_ajax_url' => admin_url( 'admin-ajax.php' ),
            'wp_site_url' => site_url(),
            'wp_admin_email' => get_bloginfo('admin_email','display')
        ));
    }
}

function oadueslookup_import()
{

    global $wpdb;

    $dbprefix = $wpdb->prefix . "oalm_";
    $hidden_field_name = 'oalm_submit_hidden';

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
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
Member ID, Dues Yr., Dues Pd. Dt., Level, Scouting Reg., Scouting Reg. Overidden, Scouting Verify Date, Scouting Verify Status<br>
Any additional columns will be ignored.</p>
<p><a href="https://github.com/oabsa/dues-lookup/wiki/OALM-Export">How to create the export file in OALM</a></p>

    <div class="oalm_upload_container">
    <input type="file" accept=".xlsx" name="oalm_file" id="oalm_file">
    <!-- Drag and Drop container-->
    <div class="upload-area" id="uploadfile">
    <div id="oalm_drop_text"><img alt="[waiting]" src="<?php echo site_url(); ?>/wp-includes/images/wpspin-2x.gif"><br>Checking status...</div>
    <div id="oalm_drop_filename">Filename</div>
    <div id="oalm_drop_status">Status</div>
    <progress id="oalm_drop_progressBar" value="0" max="1000" style="width: 300px;"></progress>
    <progress id="oalm_drop_processBar" value="0" max="1000" style="width: 300px;"></progress>
    </div>
    </div>
<div id="oalm_import_output"></div>
<p><strong>Last import:</strong> <?php
    $last_import = get_option('oadueslookup_last_import');
if ($last_import == '1900-01-01') {
    echo "Never";
} else {
    esc_html_e($last_import);
}
?></p><p>If the process seems to have stalled, <a href="#" id="oalm_reset_button">click here</a> to abort processing and start over. <?php
if (!get_option('oadueslookup_use_cron')) {
    ?>If this happens frequently it probably means your data is too big to be processed within your server's execution timeout, and you should consider running the processing script from cron (see the Dues Lookup Settings page).<?php
} else {
    ?>You have cron enabled, so you will see "Waiting for cron job..." after your upload completes until the cron job triggers (see the bottom of the Dues Lookup Settings page).<?php
}
?></p>
    <?php

    echo "</div>";
} // END OF SETTINGS SCREEN

add_action( 'wp_ajax_oalm_process_import_upload', 'oalm_process_import_upload' );
function oalm_process_import_upload() {
    if (isset($_FILES['oalm_file'])) {
        if (preg_match('/\.xlsx$/', $_FILES['oalm_file']['name'])) {
            // process file upload here
            $dir = wp_upload_dir()['basedir'] . "/dues-lookup/";
            $moved = move_uploaded_file($_FILES['oalm_file']['tmp_name'], $dir . 'import.xlsx');
            if ($moved) {
                update_option('oadueslookup_import_status', [
                    'status' => 'waiting',
                    'progress' => '0',
                    'output' => ''
                ]);
                wp_send_json(['status' => 'success']);
            } else {
                wp_send_json(['status' => 'error', 'errortext' => "Upload failed."]);
            }
        } else {
            wp_send_json(['status' => 'error', 'errortext' => 'Wrong filetype was uploaded. Please use .xlsx']);
        }
    } else {
        wp_send_json(['status' => 'error', 'errortext' => 'No file was uploaded?']);
    }
}

add_action( 'wp_ajax_oalm_import_status', 'oalm_import_status' );
function oalm_import_status() {
    $dir = wp_upload_dir()['basedir'] . "/dues-lookup/";
    if (!file_exists($dir . 'import.xlsx')) {
        $status = [ 'status' => 'ready' ];
    } else {
        $status = get_option('oadueslookup_import_status');
        if ($status['status'] == 'waiting') {
            if (!get_option('oadueslookup_use_cron')) {
                ignore_user_abort(true);
                set_time_limit(0);
                include(dirname(__FILE__)."/../bin/dues-import-processor.php");
            }
        }
    }
    wp_send_json($status);
}

/*
 * The upload widget calls this to acknowledge that it has retrieved the
 * output after the complete status has been received, effectively resetting
 * for next time. No parameters, no output.
 */
add_action( 'wp_ajax_oalm_ack_complete', 'oalm_ack_complete' );
function oalm_ack_complete() {
    $dir = wp_upload_dir()['basedir'] . "/dues-lookup/";
    if (file_exists($dir . 'import.xlsx')) {
        unlink($dir . 'import.xlsx');
    }
    update_option('oadueslookup_import_status', [
        'status' => 'waiting',
        'progress' => '0',
        'output' => ''
    ]);
}
