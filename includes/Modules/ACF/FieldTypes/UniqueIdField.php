<?php

namespace Locomotive\Cms\Modules\ACF\FieldTypes;

use acf_field;

/**
 * Unique ID Field
 *
 * The form field generates a unique ID value.
 *
 * While this library was originally developer for use in repeaters where
 * each field in a repeater block needs to be given a persistent unique ID,
 * it can be used anywhere an automatically-generated unique ID is required.
 *
 * Note: Adapted from:
 * - philipnewcomer/ACF-Unique-ID-Field
 */
class UniqueIdField extends acf_field
{
    /**
     * Setup the field type data.
     *
     * @return void
     */
    public function initialize()
    {
        $this->name     = 'unique_id';
        $this->label    = __('Unique ID', 'acf');
        $this->category = 'basic';
        $this->defaults = [];
    }

    /**
     * Create the HTML interface for the form field.
     *
     * @param  array $field Field data.
     * @return void
     */
    public function render_field($field)
    {
        // $hidden_input = acf_get_sub_array( $field, [ 'id', 'name', 'value' ] );
        // acf_hidden_input( $hidden_input );

        $text_input = acf_get_sub_array($field, ['id', 'class', 'name', 'value']);
        $text_input['readonly'] = true;
        acf_text_input($text_input);
    }

    /**
     * This filter is applied to the $value before it is updated in the DB.
     *
     * @param  mixed $value   The value which will be saved to the database.
     * @param  mixed $post_id The $post_id of value will be saved.
     * @param  array $field   The field array holding all the field options.
     * @return mixed The modified value.
     */
    public function update_value($value, $post_id, $field)
    {
        if (!empty($value)) {
            return $value;
        }

        return uniqid();
    }
}
