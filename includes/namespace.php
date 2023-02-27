<?php

namespace App\Cms;

use App\Cms\Contracts\Bootable;
use WP_Post_Type;
use WP_Taxonomy;

/**
 * Bootstraps the plugin.
 *
 * @param  array $modules Zero or more modules to boot.
 * @return void
 */
function bootstrap(array $modules = []): void
{
    register_initial_hooks();

    provide_extra_hooks();

    Modules\ACF\bootstrap();
    Modules\Polylang\bootstrap();

    // Bootstrap the models and modules
    foreach ($modules as $module) {
        if (!is_object($module)) {
            $module = new $module();
        }

        if ($module instanceof Bootable) {
            $module->boot();
        }
    }
}

/**
 * Registers actions and filters for the plugin.
 *
 * @return void
 */
function register_initial_hooks(): void
{
    add_action('plugins_loaded', 'App\\Cms\\Support\\load_textdomain');
    add_filter('sanitize_title', 'App\\Cms\\Support\\sanitize_zero_chars');
}

/**
 * Extends existing WordPress actions and filters.
 *
 * @return void
 */
function provide_extra_hooks(): void
{
    add_action('unregistered_post_type',  __NAMESPACE__ . '\\unregistered_post_type', 10, 1);
    add_action('unregistered_taxonomy',   __NAMESPACE__ . '\\unregistered_taxonomy',  10, 1);

    add_filter('wp_get_attachment_link',  __NAMESPACE__ . '\\attachment_link_class',  10, 6);
}

/**
 * Fires after a post type was unregistered.
 *
 * @see wp-includes/post.php
 *
 * @listens WP#action:unregistered_post_type
 * @fires   action:unregistered_post_type_{$post_type}
 *
 * @param  string $post_type Post type key.
 * @return void
 */
function unregistered_post_type(string $post_type): void
{
    /**
     * Fires after a specific post type was unregistered.
     *
     * The dynamic portion of the hook name, `$post_type`, refers to the post type slug.
     *
     * @event action:unregistered_post_type_{$post_type}
     */
    do_action("unregistered_post_type_{$post_type}");
}

/**
 * Fires after a taxonomy was registered.
 *
 * @listens WP#action:unregistered_taxonomy
 * @fires   action:unregistered_taxonomy_{$taxonomy}
 *
 * @param  string $taxonomy Taxonomy slug.
 * @return void
 */
function unregistered_taxonomy(string $taxonomy): void
{
    /**
     * Fires after a specific taxonomy was unregistered.
     *
     * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
     *
     * @event action:unregistered_taxonomy_{$taxonomy}
     */
    do_action("unregistered_taxonomy_{$taxonomy}");
}

/**
 * Add `class="thumbnail"` to attachment items
 *
 * @listens WP#filter:wp_get_attachment_link
 * @fires   filter:attachment_link_class
 *
 * @param  string      $link_html The page link HTML output.
 * @param  integer     $id        Post ID.
 * @param  string      $size      Image size. Default 'thumbnail'.
 * @param  bool        $permalink Whether to add permalink to image. Default false.
 * @param  bool        $icon      Whether to include an icon. Default false.
 * @param  string|bool $text      If string, will be link text. Default false.
 * @return string  HTML attachment page link.
 */
function attachment_link_class($link_html, int $id, $size, $permalink, $icon, $text)
{
    /**
     * Filter the HTML class attribute for an attachment page link.
     *
     * @event  filter:attachment_link_class
     *
     * @param  string      $classes    The HTML class attribute value.
     * @param  integer     $id         Post ID.
     * @param  string      $size       Image size. Default 'thumbnail'.
     * @param  bool        $permalink  Whether to add permalink to image. Default false.
     * @param  bool        $icon       Whether to include an icon. Default false.
     * @param  string|bool $text       If string, will be link text. Default false.
     * @return string|array CSS class names.
     */
    $classes = apply_filters('attachment_link_class', '', $id, $size, $permalink, $icon, $text);

    if (!empty($classes) && false === strpos($link_html, ' class="')) {
        if (is_array($classes)) {
            $classes = implode(' ', $classes);
        }

        $link_html = str_replace('href', 'class="' . esc_attr($classes) . '" href', $link_html);
    }

    return $link_html;
}
