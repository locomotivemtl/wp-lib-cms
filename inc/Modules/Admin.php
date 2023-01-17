<?php

namespace App\Cms\Modules;

use App\Cms\Contracts\Bootable;
use WP_Admin_Bar;

use const App\Cms\VERSION;

use function App\Cms\Support\uri;

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
     * @return void
     */
    public function boot(): void
    {
        $this->register_login_hooks();

        if (is_admin()) {
            $this->register_admin_hooks();
            $this->register_plugin_hooks();
        }
    }

    /**
     * Registers required actions and filters for Admin.
     *
     * @return void
     */
    public function register_admin_hooks(): void
    {
        add_action('admin_init',            [$this, 'disable_admin_notifications']);
        add_action('admin_menu',            [$this, 'admin_menu_order'], 10, 0);
        add_action('admin_bar_menu',        [$this, 'remove_admin_bar_nodes'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_dashboard_setup',    [$this, 'disable_dashboard_widgets'], 999);
        add_filter('wp_editor_settings',    [$this, 'editor_settings'], 10, 2);
    }

    /**
     * Registers required actions and filters for {@see wp-login.php Login page}.
     *
     * Notes:
     * - [1]: Disable login errors for everyone; not knowing whether
     *        the username or password is wrong improves security.
     *
     * @return void
     */
    public function register_login_hooks(): void
    {
        /** @see [1] */
        add_filter('login_errors', '__return_null');

        add_filter('login_headerurl', [$this, 'login_header_url']);
    }

    /**
     * Registers required actions and filters for third-party plugins.
     *
     * @return void
     */
    public function register_plugin_hooks(): void
    {
        $this->register_seo_hooks();
    }

    /**
     * Registers required actions and filters for third-party SEO plugins.
     *
     * @return void
     */
    public function register_seo_hooks(): void
    {
        // Slim SEO
        add_filter('slim_seo_meta_box_priority', [$this, 'metabox_priority_low']);

        // The SEO Framework
        add_filter('the_seo_framework_metabox_priority', [$this, 'metabox_priority_low']);

        // Yoast SEO
        add_filter('wpseo_metabox_prio', [$this, 'metabox_priority_low']);
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
     * Moves the 'upload.php' menu item after most object types.
     *
     * @listens WP#action:admin_menu
     *     Fires before the administration menu loads in the admin.
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
     * Simplifies
     *
     * @listens WP#filter:wp_editor_settings
     *     Filters the {@see wp_editor()} settings.
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
            'app-cms-admin',
            uri('resources/styles/admin.css'),
            [],
            VERSION,
            'all'
        );

        wp_enqueue_style(
            'app-cms-acf',
            uri('resources/styles/acf.css'),
            [
                'acf-field-group',
            ],
            VERSION,
            'all'
        );

        wp_enqueue_script(
            'app-cms-acf',
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
     *
     * @return  void
     */
    public function disable_dashboard_widgets(): void
    {
        // WordPress
        remove_meta_box('dashboard_primary',       'dashboard', 'side');
        remove_meta_box('dashboard_secondary',     'dashboard', 'side');

        // remove_meta_box('dashboard_activity',          'dashboard', 'normal');
        // remove_meta_box('dashboard_browser_nag',       'dashboard', 'normal');
        remove_meta_box('dashboard_incoming_links',    'dashboard', 'normal');
        // remove_meta_box('dashboard_php_nag',           'dashboard', 'normal');
        remove_meta_box('dashboard_plugins',           'dashboard', 'normal');
        // remove_meta_box('dashboard_right_now',         'dashboard', 'normal');
        // remove_meta_box('health_check_status',         'dashboard', 'normal');
        // remove_meta_box('network_dashboard_right_now', 'dashboard', 'normal');

        // BBPress
        remove_meta_box('bbp-dashboard-right-now', 'dashboard', 'normal');

        // Yoast SEO
        remove_meta_box('yoast_db_widget', 'dashboard', 'normal');

        // Gravity Forms
        remove_meta_box('rg_forms_dashboard', 'dashboard', 'normal');

        // WPML
        remove_meta_box('icl_dashboard_widget', 'dashboard', 'side');
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
    public function remove_admin_bar_nodes(WP_Admin_Bar $wp_admin_bar): void
    {
        $wp_admin_bar->remove_node('new-user');
    }

    /**
     * Lowers the priority to push the metabox below the content.
     *
     * @param  string $priority The metabox priority
     * @return string
     */
    public function metabox_priority_low(string $priority): string
    {
        return 'low';
    }
}
