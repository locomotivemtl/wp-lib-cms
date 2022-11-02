<?php

namespace WpLib\Modules\ACF;

use WpLib\Contracts\Bootable;

/**
 * ACF Module: Global Fields
 *
 * In ACF, field values are stored in metadata table for posts, terms, and users.
 * For options pages, field values are stored in the options table.
 *
 * If one has many options pages and many (complex) fields, the options table
 * can easily get messy.
 *
 * This module overrides ACF's default CRUD operations for field values on options pages
 * to serialize field values into a single option for each options page.
 *
 * @todo Update API to use {@see includes/acf-meta-functions.php ACF's meta functions.
 */
class GlobalFields implements Bootable
{
    /**
     * The options name prefix.
     *
     * @var string
     */
    protected $acf_option_prefix = 'wplib_acf_';

    /**
     * Temporary store of option values to save.
     *
     * @var array
     */
    protected $__save_options = [];

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        add_filter('acf/pre_load_value',     [$this, 'load_options_value'], 0, 3);
        add_filter('acf/pre_load_reference', [$this, 'load_options_reference'], 0, 3);

        add_filter('acf/fields/page_link/query',         [$this, 'query_options_field'], 10, 3);
        add_filter('acf/fields/post_object/query',       [$this, 'query_options_field'], 10, 3);
        add_filter('acf/fields/relationship/query',      [$this, 'query_options_field'], 10, 3);
        add_filter('acf/fields/post_relationship/query', [$this, 'query_options_field'], 10, 3);
        add_filter('acf/fields/taxonomy/query',          [$this, 'query_options_field'], 10, 3);

