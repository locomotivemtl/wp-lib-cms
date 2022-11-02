<?php

namespace WpLib\Modules;

use WpLib\Contracts\Bootable;
use WP_Admin_Bar;
use WP_Query;

use const WpLib\VERSION;

use function WpLib\Support\uri;

/**
 * Module: WordPress Admin
 *
 * Customizes various areas of the dashboard and screens.
 */
class Admin implements Bootable
{
    /**
     * Boots the module and registers actions and filters.
     *
     * Notes:
     * - [1]: Disable login errors for everyone; not knowing whether
     *        the username or password is wrong improves security.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @see [1] */
        add_filter('login_errors', '__return_null');

        add_filter('login_headerurl', [$this, 'login_header_url']);

        if (is_admin()) {
            add_action('admin_init',            [$this, 'disable_admin_notifications']);
            add_action('admin_menu',            [$this, 'admin_menu_order'], 10, 0);
            add_action('admin_bar_menu',        [$this, 'remove_admin_bar_nodes'], 999);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_dashboard_setup',    [$this, 'disable_dashboard_widgets'], 999);
            add_filter('wp_editor_settings',    [$this, 'editor_settings'], 10, 2);

            add_filter('wpseo_metabox_prio',    [$this, 'wpseo_metabox_priority']);
        }
    }

    /**
     * Changes the login header URL to the current site.
     *
     * Defaults to wordpress.org for unknown reason.
     *
     * @listens WP#filter:login_headerurl
     *
     * @return string
     */
    public function login_header_url(): string
    {
        return home_url('/');
    }

    /**
     * Fires before the administration menu loads in the admin.
     *
     * Moves the 'upload.php' menu item.
     *
     * @listens WP#action:admin_menu
     *
     * @global array $menu
     *
     * @return void
     */
    public function admin_menu_order(): void
    {
        global $menu;

        foreach ($menu as $key => $value) {
            if (isset($value[2]) && 'upload.php' === $value[2]) {
                break;
            }
        }

        $menu['30'] = $menu[$key];

        unset($menu[$key]);
    }

    /**
     * Filters the {@see wp_editor()} settings.
     *
     * @listens WP#filter:wp_editor_settings
     *
     * @param  array  $settings  Array of editor arguments.
     * @param  string $editor_id ID for the current editor instance.
     * @return void
     */
    public function editor_settings($settings, $editor_id): array
    {
        $simplify = false;

        /*
        if ( 'content' === $editor_id && in_array( get_post_type(), [  ] ) ) {
            $simplify = true;
        }
        */

        if ($simplify) {
            $settings = wp_parse_args([
                'media_buttons' => false,
                'tinymce'       => [
                    'toolbar1'  => 'bold,italic,link,unlink,undo,redo',
                ],
                'quicktags'     => [
                    'buttons'   => 'strong,em,link,close',
                ],
            ], $settings);
        }

        return $settings;
    }

    /**
     * Register core taxonomies.
     *
     * @listens WP#action:admin_enqueue_scripts
     * @return  void
     */
    public function enqueue_assets(): void
    {
        wp_enqueue_style(
            'wp-lib-admin',
            uri('resources/styles/admin.css'),
            [],
            VERSION,
            'all'
        );

        wp_enqueue_style(
            'wp-lib-acf',
            uri('resources/styles/acf.css'),
            [
                'acf-field-group',
            ],
            VERSION,
            'all'
        );

        wp_enqueue_script(
            'wp-lib-acf',
            uri('resources/scripts/acf.js'),
            [
                'acf-input',
                'select2',
            ],
            VERSION
        );
    }

    /**
     * Disable dashboard widgets
     *
     * @listens WP#action:wp_dashboard_setup
     * @see     https://digwp.com/2014/02/disable-default-dashboard-widgets/
     * @return  void
     */
    public function disable_dashboard_widgets(): void
    {
        global $wp_meta_boxes;

        // WordPress
        # unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);
        # unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts']);

        // BBPress
        unset($wp_meta_boxes['dashboard']['normal']['core']['bbp-dashboard-right-now']);

        // Yoast SEO
        unset($wp_meta_boxes['dashboard']['normal']['core']['yoast_db_widget']);

        // Gravity Forms
        unset($wp_meta_boxes['dashboard']['normal']['core']['rg_forms_dashboard']);

        // WPML
        unset($wp_meta_boxes['dashboard']['side']['core']['icl_dashboard_widget']);
    }

    /**
     * Disable various on-screen notifications.
     *
     * Disable WordPress version checks for non-administrators.
     * Leave updating to the professionals.
     *
     * @listens WP#action:admin_init
     * @return  void
     */
    public function disable_admin_notifications(): void
    {
        if (!current_user_can('administrator')) {
            remove_action('wp_version_check', 'wp_version_check');
            remove_action('admin_init', '_maybe_update_core');
            add_filter('pre_transient_update_core', '__return_null');
        }
    }

    /**
     * Remove unnecessary Administration Toolbar Nodes
     *
     * Removes:
     * 1. New ("+") → User
     *
     * @listens WP#action:admin_bar_menu
     * @param   WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
     * @return  void
     */
    public function remove_admin_bar_nodes(WP_Admin_Bar $wp_admin_bar)
    {
        $wp_admin_bar->remove_node('new-user');
    }

    /**
     * @return string
     */
    public function wpseo_metabox_priority()
    {
        return 'low';
    }
}
