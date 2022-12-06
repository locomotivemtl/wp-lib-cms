<?php

namespace App\Cms;

use App\Cms\Contracts\Bootable;
use WP_Post_Type;
use WP_Taxonomy;

use function App\Cms\Support\path;

/**
 * Bootstraps the plugin.
 *
 * @param  array $modules Zero or more modules to boot.
 * @return void
 */
function bootstrap(array $modules = []): void
{
    create_initial_constants();

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
 * Defines initial plugin constants.
 *
 * @return void
 */
function create_initial_constants(): void
{
    define(__NAMESPACE__ . '\PLUGIN_DIR',  plugin_basename(__DIR__));
    define(__NAMESPACE__ . '\PLUGIN_PATH', plugin_dir_path(__DIR__));
    define(__NAMESPACE__ . '\PLUGIN_URL',  plugin_dir_url(__DIR__));

    define(__NAMESPACE__ . '\ACF_FIELDS_PATH',  path('resources/fields'));
    define(__NAMESPACE__ . '\ACF_LAYOUTS_PATH', path('resources/blocks'));
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

    add_filter('xyz/acf/settings/load_php', __NAMESPACE__ . '\\filter_acf_load_php');

    add_filter('wpseo_enhanced_slack_data', __NAMESPACE__ . '\\remove_twitter_meta_author', 10, 2);
}

/**
 * @param array                  $data The enhanced Slack sharing data.
 * @param Indexable_Presentation $presentation The presentation of an indexable.
 * @return array
 */
function remove_twitter_meta_author($data, $args)
{
    $key = __('Written by', 'wordpress-seo');
    if (isset($data[$key])) {
        unset($data[$key]);
    }
    return $data;
}

/**
 * Filters the list of paths that are searched for local ACF PHP fields.
 *
 * @listens XYZ#filter:xyz/acf/settings/load_php
 *
 * @param   string[] $paths The paths of local ACF fields.
 * @return  string[]
 */
function filter_acf_load_php(array $paths): array
{
    $paths[] = ACF_LAYOUTS_PATH;
    $paths[] = ACF_FIELDS_PATH;
    return $paths;
}

/**
 * Extends existing WordPress actions and filters.
 *
 * @return void
 */
function provide_extra_hooks(): void
{
    add_filter('register_post_type_args', __NAMESPACE__ . '\\register_post_type_args', 10, 2);
    add_action('registered_post_type',    __NAMESPACE__ . '\\registered_post_type',    10, 2);
    add_action('unregistered_post_type',  __NAMESPACE__ . '\\unregistered_post_type',  10, 1);
    add_action('registered_taxonomy',     __NAMESPACE__ . '\\registered_taxonomy',     10, 2);
    add_filter('register_taxonomy_args',  __NAMESPACE__ . '\\register_taxonomy_args',  10, 3);
    add_action('unregistered_taxonomy',   __NAMESPACE__ . '\\unregistered_taxonomy',   10, 1);
    add_filter('wp_get_attachment_link',  __NAMESPACE__ . '\\attachment_link_class',   10, 6);
}

/**
 * Fires after a post type is registered.
 *
 * @see wp-includes/post.php
 *
 * @listens WP#action:registered_post_type
 * @fires   XYZ#action:registered_{$post_type}_post_type
 *
 * @param  string       $post_type        Post type key.
 * @param  WP_Post_Type $post_type_object Arguments used to register the post type.
 * @return void
 */
function registered_post_type(string $post_type, WP_Post_Type $post_type_object): void
{
    /**
     * Fires after a specific post type is registered.
     *
     * The dynamic portion of the hook name, `$post_type`, refers to the post type slug.
     *
     * @event XYZ#action:registered_{$post_type}_post_type
     *
     * @param WP_Post_Type $post_type_object Arguments used to register the post type.
     */
    do_action("registered_{$post_type}_post_type", $post_type_object);
}

/**
 * Filter the arguments for registering a post type.
 *
 * @listens WP#filter:register_post_type_args
 * @fires   XYZ#filter:register_{$post_type}_post_type_args
 *
 * @param  array  $args      Array of arguments for registering a post type.
 * @param  string $post_type Post type key.
 * @return array  The filtered arguments for registering a post type.
 */
function register_post_type_args(array $args, string $post_type): array
{
    /**
     * Filter the arguments for registering a specific post type.
     *
     * The dynamic portion of the hook name, `$post_type`, refers to the post type slug.
     *
     * @event XYZ#filter:register_{$post_type}_post_type_args
     *
     * @param array  $args      Array of arguments for registering a post type.
     * @param string $post_type Post type key.
     */
    return apply_filters("register_{$post_type}_post_type_args", $args, $post_type);
}

/**
 * Fires after a post type was unregistered.
 *
 * @see wp-includes/post.php
 *
 * @listens WP#action:unregistered_post_type
 * @fires   XYZ#action:unregistered_{$post_type}_post_type
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
     * @event XYZ#action:unregistered_{$post_type}_post_type
     */
    do_action("unregistered_{$post_type}_post_type");
}

