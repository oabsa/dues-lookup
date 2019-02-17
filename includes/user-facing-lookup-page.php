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

function oadueslookup_user_page(&$wp) {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    ob_start();
    if ( isset($_POST['bsaid']) ) {
        $bsaid = trim($_POST['bsaid']);
        if (preg_match('/^\d+$/', $bsaid)) {
            $results = $wpdb->get_row($wpdb->prepare("SELECT max_dues_year, dues_paid_date, level, bsa_reg, bsa_reg_overridden, bsa_verify_date, bsa_verify_status FROM ${dbprefix}dues_data WHERE bsaid = %d", array($bsaid)));
            if (!isset($results)) {
?>
<div class="oalm_dues_bad"><p>Your BSA Member ID <?php echo htmlspecialchars($bsaid) ?> was not found.</p></div>
<p>This can mean any of the following:</p>
<ul>
<li>You mistyped your ID</li>
<li>You are not a member of the lodge.</li>
<li><strong>(most likely)</strong> We don't have your BSA Member ID on your record or have the
incorrect ID on your record.</li>
</ul>
<p><strong>NOTE:</strong> If you already made a payment more recently than <?php esc_html_e(get_option('oadueslookup_last_update')) ?> it is not yet reflected here.</p>
<p>We currently have BSA Member IDs on file and verified as correctly matching your name
via the council records for everyone whose dues are current, so if your ID wasn't found
and you're sure you typed it correctly, then your dues are not current, and you should
pay them <a href="<?php echo get_option('oadueslookup_dues_url') ?>">here</a>.</p>
<?php
                if (get_option('oadueslookup_dues_register') == '1') {
                    ?><p><strong>NOTE:</strong>  <?php esc_html_e(get_option('oadueslookup_dues_register_msg')) ?></p><?php
                }
            } else {
                $max_dues_year = $results->max_dues_year;
                $dues_paid_date = $results->dues_paid_date;
                $level = $results->level;
                $bsa_reg = $results->bsa_reg ? "Registered" : "Not Registered";
                $bsa_reg_overridden = $results->bsa_reg_overridden;
                if ($bsa_reg_overridden) {
                    $bsa_reg = $bsa_reg . " (overridden)";
                }
                $bsa_verify_date = $results->bsa_verify_date;
                $bsa_verify_status = $results->bsa_verify_status;
                if ($bsa_verify_status == "") {
                    $bsa_verify_status = "Never Run";
                }
?>
<table class="oalm_dues_table">
<tr><th>BSA Member ID</th><td class="oalm_value"><?php echo htmlspecialchars($bsaid) ?></td><td class="oalm_desc"></td></tr>
<tr><th>Dues Paid Thru</th><td class="oalm_value"><?php echo htmlspecialchars($max_dues_year) ?></td><td class="oalm_desc"><?php
                $thedate = getdate();
                if ($max_dues_year >= $thedate['year']) {
                    ?><span class="oalm_dues_good">Your dues are current.</span><?php
                    if (($bsa_verify_status === "Never Run") || ($bsa_verify_status === "BSA ID Not Found") || ($bsa_verify_status === "BSA ID Found - Data Mismatch")) {
                        ?><br><span class="oalm_dues_bad">However, your OA
                        membership is not currently valid because we could not
                        verify your BSA Membership status (see
                        below)</span><?php
                    } else if ($max_dues_year < get_option('oadueslookup_max_dues_year')) {
                        ?><br><a href="<?php echo htmlspecialchars(get_option('oadueslookup_dues_url')) ?>">Click here to pay next year's dues online.</a><?php
                    }
                } else {
                    ?><span class="oalm_dues_bad">Your dues are not current.</span><?php
                    if (($bsa_verify_status !== "Never Run") && ($bsa_verify_status !== "BSA ID Not Found") && ($bsa_verify_status !== "BSA ID Found - Data Mismatch")) {
                        ?><br><a href="<?php echo htmlspecialchars(get_option('oadueslookup_dues_url')) ?>">Click here to pay your dues online.</a>
                            <p><strong>NOTE:</strong> If you already made a payment more recently than <?php esc_html_e(get_option('oadueslookup_last_update')) ?> it is not yet reflected here.</p><?php
                    }
                    if (get_option('oadueslookup_dues_register') === "1") {
                        ?><p><strong>NOTE:</strong>  <?php esc_html_e(get_option('oadueslookup_dues_register_msg')) ?></p><?php
                    }
                }
?></td></tr>
<tr><th>Last Dues Payment</th><td class="oalm_value"><?php echo htmlspecialchars($dues_paid_date) ?></td><td class="oalm_desc"></td></tr>
<tr><th>Your current honor/level</th><td class="oalm_value"><?php echo htmlspecialchars($level) ?></td><td class="oalm_desc"></td></tr>
<tr><th>BSA Registration</th><td class="oalm_value"><?php echo htmlspecialchars($bsa_regvel) ?></td><td class="oalm_desc"></td></tr>
<tr><th>BSA Verification Status</th><td class="oalm_value"><?php esc_html_e($bsa_verify_status) ?></td><td class="oalm_desc" style="text-align: left;"><?php
                switch ($bsa_verify_status) {
                    case "BSA ID Verified":
                        ?><span class="oalm_dues_good">You are currently an
                        active member of a Scouting unit.</span><br><?php
                        break;
                    case "BSA ID Not Found":
                        ?><span class="oalm_dues_bad">Your BSA registration has
                        expired, which means you are no longer listed as a
                        registered member of any Scouting unit, and also cannot
                        be a member of the OA.</span><br>You will need to join
                        a Scouting unit (troop, pack, crew, district, etc)
                        before you may renew your OA Membership. If you
                        <strong>are</strong> currently a member of a Scouting unit,
                        please have your unit chairperson check to make sure
                        your registration has been properly submitted to the
                        council. If you are a member of more than one unit,
                        please check with all of them, as only the "primary"
                        unit counts, and it's not always clear which one is
                        primary.<br><br>We last checked your status in the
                        BSA database on <?php esc_html_e($bsa_verify_date);
                        break;
                    case "BSA ID Found - Data Mismatch":
                        ?><span class="oalm_dues_bad">Our most recent audit
                        could not find you in the BSA database.</span><br>We
                        last attempted to find you on <?php
                        esc_html_e($bsa_verify_date) ?>.<br><br>This
                        almost always means the information we have on file for
                        you does not match what is on your unit's official
                        roster. We must be able to verify your BSA membership
                        before you can renew your OA membership. Please check
                        with your unit committee chairperson or advancement
                        chairperson to verify how they have you listed on the
                        unit roster. The items which matter are:<ol><li>the
                        spelling, spacing, and punctuation of your last
                        name,</li><li>your birth date,</li><li>your gender,
                        and</li><li>your BSA Member ID.</li></ol>Once you've
                        verified this information, please submit it to us by using the
                        "<?php echo htmlspecialchars(get_option('oadueslookup_update_option_text')) ?>"
                        option on the
                        <a href="<?php echo htmlspecialchars(get_option('oadueslookup_update_url')) ?>">
                            <?php echo htmlspecialchars(get_option('oadueslookup_update_option_link_text')) ?>
                        </a>
                        <?php
                        break;
                    case "Never Run":
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
<p><strong>Database last updated:</strong> <?php esc_html_e(get_option('oadueslookup_last_update')) ?></p>
<br><br>
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
<p>Enter your BSA Member ID to check your current dues status or <a href="<?php echo htmlspecialchars(get_option('oadueslookup_dues_url')) ?>">pay your dues</a>.</p>
<form method="POST" action="">
<label for="bsaid">BSA Member ID:</label> <input id="bsaid" name="bsaid" type="text" size="9">
<input type="submit" value="Go">
</form>
<br>
<p>You can find your Member ID at the bottom of your blue BSA Membership card:</p>
<p><img src="<?php echo plugins_url("BSAMemberCard.png", __FILE__) ?>" alt="Membership Card" style="border: 1px solid #ccc;"></p>
<p>If you can't find your membership card, your unit committee chairperson should be able to look it up on your unit recharter document, or your advancement chairperson can look it up in the Online Advancement System.</p>
<p>If you just came here to update your contact information, <a href="<?php echo htmlspecialchars(get_option('oadueslookup_update_url')) ?>">click here</a>.</p>
<?php
    }
    return ob_get_clean();
}

function oadueslookup_url_handler(&$wp) {
    if ($wp->request == get_option('oadueslookup_slug')) {
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

    //create a fake post instance
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
    $wp_query->is_page = true;
    $wp_query->is_singular = true;
    $wp_query->is_home = false;
    $wp_query->is_archive = false;
    $wp_query->is_category = false;
    unset($wp_query->query['error']);
    $wp->query = array();
    $wp_query->query_vars['error'] = '';
    $wp_query->is_404 = false;

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
