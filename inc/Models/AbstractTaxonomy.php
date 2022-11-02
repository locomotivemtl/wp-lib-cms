<?php

namespace WpLib\Models;

use WpLib\Contracts\Models\Taxonomy;
use WpLib\Exceptions\MissingTaxonomyIdentifierException;
use WpLib\Models\AbstractModel;
use WP_Taxonomy;
use WP_Query;

use function WpLib\Support\maybe_add_action;
use function WpLib\Support\maybe_add_filter;

/**
 * Taxonomy Model
 */
abstract class AbstractTaxonomy extends AbstractModel implements Taxonomy
{
    public const REGISTRATION_PRIORITY = 10;
    #~ abstract public const TAXONOMY = '';

    /**
     * The WordPress taxonomy definition.
     *
     * @var WP_Taxonomy
     */
    protected $wp_taxonomy_object;

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
     * @throws MissingTaxonomyIdentifierException
     */
    public function boot(): void
    {
        if (!defined('static::TAXONOMY')) {
            throw new MissingTaxonomyIdentifierException;
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
        $taxonomy = static::TAXONOMY;

        maybe_add_action('init', [$this, 'register_taxonomy'], static::REGISTRATION_PRIORITY, 0);
        maybe_add_filter("register_{$taxonomy}_taxonomy_args", [$this, 'register_taxonomy_args'], 10, 3);

        add_action('parse_query', [$this, 'init_query_flag'], 1);
        add_action("registered_{$taxonomy}_taxonomy", [$this, 'registered_taxonomy'], 0, 2);
    }

    /**
     * Determine if the taxonomy is registered.
     *
     * @return bool
     */
    public function is_registered(): bool
    {
        return taxonomy_exists(static::TAXONOMY);
    }

    /**
     * Determines if the taxonomy is currently queried.
     *
     * @global WP_Query $wp_query WordPress Query object.
     *
     * @param  WP_Query $wp_query A WP_Query instance. Defaults to the $wp_query global.
     * @return bool
     */
    public function is_taxonomy_queried(WP_Query $wp_query = null): bool
    {
        if (!$wp_query) {
            $wp_query = $GLOBALS['wp_query'];
        }

        $taxonomies = (array) $wp_query->get('taxonomy', []);
        return in_array(static::TAXONOMY, $taxonomies);
    }

    /**
     * Sets the taxonomy's custom `query_flag` key on the {@see WP_Query}.
     *
     * Note: The query_flag property on the post type object is a custom feature
     * of this project and is not natively part of WordPress.
     *
     * @see AbstractPostType::init_query_flag()
     *
     * @listens action:parse_query
     *
     * @param  WP_Query $wp_query The query object (passed by reference).
     * @return void
     */
    public function init_query_flag(WP_Query $wp_query): void
    {
        $tax_obj    = get_taxonomy(static::TAXONOMY);
        $query_flag = $tax_obj->query_flag ?? null;

        if ($query_flag && !isset($wp_query->{$query_flag})) {
            $wp_query->{$query_flag} = false;
        }

        if ($query_flag && !isset($wp_query->{$query_flag})) {
            $wp_query->{$query_flag} = false;

            $query_var = $ptype_obj->query_var;
            if (false !== $query_var && $wp_query->get($query_var, null)) {
                $wp_query->{$query_flag} = true;
            } elseif ($wp_query->is_tax(static::TAXONOMY)) {
                $wp_query->{$query_flag} = true;
            }
        }
    }

    /**
     * Fires after a taxonomy is registered.
     *
     * @listens XYZ#action:registered_{$taxonomy}_taxonomy
     *
     * @param   array       $object_types    Object type or array of object types.
     * @param   WP_Taxonomy $taxonomy_object Arguments used to register the taxonomy.
     * @return  void
     */
    public function registered_taxonomy(array $object_types, WP_Taxonomy $taxonomy_object): void
    {
        $this->wp_taxonomy_object = $taxonomy_object;
    }
}
