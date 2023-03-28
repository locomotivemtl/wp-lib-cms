<?php

namespace Locomotive\Cms\Modules;

use Locomotive\Cms\Contracts\Bootable;
use Locomotive\Cms\Contracts\Models\Model;
use Locomotive\Cms\Models\PostTypes\Page;
use InvalidArgumentException;
use WP_Post;
use WP_Post_Type;
use WP_Query;

use function Locomotive\Cms\Support\is_frontend_doing_ajax;

/**
 * Module: Page for Posts
 *
 * Adds support for custom post types to use a static page
 * as their archive stand-in.
 *
 * You can enable/disable this feature with:
 *
 * ```
 * add_post_type_support( 'product', 'page-for-posts' );
 * ```
 *
 * @link https://github.com/interconnectit/wp-permastructure
 *       Based on Interconnectit's plugin.
 */
class PageForPosts implements Bootable
{
    /**
     * The feature key.
     *
     * @var string
     */
    const FEATURE_NAME = 'page-for-posts';

    /**
     * The option name of the feature.
     *
     * @var string
     */
    const OPTION_NAME = 'app_page_for_posts';

    /**
     * The settings section name.
     *
     * @var string
     */
    protected $settings_section = 'page_for_posts';

    /**
     * The map of custom post types to pages.
     *
     * @var array
     */
    protected $pages_for_posts;

    /**
     * The cache of pages for posts.
     *
     * @var array
     */
    protected static $page_cache = [];

    /**
     * List of post types to exclude.
     *
     * @var string[]
     */
    protected $ignored_post_types = [
        'post',
        'page',
        'attachment',
    ];

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        $option = static::OPTION_NAME;

        add_action('admin_init',  [$this, 'register_settings']);
        add_action('parse_query', [$this, 'parse_query'], 5);

        /**
         * Fires after the value of a specific option has been successfully updated.
         */
        add_action("update_option_{$option}", 'flush_rewrite_rules');

        add_action('post_updated',       [$this, 'check_for_changed_slugs'], 12, 3);
        add_action('before_delete_post', [$this, 'restrict_post_deletion'], 0, 1);
        add_action('wp_trash_post',      [$this, 'restrict_post_deletion'], 0, 1);

        if (is_admin() && !is_frontend_doing_ajax()) {
            add_action('edit_form_after_title', [$this, 'page_for_posts_notice']);
        }

        add_filter('post_type_labels_page',   [$this, 'post_type_labels']);
        add_filter('display_post_states',     [$this, 'display_post_states'], 10, 2);

        add_filter('get_the_archive_title',   [$this, 'get_the_archive_title']);
        add_filter('post_type_archive_title', [$this, 'post_type_archive_title'], 10, 2);

        add_filter('post_type_link',          [$this, 'post_type_link'], 1, 2);
        add_filter('post_type_archive_link',  [$this, 'post_type_archive_link'], 10, 2);

        /**
         * Module Compatibility
         */
        add_filter('locomotive/model/get_rewrite_slugs', [$this, 'filter_model_rewrite_slugs'], 10, 2);

