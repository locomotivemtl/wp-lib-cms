<?php

namespace WpLib\Models;

use WpLib\Contracts\Models\PostType;
use WpLib\Exceptions\MissingPostTypeIdentifierException;
use WpLib\Models\AbstractModel;
use WpLib\Modules\Polylang;
use WP_Post_Type;
use WP_Query;

use function WpLib\Support\maybe_add_action;
use function WpLib\Support\maybe_add_filter;

/**
 * Post Type Model
 */
abstract class AbstractPostType extends AbstractModel implements PostType
{
    public const REGISTRATION_PRIORITY = 10;
    #~ abstract public const POST_TYPE = '';

    /**
     * The WordPress post type definition.
     *
     * @var WP_Post_Type
     */
    protected $wp_post_type_object;

    /**
     * The permastruct slug(s).
     *
     * @var string[]
     */
    protected $rewrite_slugs;

    /**
     * Boots the model.
     *
     * @return void
     * @throws MissingPostTypeIdentifierException
     */
    public function boot(): void
    {
        if (!defined('static::POST_TYPE')) {
            throw new MissingPostTypeIdentifierException;
        }

        $this->__register_hooks();
        $this->register_hooks();
    }

    /**
     * Registers required actions and filters.
     *
     * @return void
     */
    protected function __register_hooks(): void
    {
        $post_type = static::POST_TYPE;

        maybe_add_action('init', [$this, 'register_post_type'], static::REGISTRATION_PRIORITY, 0);
        maybe_add_filter("register_{$post_type}_post_type_args", [$this, 'register_post_type_args'], 10, 2);

        add_action('parse_query', [$this, 'init_query_flag'], 1);
        add_action("registered_{$post_type}_post_type", [$this, 'registered_post_type'], 0, 1);
        add_filter("post_type_labels_{$post_type}", [$this, 'register_post_type_labels'], 99, 1);
    }

    /**
     * Determine if the post type is registered.
     *
     * @return bool
     */
    public function is_registered(): bool
    {
        return post_type_exists(static::POST_TYPE);
    }

    /**
     * Determines if the post type is currently queried.
     *
     * @global WP_Query $wp_query WordPress Query object.
     *
     * @param  WP_Query $wp_query A WP_Query instance. Defaults to the $wp_query global.
     * @return bool
     */
    public function is_post_type_queried(WP_Query $wp_query = null): bool
    {
        if (!$wp_query) {
            $wp_query = $GLOBALS['wp_query'];
        }

        $post_types = (array) $wp_query->get('post_type', []);
        return in_array(static::POST_TYPE, $post_types);
    }

    /**
     * Fires after a post type is registered.
     *
     * @listens XYZ#action:registered_{$post_type}_post_type
     *
     * @param   WP_Post_Type $post_type_object Arguments used to register the post type.
     * @return  void
     */
    public function registered_post_type(WP_Post_Type $post_type_object): void
    {
        $this->wp_post_type_object = $post_type_object;
    }

    /**
     * Filters the labels of a specific post type.
     *
     * @listens filter:post_type_labels_{$post_type}
     *
     * @param   object $labels Object with labels for the post type as member variables.
     * @return  object
     */
    public function register_post_type_labels(object $labels): object
    {
        if (!isset($labels->feed_title)) {
            $labels->feed_title = $labels->name;
        }

        return $labels;
    }

    /**
     * Sets the post type's custom `query_flag` key on the {@see WP_Query}.
     *
     * Note: The query_flag property on the post type object is a custom feature
     * of this project and is not natively part of WordPress.
     *
     * @see AbstractTaxonomy::init_query_flag()
     *
     * @listens action:parse_query
     *
     * @param  WP_Query $wp_query The query object (passed by reference).
     * @return void
     */
    public function init_query_flag(WP_Query $wp_query): void
    {
        $ptype_obj  = get_post_type_object(static::POST_TYPE);
        $query_flag = $ptype_obj->query_flag ?? null;


        if ($query_flag && !isset($wp_query->{$query_flag})) {
            $wp_query->{$query_flag} = false;

            $query_var = $ptype_obj->query_var;
            if (false !== $query_var && $wp_query->get($query_var, null)) {
                $wp_query->{$query_flag} = true;
            } elseif ($wp_query->is_post_type_archive(static::POST_TYPE)) {
                $wp_query->{$query_flag} = true;
            } elseif ($wp_query->is_home() && $query_flag === 'is_post') {
                $wp_query->{$query_flag} = true;
            }
        }
    }

    /**
     * Retrieves the permatruct slug(s) as an array from Polylang.
     *
     * @return string[]
     */
    public function get_rewrite_slugs(): array
    {
        $post_type = static::POST_TYPE;

        /** @var PLL_Language[] One or more Polylang language objects. */
        $languages = Polylang\get_languages_list([], null, 'slug');

        if ('post' === $post_type) {
            $prop = 'page_for_posts';
        } else {
            $prop = "page_for_{$post_type}";
        }

        /** @var int[] One or more page IDs. */
        # $slugs = (array) wp_list_pluck( $languages, $prop, 'slug' );
        $slugs = [];
        foreach ($languages as $key => $language) {
            if (isset($language->{$prop})) {
                $slugs[$key] = $language->{$prop};
            }
        }

        if (class_exists('App\\Polylang')) {

            add_filter('get_page_uri', 'Polylang\pll_get_page_uri', 10, 2);
            $slugs = array_map('get_page_uri', $slugs);
            remove_filter('get_page_uri', 'Polylang\pll_get_page_uri', 10);
        } else {
            $slugs = array_map('get_page_uri', $slugs);
        }

        return $slugs;
    }
}
