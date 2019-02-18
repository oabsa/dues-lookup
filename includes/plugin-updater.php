<?php
/* Prevent loading this file directly and/or if the class is already defined */
if (!defined('ABSPATH') || class_exists('OADuesLookup_Plugin_Updater')) {
    return;
}


/**
 * Plugin Updater Class
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @version 0.1.4
 * @author David Chandra Purnama <david@shellcreeper.com>
 * @link http://autohosted.com/
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright Copyright (c) 2013, David Chandra Purnama
 */
class OADuesLookup_Plugin_Updater
{

    /**
     * @var $config the config for the updater
     * @access public
     */
    var $config;


    /**
     * Class Constructor
     *
     * @since 0.1.0
     * @param array $config the configuration required for the updater to work
     * @return void
     */
    public function __construct($config = array())
    {

        /* default config */
        $defaults = array(
            'base' => '',
            'repo_uri' => '',
            'repo_slug' => '',
            'key' => '',
            'dashboard' => false,
            'username' => false,
            'autohosted' => 'plugin.0.1.4',
        );

        /* merge configs and defaults */
        $this->config = wp_parse_args($config, $defaults);

        /* disable request to wp.org repo */
        add_filter('http_request_args', array(&$this, 'disable_wporg_request'), 5, 2);

        /* check minimum config before doing stuff */
        if (!empty($this->config['base']) && !empty($this->config['repo_uri']) && !empty($this->config['repo_slug'])) {
            /* filters for admin area only */
            if (is_admin()) {
                /* filter site transient "update_plugins" */
                add_filter('pre_set_site_transient_update_plugins', array(&$this, 'transient_update_plugins'));

                /* filter plugins api */
                add_filter('plugins_api_result', array(&$this, 'plugins_api_result'), 10, 3);

                /* forder name fix */
                add_filter('upgrader_post_install', array(&$this, 'upgrader_post_install'), 10, 3);

                /* add dashboard widget for activation key */
                if (true === $this->config['dashboard']) {
                    add_action('wp_dashboard_setup', array(&$this, 'add_dashboard_widget'));
                }
            }
        }
    }


    /**
     * Disable request to wp.org plugin repository
     * this function is to remove update request data of this plugin to wp.org
     * so wordpress would not do update check for this plugin.
     *
     * @link http://markjaquith.wordpress.com/2009/12/14/excluding-your-plugin-or-theme-from-update-checks/
     * @since 0.1.2
     */
    public function disable_wporg_request($r, $url)
    {

        /* WP.org plugin update check URL */
        $wp_url_string = 'api.wordpress.org/plugins/update-check';

        /* If it's not a plugin update check request, bail early */
        if (false === strpos($url, $wp_url_string)) {
            return $r;
        }

        /* Get this plugin slug */
        $plugin_slug = dirname($this->config['base']);

        /* Get response body (json/serialize data) */
        $r_body = wp_remote_retrieve_body($r);

        /* Get plugins request */
        $r_plugins = '';
        $r_plugins_json = false;
        if (isset($r_body['plugins'])) {
            /* Check if data can be serialized */
            if (is_serialized($r_body['plugins'])) {
                /* unserialize data ( PRE WP 3.7 ) */
                $r_plugins = @unserialize($r_body['plugins']);
                $r_plugins = (array)$r_plugins; // convert object to array
            }

            /* if unserialize didn't work ( POST WP.3.7 using json ) */
            else {
                /* use json decode to make body request to array */
                $r_plugins = json_decode($r_body['plugins'], true);
                $r_plugins_json = true;
            }
        }

        /* this plugin */
        $to_disable = '';

        /* check if plugins request is not empty */
        if (!empty($r_plugins)) {
            /* All plugins */
            $all_plugins = $r_plugins['plugins'];

            /* Loop all plugins */
            foreach ($all_plugins as $plugin_base => $plugin_data) {
                /* Only if the plugin have the same folder, because plugins can have different main file. */
                if (dirname($plugin_base) == $plugin_slug) {
                    /* get plugin to disable */
                    $to_disable = $plugin_base;
                }
            }

            /* Unset this plugin only */
            if (!empty($to_disable)) {
                unset($all_plugins[$to_disable]);
            }

            /* Merge plugins request back to request */
            if (true === $r_plugins_json) { // json encode data
                $r_plugins['plugins'] = $all_plugins;
                $r['body']['plugins'] = json_encode($r_plugins);
            } else { // serialize data
                $r_plugins['plugins'] = $all_plugins;
                $r_plugins_object = (object)$r_plugins;
                $r['body']['plugins'] = serialize($r_plugins_object);
            }
        }

        /* return the request */
        return $r;
    }


