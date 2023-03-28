<?php

namespace Locomotive\Cms\Modules\ACF;

use Locomotive\Cms\Contracts\Bootable;

/**
 * ACF Module: Field Relationships
 *
 * This module provides support for bidirectional field relationships.
 *
 * Based on {@link https://github.com/Hube2/acf-post2post Hube2/acf-post2post}.
 */
class FieldRelationships implements Bootable
{
    /**
     * Boots the module and registers actions and filters.
     *
     * @todo Register field settings to use the {@link https://github.com/acf-extended/ACF-Extended/blob/master/includes/fields-settings/bidirectional.php UI to associate relationships}.
     *
     * @return void
     */
    public function boot(): void
    {
        add_filter('acf/update_value/type=post_relationship', [$this, 'update_value_for_relationship'], 11, 3);
        add_filter('acf/update_value/type=relationship',      [$this, 'update_value_for_relationship'], 11, 3);
        add_filter('acf/update_value/type=post_object',       [$this, 'update_value_for_relationship'], 11, 3);

        add_filter('acf/fields/post_relationship/sync',   [$this, 'sync_relationship_settings'], 10, 3);
        add_filter('acf/fields/relationship/sync',        [$this, 'sync_relationship_settings'], 10, 3);
        add_filter('acf/fields/post_object/sync',         [$this, 'sync_relationship_settings'], 10, 3);

        add_filter('acf/fields/post_relationship/detach', [$this, 'sync_relationship_settings'], 10, 3);
        add_filter('acf/fields/relationship/detach',      [$this, 'sync_relationship_settings'], 10, 3);
        add_filter('acf/fields/post_object/detach',       [$this, 'sync_relationship_settings'], 10, 3);

        add_filter('acf/fields/post_relationship/attach', [$this, 'sync_relationship_settings'], 10, 3);
        add_filter('acf/fields/relationship/attach',      [$this, 'sync_relationship_settings'], 10, 3);
        add_filter('acf/fields/post_object/attach',       [$this, 'sync_relationship_settings'], 10, 3);
    }

    /**
     * Default settings for post relationships.
     *
     * @listens filter:acf/fields/post_relationship/sync
     * @listens filter:acf/fields/relationship/sync
     * @listens filter:acf/fields/post_object/sync
     *
     * @param  array   $args    Array of relationship arguments.
     * @param  integer $post_id The post ID to associate the relationship to.
     * @param  array   $field   The primary field structure.
     * @return array
     */
    public function sync_relationship_settings(array $args, $post_id, $field): array
    {
        if ($field['type'] === 'post_object') {
            if (!$field['multiple']) {
                $args['max']      = 1;
                $args['multiple'] = false;
            }
        } elseif ($field['type'] === 'relationship') {
            $args['max'] = (int) $field['max'];
            $args['min'] = (int) $field['min'];
        }

        $args['attach'] = true;

        return $args;
    }

