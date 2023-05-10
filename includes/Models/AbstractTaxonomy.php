<?php

namespace Locomotive\Cms\Models;

use Locomotive\Cms\Contracts\Models\Taxonomy;
use Locomotive\Cms\Exceptions\MissingTaxonomyIdentifierException;
use Locomotive\Cms\Models\AbstractModel;
use WP_Taxonomy;
use WP_Query;

use function Locomotive\Cms\Support\maybe_add_action;
use function Locomotive\Cms\Support\maybe_add_filter;

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
        add_action("registered_taxonomy_{$taxonomy}", [$this, 'registered_taxonomy'], 0, 1);
    }

    /**
     * Retrieves a post type object.
     *
     * @return ?WP_Taxonomy
     */
    public function get_taxonomy_object(): ?WP_Taxonomy
    {
        return ($this->wp_taxonomy_object ??= (get_taxonomy(static::TAXONOMY) ?: null));
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
        $tax_obj    = $this->get_taxonomy_object();
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
     * @listens action:registered_taxonomy_{$taxonomy}
     *
     * @global array<string, WP_Taxonomy> $wp_taxonomies
     *
     * @param  string $taxonomy Taxonomy slug.
     * @return void
     */
    public function registered_taxonomy(string $taxonomy): void
    {
        global $wp_taxonomies;

        $this->wp_taxonomy_object = $wp_taxonomies[$taxonomy];
    }
}