    /**
     * Data needed in an array to make everything simple.
     *
     * @since 0.1.0
     * @return array
     */
    public function updater_data()
    {

        /* Updater data: Hana Tul Set! */
        $updater_data = array();

        /* Base name */
        $updater_data['basename'] = $this->config['base'];

        /* Plugin slug */
        $slug = dirname($this->config['base']);
        $updater_data['slug'] = $slug;

        /* Main plugin file */
        $updater_data['file'] = basename($this->config['base']);

        /* Updater class location is in the main plugin folder  */
        $file_path = plugin_dir_path(__FILE__) . $updater_data['file'];

        /* if it's in sub folder */
        if (basename(dirname(dirname(__FILE__))) == $updater_data['slug']) {
            $file_path = plugin_dir_path(dirname(__FILE__)) . $updater_data['file'];
        }

        /* Get plugin data from main plugin file */
        $get_plugin_data = get_plugin_data($file_path);

        /* Plugin name */
        $updater_data['name'] = strip_tags($get_plugin_data['Name']);

        /* Plugin version */
        $updater_data['version'] = strip_tags($get_plugin_data['Version']);

        /* Plugin uri / uri */
        $uri = '';
        if ($get_plugin_data['PluginURI']) {
            $uri = esc_url($get_plugin_data['PluginURI']);
        }
        $updater_data['uri'] = $uri;

        /* Author with link to author uri */
        $author = strip_tags($get_plugin_data['Author']);
        $author_uri = $get_plugin_data['AuthorURI'];
        if ($author && $author_uri) {
            $author = '<a href="' . esc_url_raw($author_uri) . '">' . $author . '</a>';
        }
        $updater_data['author'] = $author;

        /* by user role */
        if (false === $this->config['username']) {
            $updater_data['role'] = false;
        } else {
            $updater_data['role'] = true;
        }

        /* User name / login */
        $username = '';
        if (false !== $this->config['username'] && false === $this->config['dashboard']) {
            $username = $this->config['username'];
        }
        if (true === $this->config['username'] && true === $this->config['dashboard']) {
            $widget_id = 'ahp_' . $slug . '_activation_key';
            $widget_option = get_option($widget_id);
            $username = (isset($widget_option['username']) && !empty($widget_option['username'])) ? $widget_option['username'] : '';
        }
        $updater_data['login'] = $username;

        /* Activation key */
        $key = '';
        if ($this->config['key']) {
            $key = md5($this->config['key']);
        }
        if (empty($key) && true === $this->config['dashboard']) {
            $widget_id = 'ahp_' . $slug . '_activation_key';
            $widget_option = get_option($widget_id);
            $key = (isset($widget_option['key']) && !empty($widget_option['key'])) ? md5($widget_option['key']) : '';
        }
        $updater_data['key'] = $key;

        /* Domain */
        $updater_data['domain'] = esc_url_raw(get_bloginfo('url'));

        /* Repo uri */
        $repo_uri = '';
        if (!empty($this->config['repo_uri'])) {
            $repo_uri = trailingslashit(esc_url_raw($this->config['repo_uri']));
        }
        $updater_data['repo_uri'] = $repo_uri;

        /* Repo slug */
        $repo_slug = '';
        if (!empty($this->config['repo_slug'])) {
            $repo_slug = sanitize_title($this->config['repo_slug']);
        }
        $updater_data['repo_slug'] = $repo_slug;

        /* Updater class id and version */
        $updater_data['autohosted'] = esc_attr($this->config['autohosted']);

        return $updater_data;
    }