    /**
     * Sync the list of post IDs in $value across related fields before the $field is saved to the database.
     *
     * @listens filter:acf/update_value/type=post_relationship
     * @listens filter:acf/update_value/type=relationship
     * @listens filter:acf/update_value/type=post_object
     *
     * @fires EDQ#filter:acf/fields/{$field_type}/pre_sync_value
     * @fires EDQ#filter:acf/fields/{$field_type}/sync
     * @fires EDQ#filter:acf/fields/{$field_type}/sync/name={$field_name}
     * @fires EDQ#filter:acf/fields/{$field_type}/sync/key={$field_key}
     *
     * @param  mixed   $value   The value of the primary field as found in the database.
     * @param  integer $post_id The post ID which the value was loaded from.
     * @param  array   $field   The primary field structure.
     * @return mixed The $value of the primary field.
     */
    public function update_value_for_relationship($value, $post_id, $field)
    {
        // Bail early if the field has no relationships
        if (empty($field['relations'])) {
            return $value;
        }

        /**
         * Filters whether to short-circuit the post synchronisation process.
         *
         * Returning a non-TRUE value to the filter will short-circuit
         * the method, bailing early.
         *
         * @event EDQ#filter:acf/fields/{$field_type}/pre_sync_value
         *
         * @param bool    $sync    If TRUE (default), proceed with field synchronisation.
         * @param mixed   $value   The value of the primary field as found in the database.
         * @param integer $post_id The post ID which the value was loaded from.
         * @param array   $field   The primary field structure.
         */
        $sync = apply_filters("acf/fields/{$field['type']}/pre_sync_value", true, $value, $post_id, $field);
        if (!$sync) {
            return $value;
        }

        /**
         * Filters the arguments used to sync posts.
         *
         * @event EDQ#filter:acf/fields/{$field_type}/sync
         * @event EDQ#filter:acf/fields/{$field_type}/sync/name={$field_name}
         * @event EDQ#filter:acf/fields/{$field_type}/sync/key={$field_key}
         *
         * @param array   $args {
         *     Array of relationship arguments.
         *
         *     @type bool|null $attach   Whether to force post relationship attachments.
         *         NULL to associate only new values, TRUE to associate all values, FALSE to prevent associations.
         *         Defaults to NULL.
         *     @type bool|null $detach   Whether to force post relationships detachments.
         *         NULL or TRUE to dissociate old values, FALSE to prevent dissociations.
         *         Defaults to NULL.
         * }
         * @param integer $post_id    The post ID to associate the relationship to.
         * @param array   $field      The primary field structure.
         */
        $args = apply_filters("acf/fields/{$field['type']}/sync", [], $post_id, $field);
        $args = apply_filters("acf/fields/{$field['type']}/sync/name={$field['name']}", $args, $post_id, $field);
        $args = apply_filters("acf/fields/{$field['type']}/sync/key={$field['key']}", $args, $post_id, $field);

        // defaults
        $args = acf_parse_args($args, [
            'force'     => false,
            'attach'    => null,
            'detach'    => null,
        ]);

        if ($args['force']) {
            $args['attach'] = $args['detach'] = true;
        }

        // Get the previous raw value from using {@see \get_post_meta()} to avoid conflicts
        $old_values = maybe_unserialize(get_post_meta($post_id, $field['name'], true));

        // Ensure array of integers
        $old_values = acf_get_array($old_values);
        $old_values = array_map('intval', $old_values);

        // Get a copy of the new value to avoid issues
        $new_values = $value;

        // Ensure array of integers
        $new_values = acf_get_array($new_values);
        $new_values = array_map('intval', $new_values);

        if ($args['attach'] === false) {
            $attach_ids = [];
        } elseif ($args['attach'] === true) {
            $attach_ids = $new_values;
        } else {
            // Compare values and get differences
            $attach_ids = array_values(array_diff($new_values, $old_values));
        }

        if ($args['detach'] === false) {
            $detach_ids = [];
        } else {
            // Compare values and get differences
            $detach_ids = array_values(array_diff($old_values, $new_values));
        }

        // Bail if there is nothing to sync
        if (empty($attach_ids) && empty($detach_ids)) {
            return $value;
        }

        foreach ($field['relations'] as $_field_key) {
            $_field = acf_get_field($_field_key);

            if ($_field) {
                $_field['_sync'] = $args;

                foreach ($detach_ids as $_post_id) {
                    $this->detach_relationship($post_id, $_post_id, $_field);
                }

                foreach ($attach_ids as $_post_id) {
                    $this->attach_relationship($post_id, $_post_id, $_field);
                }
            }
        }

        return $value;
    }

