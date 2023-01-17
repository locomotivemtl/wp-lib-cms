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
     */
    public function register_admin_hooks(): void
    {
        add_action('admin_init',         [$this, 'disable_admin_notifications']);
        add_action('admin_menu',         [$this, 'change_admin_menu'], 10, 0);
        add_action('admin_bar_menu',     [$this, 'change_admin_bar_menu'], 50);
        add_action('wp_dashboard_setup', [$this, 'change_dashboard_widgets'], 50);
    }

    /**
     * Registers required actions and filters for {@see wp-login.php Login page}.
     *
     * Notes:
     * - [1]: Hides login errors for everyone; not knowing whether
     *        the username or password is wrong improves security.
     * - [2]: Replaces login header URL, which defaults to wordpress.org
     *        for unknown reason, with the home page URL.
     */
    public function register_login_hooks(): void
    {
        /** @see [1] */
        add_filter('login_errors', '__return_null', 50, 0);

        /** @see [2] */
        add_filter('login_headerurl', 'home_url', 10, 0);
    }

    /**
     * Registers required actions and filters for third-party plugins.
     */
    public function register_plugin_hooks(): void
    {
        $this->register_seo_hooks();
    }

    /**
     * Registers required actions and filters for third-party SEO plugins.
     */
    public function register_seo_hooks(): void
    {
        // Slim SEO
        add_filter('slim_seo_meta_box_priority', [$this, 'get_metabox_priority_low'], 20, 0);

        // The SEO Framework
        add_filter('the_seo_framework_metabox_priority', [$this, 'get_metabox_priority_low'], 20, 0);

        // Yoast SEO
        add_filter('wpseo_metabox_prio', [$this, 'get_metabox_priority_low'], 20, 0);
    }

    /**
     * Changes the toolbar menu nodes.
     *
     * Changes:
     * - Removes New User
     *
     * @listens action:admin_bar_menu
     *
     * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
     */
    public function change_admin_bar_menu(WP_Admin_Bar $wp_admin_bar): void
    {
        $wp_admin_bar->remove_node('new-user');
    }

    /**
     * Changes the sidebar menu items.
     *
     * Changes:
     * - Moves the 'upload.php' menu item after most object types.
     *
     * @listens action:admin_menu
     *
     * @global array $menu
     */
    public function change_admin_menu(): void
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
     * Disable dashboard widgets
     *
     * @listens action:wp_dashboard_setup
     *
     * @return  void
     */
    public function change_dashboard_widgets(): void
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
     *
     * Leave updating to the professionals.
     *
     * @listens action:admin_init
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
     * Returns a 'low' priority to to push a metabox below the content.
     */
    public function get_metabox_priority_low(): string
    {
        return 'low';
    }
}