/**
 * Fires after a taxonomy is registered.
 *
 * @listens WP#action:registered_taxonomy
 * @fires   XYZ#action:registered_{$taxonomy}_taxonomy
 *
 * @global array $wp_taxonomies
 *
 * @param  string       $taxonomy     Taxonomy slug.
 * @param  array|string $object_types Object type or array of object types.
 * @return void
 */
function registered_taxonomy(string $taxonomy, $object_types): void
{
    global $wp_taxonomies;

    // Cast to an array, similar to "register_taxonomy_args_type"
    $object_types = (array) $object_types;

    // Pass the taxonomy object, similar to "registered_post_type"
    $taxonomy_object = $wp_taxonomies[$taxonomy];

    /**
     * Fires after a specific taxonomy is registered.
     *
     * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
     *
     * @event XYZ#action:registered_{$taxonomy}_taxonomy
     *
     * @param array       $object_types    Object type or array of object types.
     * @param WP_Taxonomy $taxonomy_object Arguments used to register the taxonomy.
     */
    do_action("registered_{$taxonomy}_taxonomy", $object_types, $taxonomy_object);
}

/**
 * Filter the arguments for registering a taxonomy.
 *
 * @listens filter:register_taxonomy_args
 *
 * @listens WP#filter:register_taxonomy_args
 * @fires   XYZ#filter:register_{$taxonomy}_taxonomy_args
 *
 * @param  array  $args         Array of arguments for registering a taxonomy.
 * @param  string $taxonomy     Taxonomy key.
 * @param  array  $object_types Array of names of object types for the taxonomy.
 * @return array  The filtered arguments for registering a taxonomy.
 */
function register_taxonomy_args(array $args, string $taxonomy, array $object_types): array
{
    /**
     * Filter the arguments for registering a specific taxonomy.
     *
     * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
     *
     * @event XYZ#filter:register_{$taxonomy}_taxonomy_args
     *
     * @param array    $args        Array of arguments for registering a taxonomy.
     * @param string   $taxonomy    Taxonomy key.
     * @param string[] $object_type Array of names of object types for the taxonomy.
     */
    return apply_filters("register_{$taxonomy}_taxonomy_args", $args, $taxonomy, $object_types);
}

/**
 * Fires after a taxonomy was registered.
 *
 * @listens WP#action:unregistered_taxonomy
 * @fires   XYZ#action:unregistered_{$taxonomy}_taxonomy
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
     * @event XYZ#action:unregistered_{$taxonomy}_taxonomy
     */
    do_action("unregistered_{$taxonomy}_taxonomy");
}

/**
 * Add `class="thumbnail"` to attachment items
 *
 * @listens WP#filter:wp_get_attachment_link
 * @fires   XYZ#filter:attachment_link_class
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
     * @event  XYZ#filter:attachment_link_class
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