    /**
     * Detach the post ID from the given parent post ID.
     *
     * @param  integer $related_id The post ID to detach.
     * @param  integer $post_id    The post ID to dissociate the relationship from.
     * @param  array   $field      The field to update.
     * @return bool    TRUE on successful update, FALSE on failure.
     */
    private function detach_relationship($related_id, $post_id, $field)
    {
        /**
         * Filters the arguments used to detach a post.
         *
         * @event EDQ#filter:acf/fields/{$field_type}/detach
         * @event EDQ#filter:acf/fields/{$field_type}/detach/name={$field_name}
         * @event EDQ#filter:acf/fields/{$field_type}/detach/key={$field_key}
         *
         * @param array   $args {
         *     Array of relationship arguments.
         *
         *     @type bool|null $attach   Whether to force post relationship attachments.
         *         NULL to associate only new values, TRUE to associate all values, FALSE to prevent associations.
         *         Defaults to NULL.
         *     @type bool|null $detach   Whether to force post relationships detachments.
         *         NULL or TRUE to dissociate old values, FALSE to prevent dissociations.
         *         Defaults to NULL.
         *     @type bool      $replace  Whether to replace an existing value in a related field.
         *     @type bool      $multiple Whether the field allows multiple posts (see Post Object field type).
         *     @type integer   $max      Maximum number of posts allowed (see Post Relationship field type).
         * }
         * @param integer $post_id    The post ID to associate the relationship to.
         * @param array   $field      The primary field structure.
         */
        $args = apply_filters("acf/fields/{$field['type']}/detach", [], $post_id, $field);
        $args = apply_filters("acf/fields/{$field['type']}/detach/name={$field['name']}", $args, $post_id, $field);
        $args = apply_filters("acf/fields/{$field['type']}/detach/key={$field['key']}", $args, $post_id, $field);

        // defaults
        $args = acf_parse_args($args, [
            'multiple' => true,
        ]);

        $old_values = maybe_unserialize(get_post_meta($post_id, $field['name'], true));

        // Ensure array
        $old_values = acf_get_array($old_values);

        // Bail if there is nothing to detach
        if (empty($old_values)) {
            return false;
        }

        // Ensure integers
        $old_values = array_map('intval', $old_values);

        $new_values = [];
        foreach ($old_values as $_value) {
            if ($_value !== $related_id) {
                $new_values[] = $_value;
            }
        }

        if (empty($new_values)) {
            if (!$args['multiple']) {
                $new_values = '';
            }
        } else {
            if (!$args['multiple']) {
                $new_values = $new_values[0];
            } else {
                // save value as strings, so we can clearly search for them in SQL LIKE statements
                $new_values = array_map('strval', $new_values);
            }
        }

        update_post_meta($post_id, $field['name'], $new_values);
        update_post_meta($post_id, "_{$field['name']}", $field['key']);

        return true;
    }

    /**
     * Attach the post ID to the given parent post ID.
     *
     * @param  integer $related_id The post ID to attach.
     * @param  integer $post_id    The post ID to associate the relationship to.
     * @param  array   $field      The field to update.
     * @return bool    TRUE on successful update, FALSE on failure.
     */
    private function attach_relationship($related_id, $post_id, $field)
    {
        /**
         * Filters the arguments used to attach a post.
         *
         * @event EDQ#filter:acf/fields/{$field_type}/attach
         * @event EDQ#filter:acf/fields/{$field_type}/attach/name={$field_name}
         * @event EDQ#filter:acf/fields/{$field_type}/attach/key={$field_key}
         *
         * @param array   $args {
         *     Array of relationship arguments.
         *
         *     @type bool      $replace  Whether to replace an existing value in a related field.
         *     @type bool      $multiple Whether the field allows multiple posts (see Post Object field type).
         *     @type integer   $max      Maximum number of posts allowed (see Post Relationship field type).
         * }
         * @param integer $post_id    The post ID to associate the relationship to.
         * @param array   $field      The primary field structure.
         */
        $args = apply_filters("acf/fields/{$field['type']}/attach", [], $post_id, $field);
        $args = apply_filters("acf/fields/{$field['type']}/attach/name={$field['name']}", $args, $post_id, $field);
        $args = apply_filters("acf/fields/{$field['type']}/attach/key={$field['key']}", $args, $post_id, $field);

        // defaults
        $args = acf_parse_args($args, [
            'replace'  => false,
            'multiple' => true,
            'max'      => 0,
        ]);

        $value = maybe_unserialize(get_post_meta($post_id, $field['name'], true));

        // Ensure array of integers
        $value = acf_get_array($value);
        $value = array_map('intval', $value);

        if (in_array($related_id, $value)) {
            return false;
        }

        $update = false;

        if ($args['max'] === 0 || $args['max'] > count($value)) {
            $update  = true;
            $value[] = $related_id;
        } elseif ($args['max'] > 0) {
            if ($args['replace']) {
                if (is_string($args['replace']) && in_array($args['replace'], ['first', 'last'], true)) {
                    $type = $args['replace'];
                } else {
                    $type = 'first';
                }

                if ($type === 'first') {
                    $detach = array_shift($value);
                } else {
                    $detach = array_pop($value);
                }

                // Disocciate the post ID that was just replaced
                $this->detach_relationship($detach, $post_id, $field);

                $update  = true;
                $value[] = $related_id;
            }
        }

        if (!$update) {
            return false;
        }

        if (!$args['multiple']) {
            $value = $value[0];
        } else {
            $value = array_map('strval', $value);
        }

        update_post_meta($post_id, $field['name'], $value);
        update_post_meta($post_id, "_{$field['name']}", $field['key']);

        return true;
    }
}
