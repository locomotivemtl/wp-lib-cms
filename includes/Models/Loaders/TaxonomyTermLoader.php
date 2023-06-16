<?php

namespace Locomotive\Cms\Models\Loaders;

use UnexpectedValueException;
use WP_Term;

class TaxonomyTermLoader
{
    private string $taxonomy;
    private array $terms_by_id;
    private array $terms_by_slug;

    public function __construct(string $taxonomy)
    {
        if (!taxonomy_exists($taxonomy)) {
            throw new \RuntimeException('Taxonomy "' . $taxonomy . '" does not exist.', 500);
        }

        $this->taxonomy = $taxonomy;
    }

    /**
     * Retrieve a term object or its field value.
     *
     * @param  int|string  $term  Search for this term by slug or ID.
     * @param  string|null $field Field from the term to retrieve.
     * @return WP_Term|mixed|null A {@see \WP_Term} object, $field value, or NULL.
     */
    public function get_term(int|string $term, string $field = null): mixed
    {
        if (!isset($this->terms_by_id, $this->terms_by_slug)) {
            $terms = $this->fetch_terms();
        }

        if (is_numeric($term) && isset($this->terms_by_id[ $term ])) {
            $term_obj = $this->terms_by_id[ $term ];
            return $field ? $term_obj->{$field} : $term_obj;
        }

        if (is_string($term) && isset($this->terms_by_slug[ $term ])) {
            $term_obj = $this->terms_by_slug[ $term ];
            return $field ? $term_obj->{$field} : $term_obj;
        }

        return null;
    }

    /**
     * Retrieve the term objects or a field value from each one.
     *
     * @param  string|null $field     Field from the terms to retrieve.
     * @param  string|null $index_key Optional. Field from the object to use as keys for the new array.
     * @return WP_Term[]|mixed[] Array of {@see \WP_Term} objects or $field values.
     */
    public function get_terms(string $field = null, string $index_key = null): array
    {
        $terms = $this->fetch_terms();

        if ($field) {
            return wp_list_pluck($this->terms_by_slug, $field, $index_key);
        }

        return $this->terms_by_slug;
    }

    /**
     * Fetch the province objects.
     *
     * @return WP_Term[] Array of {@see \WP_Term} objects.
     * @throws UnexpectedValueException If the top-level terms are missing.
     */
    protected function fetch_terms(): array
    {
        $terms = get_terms([
            'taxonomy'     => $this->taxonomy,
            'parent'       => 0,
            'hide_empty'   => false,
            'hierarchical' => false,
        ]);

        if (empty($terms) || ! is_array($terms)) {
            throw new UnexpectedValueException(
                'Taxonomy "' . $this->taxonomy . '" terms are missing'
            );
        }

        $this->terms_by_id   = array_column($terms, null, 'term_id');
        $this->terms_by_slug = array_column($terms, null, 'slug');

        return $terms;
    }
}