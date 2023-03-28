<?php

namespace Locomotive\Cms\Models;

use WP_Query;

/**
 * Prints the HTML string for column data for a post meta field.
 *
 * @listen Extended_CPT_Admin#action:manage_{$post_type}_posts_custom_column
 *
 * @param string $meta_key The post meta key.
 * @param array  $args     Array of arguments for this field.
 * @retun void
 */
function display_column_post_meta(string $meta_key, array $args = []): void
{
    echo get_column_post_meta_html($meta_key, $args);
}

/**
 * Returns an HTML string for column data for a post meta field.
 *
 * @param string $meta_key The post meta key.
 * @param array  $args     Array of arguments for this field.
 * @retun string
 */
function get_column_post_meta_html(string $meta_key, array $args = []): string
{
    $args = wp_parse_args($args, [
        'empty'     => '&#8212;',
        'separator' => ', ',
    ]);

    $vals = get_post_meta(get_the_ID(), $meta_key, false);
    $html = [];
    sort($vals);

    foreach ($vals as $val) {
        if (!empty($val) || is_numeric($val)) {
            $parts = preg_split('/\s*\R+\s*/', $val, -1, PREG_SPLIT_NO_EMPTY);
            array_push($html, ...$parts);
        }
    }

    if (empty($html)) {
        return $args['empty'];
    } else {
        return esc_html(implode($args['separator'], $html));
    }
}

/**
 * Prints the HTML string for column data for a post's featured image or thumbnail.
 *
 * @listen Extended_CPT_Admin#action:manage_{$post_type}_posts_custom_column
 *
 * @param string $size The image size
 * @param array  $args Array of `width` and `height` attributes for the image
 * @retun void
 */
function display_column_featured_image(string $size = 'thumbnail', array $args = []): void
{
    echo get_column_featured_image_html($size, $args);
}

/**
 * Returns an HTML string for column data for a post's featured image or thumbnail.
 *
 * @listen Extended_CPT_Admin#action:manage_{$post_type}_posts_custom_column
 *
 * @param string $size The image size
 * @param array  $args Array of `width` and `height` attributes for the image
 * @retun string
 */
function get_column_featured_image_html(string $size = 'thumbnail', array $args = []): string
{
    $args = wp_parse_args($args, [
        'empty'  => '',
        'width'  => 'auto',
        'height' => 'atuo',
    ]);

    if (!function_exists('has_post_thumbnail') || !function_exists('get_field')) {
        return $args['empty'];
    }

    if (is_numeric($args['width'])) {
        $width = sprintf('%dpx', $args['width']);
    }

    if (is_numeric($args['height'])) {
        $height = sprintf('%dpx', $args['height']);
    }

    $atts = [
        'style' => esc_attr(sprintf(
            'width:%1$s;height:%2$s',
            $width,
            $height
        )),
        'title' => '',
    ];

    $html = $args['empty'];

    if (has_post_thumbnail()) {
        $html = get_the_post_thumbnail(null, $size, $atts);
    } else {
        $acf_thumbnail_id = get_field('thumbnail_image');

        if ($acf_thumbnail_id) {
            $html = wp_get_attachment_image($acf_thumbnail_id, $size, false, $atts);
        }
    }

    return $html;
}

/**
 * Remove featured image meta box.
 *
 * If post type supports "thumbnail" but meta box is removed,
 * it is possible the custom field is reimplemented via ACF.
 *
 * @listens WP#action:add_meta_boxes_{$post_type}
 *
 * @return void
 */
function remove_postimagediv_meta_box(): void
{
    remove_meta_box('postimagediv', get_post_type(), 'side');
}

/**
 * Changes the ORDER BY clause and query object to sort by the post 'menu_order' field.
 *
 * Mutations:
 * - [1]: Sort posts by 'menu_order', by default.
 * - [2]: Sort posts with a 'menu_order' of zero last.
 *
 * @listens filter:posts_orderby
 *     Filter the ORDER BY clause of the query.
 *
 * @global \wpdb $wpdb WordPress database abstraction object.
 *
 * @param  string   $clause   The ORDER BY clause of the query.
 * @param  WP_Query $wp_query The WP_Query instance (passed by reference).
 * @return string
 */
function change_posts_orderby_to_menu_order($clause, WP_Query $wp_query): string
{
    global $wpdb;

    $orderby   = $wp_query->get('orderby');
    $direction = strtoupper($wp_query->get('order'));

    /** @see [1] */
    if ('' === $orderby && 'DESC' === $direction) {
        $clause = "{$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC";

        $wp_query->set('orderby', 'menu_order title');
        $wp_query->set('order', 'ASC');
    }

    /** @see [2] */
    if ('menu_order title' === $orderby && 'ASC' === $direction) {
        $clause = "{$wpdb->posts}.menu_order = 0, {$clause}";
    }

    return $clause;
}