        if (is_admin()) {
            // Hooks before ACF has saved the $_POST data.
            add_action('acf/save_post', [$this, 'before_save_options_values'], 9, 1);

            // Hooks after ACF has saved the $_POST data.
            add_action('acf/save_post', [$this, 'after_save_options_values'], 11, 1);
        }
    }

    /**
     * Determines if the options page exists.
     *
     * @param  string $slug The menu slug.
     * @return bool
     */
    public function is_options_page(string $slug): bool
    {
        return (bool) preg_match('/^options(?:_(.+))?$/', $slug);
    }

    /**
     * Loads a field value from an options dataset.
     *
     * Note: This method overrides ACF's {@see acf_get_value()}
     * to support loading of a value from an options dataset.
     *
     * @listens ACF#filter:acf/pre_load_value
     *
     * @fires ACF#filter:acf/load_value/*
     *
     * @param   mixed       $value     The value to be loaded.
     * @param   int|string  $post_id   The object storage key.
     * @param   array       $field     The field array.
     * @return  mixed
     */
    public function load_options_value($value, $post_id, $field)
    {
        if (!$this->is_options_page($post_id)) {
            return $value;
        }

        // vars
        $field_name = $field['name'];
        $cache_key  = $post_id . ':' . $field_name;

        // check store
        $store = acf_get_store('values');
        if ($store->has($cache_key)) {
            return $store->get($cache_key);
        }

        $option  = $this->acf_option_prefix . $post_id;
        $dataset = get_option($option, []);

        // force array
        if (!is_array($dataset)) {
            $dataset = [];
        }

        // load value
        if (array_key_exists($field_name, $dataset)) {
            $value = $dataset[$field_name];
        }

        // if value was duplicated, it may now be a serialized string!
        $value = maybe_unserialize($value);

        // no value? try default_value
        if ($value === null && isset($field['default_value'])) {
            $value = $field['default_value'];
        }

        /**
         * Filters the $value after it has been loaded.
         *
         * @event ACF#filter:acf/load_value/*
         *
         * @param mixed  $value   The value to preview.
         * @param string $post_id The post ID for this value.
         * @param array  $field   The field array.
         */
        $value = apply_filters('acf/load_value', $value, $post_id, $field);

        // update store
        $store->set($cache_key, $value);

        // return
        return $value;
    }

    /**
     * Loads a field key from an options dataset.
     *
     * Note: This method overrides ACF's {@see acf_get_reference()}
     * to support loading of a reference from an options dataset.
     *
     * @listens ACF#filter:acf/pre_load_reference
     *
     * @fires ACF#filter:acf/load_reference
     *
     * @param   null        $reference   The field key.
     * @param   string      $field_name  The field name.
     * @param   int|string  $post_id     The object storage key.
     * @return  mixed
     */
    public function load_options_reference($reference, $field_name, $post_id)
    {
        if (!$this->is_options_page($post_id)) {
            return $reference;
        }

        $option  = $this->acf_option_prefix . $post_id;
        $dataset = get_option($option, []);

        $name = '_' . $field_name;

        // force array
        if (!is_array($dataset)) {
            $dataset = [];
        }

        // load value
        if (array_key_exists($name, $dataset)) {
            $reference = $dataset[$name];
        }

        /**
         * Filters the reference value.
         *
         * @event ACF#filter:acf/load_reference
         *
         * @param string $reference  The reference value.
         * @param string $field_name The field name.
         * @param int    $post_id    The post ID where meta is stored.
         */
        $reference = apply_filters('acf/load_reference', $reference, $field_name, $post_id);

        // return
        return $reference;
    }

    /**
     * Overrides the ACF saving procedure for options.
     *
     * Save all the field values and references into a single optionset.
     *
     * @listens ACF#action:acf/save_post
     *
     * @param   int|string $post_id The object storage key.
     * @return  void
     */
    public function before_save_options_values($post_id): void
    {
        // Determine if ACF is saving "options"
        if (!$this->is_options_page($post_id)) {
            return;
        }

        // Reset options stack
        $this->__save_options[$post_id] = [];

        add_filter('acf/pre_update_value', [$this, 'update_options_value'], 0, 4);
    }

    /**
     * Restores the ACF saving procedure for options.
     *
     * @listens ACF#action:acf/save_post
     *
     * @param   int|string $post_id The object storage key.
     * @return  void
     */
    public function after_save_options_values($post_id): void
    {
        // Determine if ACF is saving "options"
        if (!$this->is_options_page($post_id)) {
            return;
        }

        $option = $this->acf_option_prefix . $post_id;

        $dataset = get_option($option, []);

        // force array
        if (!is_array($dataset)) {
            $dataset = [];
        }

        // Save new options
        $dataset = array_replace($dataset, $this->__save_options[$post_id]);
        update_option($option, $dataset);

        // Reset options stack
        unset($this->__save_options[$post_id]);

        // Short-circuit 'save_post' logic
        $_POST['acf'] = [];

        remove_filter('acf/pre_update_value', [$this, 'update_options_value']);
    }

    /**
     * Updates a value on an options array.
     *
     * @listens ACF#filter:acf/pre_update_value
     *
     * @fires ACF#filter:acf/update_value/*
     *
     * @param   bool|null   $check     Short-circuit logic.
     * @param   mixed       $value     The value to be saved.
     * @param   int|string  $post_id   The object storage key.
     * @param   array       $field     The field array.
     * @return  boolean
     */
    public function update_options_value($check, $value, $post_id, $field)
    {
        // Determine if ACF is updating "options"
        if (!$this->is_options_page($post_id)) {
            return $check;
        }

        /**
         * Filters the $value before it is updated.
         *
         * @event ACF#filter:acf/update_value/*
         *
         * @param mixed  $value    The value to update.
         * @param string $post_id  The post ID for this value.
         * @param array  $field    The field array.
         * @param mixed  $original The original value before modification.
         */
        $value = apply_filters('acf/update_value', $value, $post_id, $field, $value);

        // For some reason, update_option does not use stripslashes_deep.
        // update_metadata -> https://core.trac.wordpress.org/browser/tags/3.4.2/wp-includes/meta.php#L82: line 101 (does use stripslashes_deep)
        // update_option -> https://core.trac.wordpress.org/browser/tags/3.5.1/wp-includes/option.php#L0: line 215 (does not use stripslashes_deep)
        $value = stripslashes_deep($value);

        // allow null to delete
        if ($value === null) {
            return $this->delete_options_value($post_id, $field);
        }

        $name = $field['name'];
        $ref  = '_' . $name;

        if (!isset($this->__save_options[$post_id])) {
            $this->__save_options[$post_id] = [];
        }

        // update value + reference
        $this->__save_options[$post_id][$name] = $value;
        $this->__save_options[$post_id][$ref]  = $field['key'];

        // clear cache
        acf_flush_value_cache($post_id, $name);

        return true;
    }

    /**
     * Deletes a value from an options array.
     *
     * @fires ACF#action:acf/delete_value/*
     *
     * @see acf_delete_value() in includes/api/api-value.php
     *
     * @param   int|string  $post_id  The object storage key.
     * @param   array       $field    The field array.
     * @return  boolean
     */
    protected function delete_options_value($post_id = 0, $field)
    {
        /**
         * Fires before a value is deleted.
         *
         * @event ACF#action:acf/delete_value/*
         *
         * @param string $post_id The post ID for this value.
         * @param mixed  $name    The meta name.
         * @param array  $field   The field array.
         */
        do_action('acf/delete_value', $post_id, $field['name'], $field);

        // clear cache
        acf_flush_value_cache($post_id, $field['name']);

        return true;
    }

    /**
     * Filters the query arguments for a relational field.
     *
     * This function will ensure that fields for options pages
     * include the current language.
     *
     * @listens filter:acf/fields/page_link/query
     * @listens filter:acf/fields/post_object/query
     * @listens filter:acf/fields/relationship/query
     * @listens filter:acf/fields/post_relationship/query
     * @listens filter:acf/fields/taxonomy/query
     *
     * @param  array      $args    The WP_Query arguments.
     * @param  array      $field   The field structure.
     * @param  int|string $post_id The post ID from which the value was loaded.
     * @return array
     */
    public function query_options_field(array $args, array $field, $post_id): array
    {
        if ($this->is_options_page($post_id) && function_exists('PLL')) {
            $args['lang'] = pll_current_language() ?: pll_default_language();
        }

        return $args;
    }
}