    /**
     * Check for plugin updates
     *
     * @since 0.1.0
     */
    public function transient_update_plugins($checked_data)
    {

        global $wp_version;

        /* Check the data */
        if (empty($checked_data->checked)) {
            return $checked_data;
        }

        /* Get needed data */
        $updater_data = $this->updater_data();

        /* Get data from server */
        $remote_url = add_query_arg(array('plugin_repo' => $updater_data['repo_slug'], 'ahpr_check' => $updater_data['version']), $updater_data['repo_uri']);
        $remote_request = array('timeout' => 20, 'body' => array('key' => $updater_data['key'], 'login' => $updater_data['login'], 'autohosted' => $updater_data['autohosted']), 'user-agent' => 'WordPress/' . $wp_version . '; ' . $updater_data['domain']);
        $raw_response = wp_remote_post($remote_url, $remote_request);

        /* Error check */
        $response = '';
        if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200)) {
            $response = maybe_unserialize(wp_remote_retrieve_body($raw_response));
        }

        /* Check response data */
        if (is_object($response) && !empty($response)) {
            /* Check the data is available */
            if (isset($response->new_version) && !empty($response->new_version) && isset($response->package) && !empty($response->package)) {
                /* Create response data object */
                $updates = new stdClass;
                $updates->new_version = $response->new_version;
                $updates->package = $response->package;
                $updates->slug = $updater_data['slug'];
                $updates->url = $updater_data['uri'];

                /* Set response if not set yet. */
                if (!isset($checked_data->response)) {
                    $checked_data->response = array();
                }

                /* Feed the update data */
                $checked_data->response[$updater_data['basename']] = $updates;
            }
        }
        return $checked_data;
    }


    /**
     * Filter Plugin API
     *
     * @since 0.1.0
     */
    public function plugins_api_result($res, $action, $args)
    {

        global $wp_version;

        /* Get needed data */
        $updater_data = $this->updater_data();

        /* Make sure $args is object */
        if (is_array($args)) {
            $args = (object)$args;
        }

        /* WP 3.7.1 get plugin slug.
        ----------------------------------- */
        $plugin_slug = '';  /* default, empty */

        /* Only if "slug" is not set yet */
        if (!isset($args->slug)) {
            /* Get plugin "slug" from Plugin Info Iframe URL */
            if (isset($_REQUEST['plugin'])) {
                $plugin_slug = wp_unslash($_REQUEST['plugin']);
            }

            /* If it's not on plugin info iframe (e.g. update core page) */
            else {
                /* Check "$args" body request */
                if (isset($args->body['request'])) {
                    $get_args_body = maybe_unserialize($args->body['request']);
                    if (isset($get_args_body->slug)) {
                        $plugin_slug = $get_args_body->slug;
                    }
                }
            }
        }

        /* if "slug" is set, use it */
        else {
            $plugin_slug = $args->slug;
        }

        /* Get data only from current plugin, and only when call for "plugin_information" */
        if ($plugin_slug == $updater_data['slug'] && $action == 'plugin_information') {
            /* Get data from server */
            $remote_url = add_query_arg(array('plugin_repo' => $updater_data['repo_slug'], 'ahpr_info' => $updater_data['version']), $updater_data['repo_uri']);
            $remote_request = array('timeout' => 20, 'body' => array('key' => $updater_data['key'], 'login' => $updater_data['login'], 'autohosted' => $updater_data['autohosted']), 'user-agent' => 'WordPress/' . $wp_version . '; ' . $updater_data['domain']);
            $request = wp_remote_post($remote_url, $remote_request);

            /* If error on retriving the data from repo */
            if (is_wp_error($request)) {
                $res = new WP_Error('plugins_api_failed', '<p>' . __('An Unexpected HTTP Error occurred during the API request.', 'text-domain') . '</p><p><a href="?" onclick="document.location.reload(); return false;">' . __('Try again', 'text-domain') . '</a></p>', $request->get_error_message());
            }

            /* If no error, construct the data */
            else {
                /* Unserialize the data */
                $requested_data = maybe_unserialize(wp_remote_retrieve_body($request));

                /* Check response data is available */
                if (is_object($requested_data) && !empty($requested_data)) {
                    /* Check the data is available */
                    if (isset($requested_data->version) && !empty($requested_data->version) && isset($requested_data->download_link) && !empty($requested_data->download_link)) {
                        /* Create plugin info data object */
                        $info = new stdClass;

                        /* Data from repo */
                        $info->version = $requested_data->version;
                        $info->download_link = $requested_data->download_link;
                        $info->requires = $requested_data->requires;
                        $info->tested = $requested_data->tested;
                        $info->sections = $requested_data->sections;

                        /* Data from plugin */
                        $info->slug = $updater_data['slug'];
                        $info->author = $updater_data['author'];
                        $info->uri = $updater_data['uri'];

                        /* Other data needed */
                        $info->external = true;
                        $info->downloaded = 0;

                        /* Feed plugin information data */
                        $res = $info;
                    }
                }

                /* If data is empty or not an object */
                else {
                    $res = new WP_Error('plugins_api_failed', __('An unknown error occurred', 'text-domain'), wp_remote_retrieve_body($request));
                }
            }
        }
        return $res;
    }


    /**
     * Make sure plugin is installed in correct folder
     *
     * @since 0.1.0
     */
    public function upgrader_post_install($true, $hook_extra, $result)
    {

        /* Check if hook extra is set */
        if (isset($hook_extra)) {
            /* Get needed data */
            $plugin_base = $this->config['base'];
            $plugin_slug = dirname($plugin_base);

            /* Only filter folder in this plugin only */
            if (isset($hook_extra['plugin']) && $hook_extra['plugin'] == $plugin_base) {
                /* wp_filesystem api */
                global $wp_filesystem;

                /* Move & Activate */
                $proper_destination = trailingslashit(WP_PLUGIN_DIR) . $plugin_slug;
                $wp_filesystem->move($result['destination'], $proper_destination);
                $result['destination'] = $proper_destination;
                $activate = activate_plugin(trailingslashit(WP_PLUGIN_DIR) . $plugin_base);

                /* Update message */
                $fail = __('The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'text-domain');
                $success = __('Plugin reactivated successfully. ', 'text-domain');
                echo is_wp_error($activate) ? $fail : $success;
            }
        }
        return $result;
    }


    /**
     * Add Dashboard Widget
     *
     * @since 0.1.0
     */
    public function add_dashboard_widget()
    {

        /* Get needed data */
        $updater_data = $this->updater_data();

        /* Widget ID, prefix with "ahp_" to make sure it's unique */
        $widget_id = 'ahp_' . $updater_data['slug'] . '_activation_key';

        /* Widget name */
        $widget_name = $updater_data['name'] . __(' Plugin Updates', 'text-domain');

        /* role check, in default install only administrator have this cap */
        if (current_user_can('update_plugins')) {
            /* add dashboard widget for acivation key */
            wp_add_dashboard_widget($widget_id, $widget_name, array(&$this, 'dashboard_widget_callback'), array(&$this, 'dashboard_widget_control_callback'));
        }
    }


    /**
     * Dashboard Widget Callback
     *
     * @since 0.1.0
     */
    public function dashboard_widget_callback()
    {

        /* Get needed data */
        $updater_data = $this->updater_data();

        /* Widget ID, prefix with "ahp_" to make sure it's unique */
        $widget_id = 'ahp_' . $updater_data['slug'] . '_activation_key';

        /* edit widget url */
        $edit_url = 'index.php?edit=' . $widget_id . '#' . $widget_id;

        /* get activation key from database */
        $widget_option = get_option($widget_id);

        /* if activation key available/set */
        if (!empty($widget_option) && is_array($widget_option)) {
            /* members only update */
            if (true === $updater_data['role']) {
                /* username */
                $username = isset($widget_option['username']) ? $widget_option['username'] : '';
                echo '<p>' . __('Username: ', 'text-domain') . '<code>' . $username . '</code></p>';

                /* activation key input */
                $key = isset($widget_option['key']) ? $widget_option['key'] : '';
                echo '<p>' . __('Email: ', 'text-domain') . '<code>' . $key . '</code></p>';
            } else {
                /* activation key input */
                $key = isset($widget_option['key']) ? $widget_option['key'] : '';
                echo '<p>' . __('Key: ', 'text-domain') . '<code>' . $key . '</code></p>';
            }


            /* if key status is valid */
            if ($widget_option['status'] == 'valid') {
                _e('<p>Your plugin update is <span style="color:green">active</span></p>', 'text-domain');
            }
            /* if key is not valid */
            elseif ($widget_option['status'] == 'invalid') {
                _e('<p>Your input is <span style="color:red">not valid</span>, automatic updates is <span style="color:red">not active</span>.</p>', 'text-domain');
                echo '<p><a href="' . $edit_url . '" class="button-primary">' . __('Edit Key', 'text-domain') . '</a></p>';
            }
            /* else */
            else {
                _e('<p>Unable to validate update activation.</p>', 'text-domain');
                echo '<p><a href="' . $edit_url . '" class="button-primary">' . __('Try again', 'text-domain') . '</a></p>';
            }
        }
        /* if activation key is not yet set/empty */
        else {
            echo '<p><a href="' . $edit_url . '" class="button-primary">' . __('Add Key', 'text-domain') . '</a></p>';
        }
    }


    /**
     * Dashboard Widget Control Callback
     *
     * @since 0.1.0
     */
    public function dashboard_widget_control_callback()
    {

        /* Get needed data */
        $updater_data = $this->updater_data();

        /* Widget ID, prefix with "ahp_" to make sure it's unique */
        $widget_id = 'ahp_' . $updater_data['slug'] . '_activation_key';

        /* check options is set before saving */
        if (isset($_POST[$widget_id]) && isset($_POST['dashboard-widget-nonce']) && wp_verify_nonce($_POST['dashboard-widget-nonce'], 'edit-dashboard-widget_' . $widget_id)) {
            /* get submitted data */
            $submit_data = $_POST[$widget_id];

            /* username submitted */
            $username = isset($submit_data['username']) ? strip_tags(trim($submit_data['username'])) : '';

            /* key submitted */
            $key = isset($submit_data['key']) ? strip_tags(trim($submit_data['key'])) : '';

            /* get wp version */
            global $wp_version;

            /* get current domain */
            $domain = $updater_data['domain'];

            /* Get data from server */
            $remote_url = add_query_arg(array('plugin_repo' => $updater_data['repo_slug'], 'ahr_check_key' => 'validate_key'), $updater_data['repo_uri']);
            $remote_request = array('timeout' => 20, 'body' => array('key' => md5($key), 'login' => $username, 'autohosted' => $updater_data['autohosted']), 'user-agent' => 'WordPress/' . $wp_version . '; ' . $updater_data['domain']);
            $raw_response = wp_remote_post($remote_url, $remote_request);

            /* get response */
            $response = '';
            if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200)) {
                $response = trim(wp_remote_retrieve_body($raw_response));
            }

            /* if call to server sucess */
            if (!empty($response)) {
                /* if key is valid */
                if ($response == 'valid') {
                    $valid = 'valid';
                }

                /* if key is not valid */
                elseif ($response == 'invalid') {
                    $valid = 'invalid';
                }

                /* if response is value is not recognized */
                else {
                    $valid = 'unrecognized';
                }
            }
            /* if response is empty or error */
            else {
                $valid = 'error';
            }

            /* database input */
            $input = array(
                'username' => $username,
                'key' => $key,
                'status' => $valid,
            );

            /* save value */
            update_option($widget_id, $input);
        }

        /* get activation key from database */
        $widget_option = get_option($widget_id);

        /* default key, if it's not set yet */
        $username_option = isset($widget_option['username']) ? $widget_option['username'] : '';
        $key_option = isset($widget_option['key']) ? $widget_option['key'] : '';

        /* display the form input for activation key */ ?>

        <?php if (true === $updater_data['role']) { // members only update ?>
        <p>
            <label for="<?php echo $widget_id; ?>-username"><?php _e('User name', 'text-domain'); ?></label>
        </p>
        <p>
            <input id="<?php echo $widget_id; ?>-username" name="<?php echo $widget_id; ?>[username]" type="text" value="<?php echo $username_option; ?>"/>
        </p>
        <p>
            <label for="<?php echo $widget_id; ?>-key"><?php _e('Email', 'text-domain'); ?></label>
        </p>
        <p>
            <input id="<?php echo $widget_id; ?>-key" class="regular-text" name="<?php echo $widget_id; ?>[key]" type="text" value="<?php echo $key_option; ?>"/>
        </p>

            <?php
        } else { // activation keys ?>
        <p>
            <label for="<?php echo $widget_id; ?>-key"><?php _e('Activation Key', 'text-domain'); ?></label>
        </p>
        <p>
            <input id="<?php echo $widget_id; ?>-key" class="regular-text" name="<?php echo $widget_id; ?>[key]" type="text" value="<?php echo $key_option; ?>"/>
        </p>

                <?php
        }
    }
}
