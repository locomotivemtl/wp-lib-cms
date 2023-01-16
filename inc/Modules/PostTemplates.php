<?php

namespace App\Cms\Modules;

use App\Cms\Contracts\Bootable;
use InvalidArgumentException;
use WP_Post;

/**
 * Module: Theme Post Templates
 *
 * Registers a sortable _post template_ column.
 */
class PostTemplates implements Bootable
{
    /**
     * The feature key.
     *
     * @var string
     */
    const FEATURE_NAME = 'post-templates';

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        // Defer booting until admin is ready
        if (!did_action('load-edit.php')) {
            if (!has_filter('load-edit.php', [$this, 'boot'])) {
                add_action('load-edit.php', [$this, 'boot']);
            }
            return;
        }

        $screen    = get_current_screen();
        $screen_id = $screen->id;
        $post_type = $screen->post_type;

        if (!$post_type) {
            return;
        }

        if (!post_type_supports($post_type, static::FEATURE_NAME)) {
            return;
        }

        if (count(get_page_templates(null, $post_type)) === 0) {
            return;
        }

        add_filter("manage_{$post_type}_posts_columns",       [$this, 'add_template_column']);
        add_action("manage_{$post_type}_posts_custom_column", [$this, 'display_template_column_output'], 10, 2);
        add_filter("manage_{$screen_id}_sortable_columns",    [$this, 'register_sortable_template_column']);
        add_filter('request', [$this, 'order_template_column']);

        add_filter('xyz/post-templates/column/output', [$this, 'get_default_post_template_output'], 15);
    }

    /**
     * Register the "Template" column.
     *
     * @listens WP#filter:manage_{$post_type}_posts_columns
     *     Filters the columns displayed in the Posts list table for a specific post type.
     *
     * @param  array<string, string> $post_columns An array of column names.
     * @return array<string, string>
     */
    public function add_template_column(array $post_columns): array
    {
        $columns = [
            'template' => __('Template'),
        ];

        if (function_exists('array_insert')) {
            $post_columns = array_insert($post_columns, $columns, 'title');
        } else {
            $post_columns = array_merge($post_columns, $columns);
        }

        return $post_columns;
    }

    /**
     * Display the "Template" column output.
     *
     * @listens WP#action:manage_pages_custom_column
     *     Fires for each custom column of a specific post type in the Posts list table.
     *
     * @fires filter:xyz/post-templates/column/output
     *
     * @param  string  $column_name The name of the column to display.
     * @param  integer $post_id     The current post ID.
     * @return void
     */
    public function display_template_column_output(string $column_name, int $post_id): void
    {
        if ('template' !== $column_name) {
            return;
        }

        $template_name = $this->get_post_template_name($post_id);
        $template_path = get_page_template_slug($post_id);

        /**
         * Filter the post template cell value of the post.
         *
         * @event  filter:xyz/post-templates/column/output
         *
         * @param  string  $output   The value of the cell to display.
         * @param  string  $template The current post's assigned post template.
         * @param  integer $post_id  The current post ID.
         */
        echo apply_filters('xyz/post-templates/column/output', (string) $template_name, $template_path, $post_id);
    }

    /**
     * Register Page Template column as sortable
     *
     * @listens WP#filter:manage_edit-page_sortable_columns
     *
     * @param  array<string, string> $sortable_columns An array of sortable columns.
     * @return array<string, string> $sortable_columns
     */
    public function register_sortable_template_column(array $sortable_columns): array
    {
        $sortable_columns['template'] = 'template';

        return $sortable_columns;
    }

    /**
     * Sort Pages by the Page Template column
     *
     * @listens WP#filter:request
     *
     * @param  array<string, string> $query_vars The array of requested query variables.
     * @return array<string, string> $query_vars
     */
    public function order_template_column(array $query_vars): array
    {
        $vars = [];

        if (isset($query_vars['orderby']) && $query_vars['orderby'] === 'template') {
            $vars = [
                'meta_key' => '_wp_page_template',
                'orderby'  => 'meta_value',
            ];
        }

        return array_merge($query_vars, $vars);
    }

    /**
     * Retrieve the default page template output.
     *
     * @listens filter:xyz/post-templates/column/output
     *
     * @param  string $output The value of the cell to display.
     * @return string
     */
    public function get_default_post_template_output(string $output): string
    {
        if (!$output) {
            /** This filter is documented in wp-admin/includes/meta-boxes.php */
            $default_title = apply_filters('default_page_template_title', __('Default Template'), 'list-table');

            $output = sprintf(
                '<span aria-hidden="true">—</span><span class="screen-reader-text">%s</span>',
                $default_title
            );
        }

        return $output;
    }

    /**
     * Retrieve the post template name for the given post ID or post object..
     *
     * @param  WP_Post|integer $post  Post ID or post object.
     * @return string|null     The name of the post template.
     *
     * @throws InvalidArgumentException If the post is invalid.
     */
    public function get_post_template_name($post): ?string
    {
        $post = get_post($post);

        if (!$post) {
            throw new InvalidArgumentException(
                'Invalid post. Must be a post ID or an instance of WP_Post.'
            );
        }

        $current_template = $post->page_template;

        if (!$current_template || 'default' === $current_template) {
            return null;
        }

        $theme_templates = get_page_templates($post);

        ksort($theme_templates);
        foreach ($theme_templates as $template_name => $template_path) {
            if ($current_template === $template_path) {
                return $template_name;
            }
        }

        return null;
    }
}