        /**
         * Advanced Custom Fields Compatibility
         */
        add_filter('acf/location/rule_values/page_type', [$this, 'acf_location_page_type_rule_values']);
        add_filter('acf/location/rule_match/page_type',  [$this, 'acf_location_page_type_rule_match'], 10, 3);
    }

    /**
     * Filters the available values for the "page_type" location rule type.
     *
     * This function will add pages for custom post types.
     *
     * @see \acf_location_page_type
     *
     * @listens ACF#filter:acf/location/rule_values/page_type
     * @listens ACF#filter:acf/location/rule_values/{$type}
     *
     * @param  array $values An array of available values for a given rule.
     * @return array
     */
    public function acf_location_page_type_rule_values(array $values = []): array
    {
        $values['post_type_archive'] = __('All post type archives', 'locomotive');

        $archives = static::get_pages_for_posts();

        foreach ($archives as $post_type => $page_id) {
            if (post_type_exists($post_type)) {
                $post_type_obj = get_post_type_object($post_type);
                $values[$post_type] = $post_type_obj->labels->page_for_posts ?? $post_type_obj->name;
            }
        }

        ksort($values);

        return $values;
    }

    /**
     * Match modified values for "Page Type" location rule
     *
     * @listens filter:acf/location/rule_match/page_type
     * @listens filter:acf/location/rule_match/{$type}
     *
     * @param  bool  $result The true / false variable which must be returned.
     * @param  array $rule   The current rule that you are matching against.
     * @param  array $screen Data about the current edit screen, includes any data posted in an AJAX call.
     * @return bool
     */
    public function acf_location_page_type_rule_match(bool $result, array $rule, array $screen): bool
    {
        $post_id = acf_maybe_get($screen, 'post_id');
        if (!$post_id) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        if ('post_type_archive' === $rule['value']) {
            $archives = static::get_pages_for_posts(true);

            if (function_exists('PLL')) {
                $translations = pll_get_post_translations($post->ID);

                if ('==' === $rule['operator']) {
                    $result = (bool) array_intersect($translations, $archives);
                } elseif ('!=' === $rule['operator']) {
                    $result = !array_intersect($translations, $archives);
                }
            } else {
                if ('==' === $rule['operator']) {
                    $result = (false !== array_search($post->ID, $archives));
                } elseif ('!=' === $rule['operator']) {
                    $result = (false === array_search($post->ID, $archives));
                }
            }
        } elseif (post_type_supports($rule['value'], static::FEATURE_NAME)) {
            $page_id = static::get_page_id_for_post_type($rule['value']);
            if ($page_id) {
                if ('==' === $rule['operator']) {
                    $result = ($post->ID === $page_id);
                } elseif ('!=' === $rule['operator']) {
                    $result = !($post->ID === $page_id);
                }
            }
        }

        return $result;
    }

    /**
     * Filters the array of rewrite slugs for the given post type or taxonomy.
     *
     * @listens filter:locomotive/model/get_rewrite_slugs
     *
     * @param  string[] $slugs An array of permastruct slugs.
     * @param  Model    $model The post type or taxonomy model.
     * @return string[]
     */
    public function filter_model_rewrite_slugs($slugs, Model $model)
    {
        if (function_exists('pll_languages_list')) {
            return $this->pll_get_post_type_rewrite_slugs($model::POST_TYPE);
        }

        /** @var string[] One page URI. */
        return (array) static::get_page_uri_for_post_type($model::POST_TYPE);
    }

    /**
     * Retrieves the permatruct slug(s) for the given post type.
     *
     * @param  string $post_type Post type name.
     * @return string[] An array of rewrite slugs.
     */
    public function pll_get_post_type_rewrite_slugs(string $post_type): array
    {
        // Removed since we get this from the param
        // $post_type = static::POST_TYPE;

        /** @var PLL_Language[] One or more Polylang language objects. */
        $languages = pll_languages_list(['fields' => null]);

        if ('post' === $post_type) {
            $prop = 'page_for_posts';
        } else {
            $prop = "page_for_{$post_type}";
        }

        /** @var int[] One or more page IDs. */
        $slugs = (array) wp_list_pluck($languages, $prop, 'slug');
        $slugs = with_filter(function () use ($slugs) {
            return array_map('get_page_uri', $slugs);
        }, 'get_page_uri', 'pll_get_page_uri', 10, 2);

        return $slugs;
    }

    /**
     * Output a notice when editing the page for posts of a custom post type.
     *
     * @listens WP#action:edit_form_after_title
     *
     * @param   WP_Post $post Post object.
     * @return  void
     */
    public function page_for_posts_notice(WP_Post $post)
    {
        $archives = static::get_pages_for_posts();

        if ($archives) {
            $show_notice = false;
            $post_type   = null;

            if (function_exists('PLL')) {
                foreach ($archives as $post_type => $page_id) {
                    $field        = 'page_for_' . $post_type;
                    $translations = PLL()->model->get_languages_list(['fields' => $field]);

                    if (in_array($post->ID, $translations)) {
                        $show_notice = true;
                        break;
                    }
                }
            } elseif ($post_type = array_search($post->ID, $archives)) {
                $show_notice = true;
            }

            if ($show_notice) {
                $post_type_object = get_post_type_object($post_type);

                $notice = sprintf(
                    __('You are currently editing the page that shows your latest %s.', 'locomotive'),
                    strtolower($post_type_object->labels->name)
                );

                echo '<div class="notice notice-warning inline"><p>' . $notice . '</p></div>';
            }
        }
    }

    /**
     * Filters the archive title for a custom post type archive.
     *
     * @listens WP#filter:get_the_archive_title
     *
     * @param   string  $title  Archive title to be displayed.
     * @return  string  The archive title.
     */
    public function get_the_archive_title($title)
    {
        if (is_post_type_archive()) {
            /** Ignore the "Archives: " prefix */
            $title = post_type_archive_title('', false);
        }

        return $title;
    }

    /**
     * Filters the post type archive title.
     *
     * @listens WP#filter:post_type_archive_title
     *
     * @param   string  $title      Archive title to be returned.
     * @param   string  $post_type  Post type.
     * @return  string  The post type archive title.
     */
    public function post_type_archive_title($title, $post_type)
    {
        $queried_object = get_queried_object();

        if ($queried_object instanceof WP_Post_Type) {
            $queried_object = static::get_page_for_post_type($queried_object->name);
        }

        if ($queried_object instanceof WP_Post) {
            $page = $queried_object;

            if ($page->post_type === Page::POST_TYPE && isset($page->post_title)) {
                /**
                 * Filters the page title for a single post.
                 *
                 * @see single_post_title()
                 *
                 * @event WP#filter:single_post_title documented in general-template.php
                 *
                 * @param string $_post_title The single post page title.
                 * @param object $_post       The current queried object as returned by get_queried_object().
                 */
                $title = apply_filters('single_post_title', $page->post_title, $page);
            }
        }

        return $title;
    }

    /**
     * Retrieves the permalink for a post type archive.
     *
     * @listens WP#filter:post_type_link
     *
     * @param  string  $post_link The post permalink.
     * @param  WP_Post $post      The post in question.
     * @return string|false The post's permalink.
     */
    public function post_type_link($post_link, WP_Post $post)
    {
        $post_type = $post->post_type;

        if (!post_type_supports($post_type, static::FEATURE_NAME)) {
            return $post_link;
        }

        if (!get_option('permalink_structure')) {
            return $post_link;
        }

        if (false === strpos($post_link, '%')) {
            return $post_link;
        }

        $page_lang = function_exists('pll_get_post_language') ? pll_get_post_language($post->ID) : null;
        $page_uri  = static::get_page_uri_for_post_type($post_type, $page_lang);
        $post_link = str_replace("%page_for_$post->post_type%", $page_uri, $post_link);

        return $post_link;
    }

    /**
     * Retrieves the permalink for a post type archive.
     *
     * @listens WP#filter:post_type_archive_link
     *
     * @param   string  $link       The post type archive permalink.
     * @param   string  $post_type  Post type name.
     * @return  string|false The post type archive permalink.
     */
    public function post_type_archive_link($link, $post_type)
    {
        if (!post_type_supports($post_type, static::FEATURE_NAME)) {
            return $link;
        }

        if (!get_option('permalink_structure')) {
            return $link;
        }

        $post_type_obj = get_post_type_object($post_type);

        if (false === $post_type_obj->has_archive) {
            return $link;
        }

        $page_id = static::get_page_id_for_post_type($post_type);
        if ($page_id) {
            $link = get_permalink($page_id);
        }

        return $link;
    }

    /**
     * Fires once an existing post has been updated.
     *
     * @listens WP#action:post_updated
     *
     * @see wp_check_for_changed_slugs()
     *
     * @param  int     $post_ID      Post ID.
     * @param  WP_Post $post_after   Post object following the update.
     * @param  WP_Post $post_before  Post object before the update.
     * @return void
     */
    public function check_for_changed_slugs(
        int $post_ID,
        WP_Post $post_after,
        WP_Post $post_before
    ): void {
        if (!static::is_page_for_posts($post_ID)) {
            return;
        }

        if (
            $post_after->post_name   === $post_before->post_name &&
            $post_after->post_parent === $post_before->post_parent
        ) {
            return;
        }

        flush_rewrite_rules();
    }

    /**
     * Fires before a post is sent to the trash or deleted.
     *
     * Prevent pages for posts from being deleted.
     *
     * @listens WP#action:wp_trash_post
     * @listens WP#action:before_delete_post
     *
     * @param   int $post_id Post ID.
     * @return  void
     */
    public function restrict_post_deletion(int $post_id): void
    {
        $post = get_post($post_id);

        if ('page' !== $post->post_type) {
            return;
        }

        if ('page' === get_option('show_on_front')) {
            if (function_exists('PLL')) {
                $language   = PLL()->model->post->get_language($post_id);
                $front_page = isset($language->page_on_front) ? (int) $language->page_on_front : 0;
            } else {
                $front_page = ('page' === get_option('show_on_front')) ? (int) get_option('page_on_front') : 0;
            }

            if ($front_page === $post_id) {
                wp_die(__('You cannot delete the front page.', 'locomotive'), 403);
            }
        }

        $post_type = static::get_post_type_for_page($post);

        if (!$post_type) {
            return;
        }

        $post_type_object = get_post_type_object($post_type);

        $message = sprintf(
            __('You cannot delete the page while it is assigned to show your latest %s.', 'locomotive'),
            strtolower($post_type_object->labels->name)
        );

        wp_die($message, 403);
    }

    /**
     * Fires after the main query vars have been parsed.
     *
     * @listens WP#action:parse_query documented in wp-includes/query.php
     *
     * @global  \WP $wp Current WordPress environment instance.
     *
     * @param  WP_Query $wp_query The WP_Query instance.
     * @return void
     */
    public function parse_query(WP_Query $wp_query): void
    {
        global $wp;

        if (!$wp_query->is_main_query()) {
            return;
        }

        if (is_admin() && !is_frontend_doing_ajax()) {
            return;
        }

        $post_type = $wp_query->get('post_type', null);
        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }

        if (post_type_supports($post_type, static::FEATURE_NAME)) {
            $ptype_obj = get_post_type_object($post_type);
            $query_var = $ptype_obj->query_var;

            if (false !== $query_var && $wp_query->get($query_var, null)) {
                if ($wp_query->is_single && $wp->request) {
                    $page = get_page_by_path($wp->request);

                    if ($page) {
                        $query_flag = $ptype_obj->query_flag ?? null;

                        $wp_query->queried_object    = $page;
                        $wp_query->queried_object_id = (int) $page->ID;

                        $wp_query->is_page   = true;
                        $wp_query->is_single = false;

                        if (isset($wp_query->{$query_flag})) {
                            $wp_query->{$query_flag} = false;
                        }

                        $qv = &$wp_query->query_vars;

                        $qv['pagename'] = $wp->request;
                        unset($qv[$query_var], $qv['post_type'], $qv['name']);

                        return;
                    }
                }
            }
        }

        if (!$wp_query->is_post_type_archive() && !$wp_query->is_home() && !$wp_query->is_category()) {
            return;
        }

        $archives = static::get_pages_for_posts(true);

        if (empty($_post_type) || !isset($archives[$post_type])) {
            return;
        }

        $page = static::get_page_for_post_type($post_type);
        set_queried_object($page, $wp_query);
    }

    /**
     * Declare the queried object as a page for posts.
     *
     * @used-by self::parse_query()
     *
     * @param  WP_Query $wp_query The WP_Query instance (passed by reference).
     * @return void
     */
    private function enable_posts_page(WP_Query $wp_query): void
    {
        $wp_query->is_single            = false;
        $wp_query->is_singular          = false;
        $wp_query->is_page              = false;
        $wp_query->is_home              = false;
        $wp_query->is_archive           = true;  // false ?
        $wp_query->is_post_type_archive = true;  // false ?
        $wp_query->is_posts_page        = false; // true ?
    }

    /**
     * Filter the labels of a specific post type.
     *
     * The dynamic portion of the hook name, `$post_type`, refers to
     * the post type slug.
     *
     * @listens WP#filter:post_type_labels_{$post_type} documented in wp-includes/post.php
     *
     * @see get_post_type_labels() for the full list of labels.
     *
     * @param  object  $labels  Object with labels for the post type as member variables.
     * @return object
     */
    public function post_type_labels($labels)
    {
        if (!isset($labels->page_for_posts)) {
            $labels->page_for_posts = sprintf(
                _x('%s Page', 'Page for post type', 'locomotive'),
                $labels->name
            );
        }

        if (!isset($labels->page_for_posts_field)) {
            $labels->page_for_posts_field = sprintf(
                _x('%1$s page: %2$s', '1. Post type; 2. "%s"', 'locomotive'),
                $labels->name,
                '%s'
            );
        }

        return $labels;
    }

    /**
     * Filter the default post display states used in the posts list table.
     *
     * @listens WP#filter:display_post_states documented in wp-admin/includes/template.php
     *
     * @param  array   $post_states An array of post display states.
     * @param  WP_Post $post        The current post object.
     * @return array
     */
    public function display_post_states(array $post_states, WP_Post $post): array
    {
        $archives = static::get_pages_for_posts();

        if ($archives) {
            if (function_exists('PLL')) {
                foreach ($archives as $post_type => $page_id) {
                    $field        = 'page_for_' . $post_type;
                    $translations = PLL()->model->get_languages_list(['fields' => $field]);

                    if (in_array($post->ID, $translations)) {
                        $post_type_obj = get_post_type_object($post_type);
                        $post_states[$field] = $post_type_obj->labels->page_for_posts;
                    }
                }
            } else {
                if ($post_type = array_search($post->ID, $archives)) {
                    $field         = 'page_for_' . $post_type;
                    $post_type_obj = get_post_type_object($post_type);

                    $post_states[$field] = $post_type_obj->labels->page_for_posts;
                }
            }
        }

        return $post_states;
    }

    /**
     * Retrieve the "page_for_posts_field" label from the given post type.
     *
     * @param  string|object $post_type Name of the post type to retrieve from.
     * @return string
     */
    private function get_field_label_from($post_type)
    {
        if (is_string($post_type)) {
            $post_type = get_post_type_object($post_type);
        }

        if (!is_object($post_type)) {
            throw new InvalidArgumentException('The given post type must be an object.');
        }

        if (isset($post_type->labels->page_for_posts_field)) {
            return $post_type->labels->page_for_posts_field;
        } else {
            return sprintf(
                _x('%1$s: %2$s', 'Used to explain or start an enumeration', 'locomotive'),
                $post_type->label,
                '%s'
            );
        }
    }

    /**
     * Register the section and settings to allow administrators to choose a static page
     * for post types that support this feature.
     *
     * @listens WP#action:load-{$page_hook} (`options-reading.php`)
     *     documented in wp-admin/admin.php
     *
     * @return void
     */
    public function register_settings(): void
    {
        $post_types = $this->get_post_types();

        if (count($post_types)) {
            register_setting(
                'reading',
                static::OPTION_NAME,
                [$this, 'sanitize_settings']
            );

            add_settings_section(
                $this->settings_section,
                sprintf(_x('%s Options', 'page for posts', 'locomotive'), get_bloginfo('name')),
                '',
                'reading'
            );

            add_settings_field(
                static::OPTION_NAME,
                __('Pages to use as archives', 'locomotive'),
                [$this, 'render_archive_settings_field'],
                'reading',
                $this->settings_section,
                [
                    'post_types' => $post_types
                ],
            );
        }
    }

    /**
     * @param  array  $args
     * @return void
     */
    public function render_page_settings_field($args = [])
    {
        wp_dropdown_pages([
            'name'              => $args['option_name'],
            'echo'              => 1,
            'show_option_none'  => __('&mdash; Select &mdash;'),
            'option_none_value' => '0',
            'selected'          => get_option($args['option_name']),
        ]);
    }

    /**
     * Sanitize the settings fields.
     *
     * @param  array  $value  The "permalink_structures" input value.
     * @return array
     */
    public function sanitize_settings($value)
    {
        if (is_integer($value)) {
            $value = [$value];
        }
        foreach ($value as $post_type => &$page_ID) {
            /** @todo Document the filter */
            $page_ID = apply_filters(
                "locomotive/page-for-posts/{$post_type}/input",
                absint($page_ID)
            );
        }

        return $value;
    }

    /**
     * Render the fields with the desired inputs as part of the Reading Settings page.
     *
     * @throws InvalidArgumentException
     * @param  array  $args  Optional. Extra arguments used when outputting the field.
     * @return void
     */
    public function render_archive_settings_field($args = []): void
    {
        if (!isset($args['post_types'])) {
            throw new InvalidArgumentException(
                'The field must be define at least one post type.'
            );
        }

        $archives = static::get_pages_for_posts();

        echo '<fieldset>' .
            '<legend class="screen-reader-text"><span>' .
            __('Pages to use as archives', 'locomotive') .
            '</span></legend>';

        foreach ($args['post_types'] as $post_type_obj) {
            $post_type = $post_type_obj->name;

            $id = esc_attr(sprintf('page_for_%s', $post_type));

            /** @todo Document the filter */
            $value = apply_filters(
                "locomotive/page-for-posts/{$post_type}/output",
                (isset($archives[$post_type]) ? $archives[$post_type] : null)
            );

            printf(
                sprintf(
                    '<p><label for="%1$s">%2$s</label></p>',
                    $id,
                    $this->get_field_label_from($post_type)
                ),
                wp_dropdown_pages([
                    'echo'              => 0,
                    'name'              => esc_attr(sprintf('%1$s[%2$s]', static::OPTION_NAME, $post_type)),
                    'id'                => $id,
                    'show_option_none'  => __('&mdash; Select &mdash;'),
                    'option_none_value' => '0',
                    'selected'          => esc_attr($value)
                ])
            );
        }

        echo '</fieldset>';
    }

    /**
     * Retrieve the post type objects that support this feature.
     *
     * @return array
     */
    protected function get_post_types(): array
    {
        $post_types = get_post_types_by_support(static::FEATURE_NAME);

        if (is_array($this->ignored_post_types)) {
            $post_types = array_diff($post_types, $this->ignored_post_types);
            $post_types = array_values($post_types);
        }

        return array_map('get_post_type_object', $post_types);
    }

    /**
     * Retrieve the page IDs for posts.
     *
     * @param  bool $posts Include page for 'post' post type.
     * @return array
     */
    public static function get_pages_for_posts(bool $include_posts = false): array
    {
        $pages = get_option(static::OPTION_NAME, []);

        if (
            $include_posts &&
            'page' === get_option('show_on_front') &&
            ($page_ID = get_option('page_for_posts'))
        ) {
            $pages['post'] = $page_ID;
        }

        return array_map('intval', $pages);
    }

    /**
     * Retrieve the current or given locale's page IDs for posts.
     *
     * @param  bool  $posts Include page for 'post' post type.
     * @param  mixed $lang  Optional. The language code or object of the desired translation.
     * @return array
     */
    public static function get_translated_pages_for_posts(bool $include_posts = false, $lang = null): array
    {
        if (!function_exists('PLL')) {
            return static::get_pages_for_posts($include_posts);
        }

        $key = null;

        if ($lang === null) {
            $lang = PLL()->curlang;
            $key = $lang->slug;
        } elseif (is_string($lang)) {
            $lang = PLL()->model->get_language($lang);
            $key = $lang->slug;
        } elseif (isset($lang->slug)) {
            $key = $lang->slug;
        }

        if (isset(static::$page_cache[$key][$include_posts])) {
            return static::$page_cache[$key][$include_posts];
        }

        $pages = static::get_pages_for_posts($include_posts);

        foreach ($pages as $post_type => $page_id) {
            if ('post' === $post_type) {
                $prop = 'page_for_posts';
            } else {
                $prop = 'page_for_' . $post_type;
            }

            if (isset($lang->{$prop})) {
                $pages[$post_type] = $lang->{$prop};
            }
        }

        return static::$page_cache[$key][$include_posts] = $pages;
    }

    /**
     * Determine if the page is a page for posts.
     *
     * @param  int|\WP_Post $page Page ID or {@see WP_Post} object.
     * @return bool
     */
    public static function is_page_for_posts($page)
    {
        return (bool) static::get_post_type_for_page($page);
    }

    /**
     * Retrieve the post type assigned to the page.
     *
     * @param  int|\WP_Post $page  Page ID or {@see WP_Post} object.
     * @return string|null
     */
    public static function get_post_type_for_page($page)
    {
        $page = get_page($page);

        if (!$page) {
            return null;
        }

        $archives = static::get_pages_for_posts(true);

        if ($archives) {
            if (function_exists('PLL')) {
                $lang = PLL()->model->post->get_language($page->ID);

                if ($lang) {
                    foreach ($archives as $post_type => $page_id) {
                        if ('post' === $post_type) {
                            $prop = 'page_for_posts';
                        } else {
                            $prop = 'page_for_' . $post_type;
                        }

                        if (isset($lang->{$prop}) && $lang->{$prop} === $page->ID) {
                            return $post_type;
                        }
                    }
                }
            } elseif ($post_type = array_search($page->ID, $archives)) {
                return $post_type;
            }
        }

        return null;
    }

    /**
     * Retrieve the page ID assigned to the post type.
     *
     * @param  string   $post_type Post type name.
     * @param  mixed    $lang      Optional. The language code or object of the desired translation.
     * @return int|null Page ID, NULL on error.
     */
    public static function get_page_id_for_post_type($post_type, $lang = null)
    {
        $pages = static::get_pages_for_posts(true);

        if (isset($pages[$post_type])) {
            if (function_exists('PLL')) {
                if ('post' === $post_type) {
                    $prop = 'page_for_posts';
                } else {
                    $prop = 'page_for_' . $post_type;
                }

                if ($lang === null) {
                    $lang = PLL()->curlang;
                } elseif (is_string($lang)) {
                    $lang = PLL()->model->get_language($lang);
                }

                if (is_object($lang) && isset($lang->{$prop})) {
                    return $lang->{$prop};
                }
            }

            return $pages[$post_type];
        }

        return null;
    }

    /**
     * Retrieve the page object for the page assigned to the post type.
     *
     * @param  string       $post_type Post type name.
     * @param  mixed        $lang      Optional. The language code or object of the desired translation.
     * @return WP_Post|null Page object, NULL on error.
     */
    public static function get_page_for_post_type($post_type, $lang = null)
    {
        $page_id = static::get_page_id_for_post_type($post_type, $lang);

        if ($page_id) {
            $page = get_page($page_id);
            $page->related_post_type = $post_type;
            return $page;
        }

        return null;
    }

    /**
     * Retrieve the URI path for the page assigned to the post type.
     *
     * @param  string      $post_type Post type name.
     * @param  mixed       $lang      Optional. The language code or object of the desired translation.
     * @return string|null Page URI, NULL on error.
     */
    public static function get_page_uri_for_post_type($post_type, $lang = null)
    {
        $page_id = static::get_page_id_for_post_type($post_type, $lang);

        if ($page_id) {
            return get_page_uri($page_id);
        }

        return null;
    }
}
