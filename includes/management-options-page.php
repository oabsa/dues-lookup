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

add_action( 'admin_menu', 'oadueslookup_add_settings_menu', 10 );
function oadueslookup_add_settings_menu() {
    add_submenu_page( "oa_tools", "Dues Lookup Settings", "Dues Lookup Settings", "manage_options", 'oadueslookup_options', 'oadueslookup_options', 90 );
}

function oadueslookup_options()
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

    //
    // HANDLE SETTINGS SCREEN UPDATES
    //

    if (isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'oadueslookup-settings') {
        $dues_url = $_POST['oadueslookup_dues_url'];
        $max_dues_year = $_POST['oadueslookup_max_dues_year'];
        $dues_register = $_POST['oadueslookup_dues_register'];
        $dues_register_msg = $_POST['oadueslookup_dues_register_msg'];
        $update_url = $_POST['oadueslookup_update_url'];
        $update_link_text = $_POST['oadueslookup_update_option_link_text'];
        $update_option_text = $_POST['oadueslookup_update_option_text'];
        $help_email = $_POST['oadueslookup_help_email'];
        $use_cron = $_POST['oadueslookup_use_cron'];

        # $help_email is the only one that throws an error if it doesn't
        # validate.  The others we just silently fix so they're something
        # valid. The user will see the result on the form.
        if (!is_email($help_email)) {
            ?><div class="error"><p><strong>'<?php esc_html_e($help_email); ?>' is not a valid email address.</strong></p></div><?php
        } else {
            $foundchanges = 0;
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

            if ($use_cron != get_option('oadueslookup_use_cron')) {
                update_option('oadueslookup_use_cron', $use_cron);
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

    echo "<h2>" . __('OA Dues Lookup Settings', 'oadueslookup') . "</h2>";

    // settings form

    ?>

<h3 class="oalm">Lookup Page Settings</h3>
<p>Add the dues lookup form to any page with the shortcode <code>[oadueslookup]</code>.</p>
<form name="oadueslookup-settings" method="post" action="">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="oadueslookup-settings">
<table class="form-table">
<tbody>
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
    <td><input id="oadueslookup_dues_register" name="oadueslookup_dues_register" class="code" type="checkbox" value="1"<?php checked(1 == esc_html(get_option('oadueslookup_dues_register'))); ?>">
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
<tr>
    <th scope="row"><label for="oadueslookup_use_cron">Use Cron?</label></th>
    <td><input id="oadueslookup_use_cron" name="oadueslookup_use_cron" class="code" type="checkbox" value="1"<?php checked(1 == esc_html(get_option('oadueslookup_use_cron'))); ?>">
        <p class="description">If this is NOT checked (the default), WordPress will attempt to immediately trigger processing of uploaded dues data after the file is uploaded. This is the fastest way to process the data, however, if you have a large dataset which takes longer than your server's execution timeout (your PHP is configured for <?php echo ini_get('max_execution_time'); ?> seconds, but web servers often enforce 30 seconds), this will fail and leave your data incomplete. If you find this happening a lot, you should check this box, and create a cron job on your server to trigger the data processing.</p>
        <p class="description">Example cron job:</p>
        <p class="description"><code>*/5 * * * * [ -f "<?php echo wp_upload_dir()['basedir']; ?>/dues-lookup/import.xlsx" ] &amp;&amp; /path/to/php "<?php echo realpath(dirname(__FILE__) . '/../bin/dues-import-processor.php') ?>"</code></p>
        <p class="description">Be sure to replace <code>/path/to/php</code> with the actual path to the php binary that matches the version WordPress is running under (<?php echo PHP_VERSION; ?>).</p>
        <p class="description">The script will exit without doing anything if it's already running.</p>
    </td>
</tr>
</tbody>
</table>
<p class="submit"><input id="submit" class="button button-primary" type="submit" value="Save Changes" name="submit"></p>
</form>
    <?php

    echo "</div>";
} // END OF SETTINGS SCREEN
