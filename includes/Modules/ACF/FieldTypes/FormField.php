<?php

namespace App\Cms\Modules\ACF\FieldTypes;

use acf_field;
use GFAPI;
use WPCF7_ContactForm;

use function App\Cms\Support\path;
use function App\Cms\Support\uri;
use function Ninja_Forms;

/**
 * Form Field
 *
 * The form field creates a select field where the choices are forms from
 * Gravity Forms, Ninja Forms, and Contact Form 7.
 *
 * This field is useful for embedding a form into a page / post.
 *
 * Note: Adapted from:
 * - dannyvanholten/acf-gravityforms-add-on.
 * - dannyvanholten/acf-ninjaforms-add-on
 * - taylormsj/acf-cf7
 * - v-technologies/acf-cf7
 * - mindfullsilence/acf-cf7
 */
class FormField extends acf_field
{
    const WPCF7_SHORTCODE_TEMPLATE = '[contact-form-7 id="%form_id%"]';
    const RGGF_SHORTCODE_TEMPLATE  = '[gravityform id="%form_id%"]';
    const WPNF_SHORTCODE_TEMPLATE  = '[ninja_form id="%form_id%"]';

    /**
     * Make sure we can easily access our notices.
     *
     * @var Notices
     */
    public $notices;

    /**
     * Available sources for forms.
     *
     * @var array
     */
    public $sources = [];

    /**
     * Available forms.
     *
     * @var array
     */
    public $forms = [];

    /**
     * Setup the field type data.
     *
     * @return void
     */
    public function initialize()
    {
        $this->name     = 'form';
        $this->label    = _x('Form Selector', 'noun', 'acf');
        $this->category = 'relational';
        $this->defaults = [
            'form_type'     => [],
            'return_format' => 'object',
            'default_value' => '',
            'placeholder'   => '',
            'multiple'      => 0,
            'allow_null'    => 0,
            'ui'            => 0,
            'ajax'          => 0,
        ];

        // Get our notices up and running
        # $this->notices = new Notices();

        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            $this->sources['contactform7'] = __( 'Contact Form 7', 'contact-form-7' );
        }

        if ( class_exists( 'GFAPI' ) ) {
            $this->sources['gravityforms'] = __( 'Gravity Forms', 'gravityforms' );
        }

        if ( function_exists( 'Ninja_Forms' ) ) {
            $this->sources['ninjaforms'] = __( 'Ninja Forms', 'ninjaforms' );
        }
    }

    /**
     * Enqueue scripts for the field.
     *
     * @listens action:acf/input/admin_enqueue_scripts added in includes/fields/class-acf-field.php
     *
     * @return  void
     */
    public function input_admin_enqueue_scripts()
    {
        $handle = 'acf-field-form';

        if ( ! wp_script_is( $handle, 'registered' ) ) {
            wp_register_script(
                $handle,
                uri( 'resources/scripts/acf-field-form.js' ),
                [ 'acf-input' ],
                '',
                true
            );
        }

        wp_enqueue_script( $handle );
    }

    /**
     * Process the AJAX request.
     *
     * @return void
     */
    public function ajax_query()
    {
        if ( ! acf_verify_ajax() ) {
            die();
        }

        $response = $this->get_ajax_query( $_POST );

        acf_send_ajax_results($response);
    }

    /**
     * Retrieve an array of data formatted for use in a select2 AJAX response.
     *
     * @todo   Implement AJAX response data.
     * @param  array $options Options to filter data to fetch.
     * @return array
     */
    public function get_ajax_query( $options = [] )
    {
        $options = acf_parse_args( $options, [
            'form_id'       => 0,
            's'             => '',
            'field_key'     => '',
            'paged'         => 1
        ] );

        // Load field
        $field = acf_get_field( $options['field_key'] );
        if ( ! $field ) {
            return false;
        }

        $results   = [];
        $args      = [];
        $s         = false;
        $is_search = false;

        $args['forms_per_page'] = 20;
        $args['paged'] = $options['paged'];

        if ( $options['s'] !== '' ) {
            $s = wp_unslash( strval($options['s']) );

            $args['s'] = $s;
            $is_search = true;
        }

        if ( empty($field['form_type']) ) {
            $args['form_type'] = $this->get_form_types();
        } else {
            $args['form_type'] = acf_get_array( $field['form_type'] );
        }

        $args = apply_filters( 'acf/fields/form/query', $args, $field, $options['form_id']);
        $args = apply_filters( 'acf/fields/form/query/name=' . $field['name'], $args, $field, $options['form_id'] );
        $args = apply_filters( 'acf/fields/form/query/key=' . $field['key'], $args, $field, $options['form_id'] );

        $groups = $this->get_grouped_forms( $args );

        // Bail early if no forms
        if ( empty($groups) ) {
            return false;
        }

        foreach( array_keys($groups) as $group_title ) {
            $forms = acf_extract_var( $groups, $group_title );

            $data = [
                'text'      => $group_title,
                'children'  => []
            ];

            // convert form objects to form names
            foreach ( array_keys($forms) as $form_id ) {
                $forms[ $post_id ] = $this->get_form_title( $forms[ $form_id ], $field, $options['form_id'], $is_search );
            }

            // Order forms by search
            if ( $is_search && empty($args['orderby']) ) {
                $forms = acf_order_by_search( $forms, $args['s'] );
            }

            foreach ( array_keys($forms) as $post_id ) {
                $data['children'][] = $this->get_form_result( $post_id, $forms[ $post_id ]);
            }

            $results[] = $data;

        }


        // optgroup or single
        if ( count($args['form_type']) === 1 ) {
            $results = $results[0]['children'];
        }

        $response = [
            'results'  => $results,
            'limit'    => $args['forms_per_page']
        ];

        return $response;
    }

    /**
     * Create the HTML interface for the form field.
     *
     * @param  array $field Field data.
     * @return void
     */
    public function render_field( $field )
    {
        // Change Field into a select
        $field['type']    = 'select';
        $field['choices'] = [];

        // Force value to array
        $field['value'] = acf_get_array( $field['value'] );

        // Load forms
        # $forms = $this->get_forms( $field['value'], $field );
        $forms = $this->load_forms( $field['form_type'] );

        if ( $forms ) {
            foreach ( array_keys($forms) as $i ) {
                $form = acf_extract_var( $forms, $i );

                $form_id    = $this->get_form_id( $form, $field );
                $form_title = $this->get_form_title( $form, $field );

                // Append to choices
                $field['choices'][ $form_id ] = $form_title;
            }
        }

        acf_render_field( $field );
    }

    /**
     * Create extra settings for the form field.
     *
     * @param  $field The field array holding all the field options.
     * @return void
     */
    public function render_field_settings( $field )
    {
        // form_type
        acf_render_field_setting( $field, [
            'label'         => __( 'Form Type','acf' ),
            'instructions'  => '',
            'type'          => 'select',
            'name'          => 'form_type',
            'choices'       => $this->get_form_types(),
        ] );

        // default_value
        acf_render_field_setting( $field, [
            'label'         => __( 'Default Value', 'acf' ),
            'instructions'  => __( 'Appears when creating a new post', 'acf' ),
            'type'          => 'form',
            'name'          => 'default_value',
        ]);

        // allow_null
        acf_render_field_setting( $field, [
            'label'         => __( 'Allow Null?','acf' ),
            'instructions'  => '',
            'name'          => 'allow_null',
            'type'          => 'true_false',
            'ui'            => 1,
        ] );

        // multiple
        acf_render_field_setting( $field, [
            'label'         => __( 'Select multiple values?','acf' ),
            'instructions'  => '',
            'name'          => 'multiple',
            'type'          => 'true_false',
            'ui'            => 1,
        ] );

        // ui
        acf_render_field_setting( $field, [
            'label'         => __( 'Stylised UI', 'acf' ),
            'instructions'  => '',
            'name'          => 'ui',
            'type'          => 'true_false',
            'ui'            => 1,
        ]);

        // ajax
        acf_render_field_setting( $field, [
            'label'         => __( 'Use AJAX to lazy load choices?', 'acf' ),
            'instructions'  => '',
            'name'          => 'ajax',
            'type'          => 'true_false',
            'ui'            => 1,
            'conditions'    => [
                'field'     => 'ui',
                'operator'  => '==',
                'value'     => 1
            ]
        ]);

        // return_format
        acf_render_field_setting( $field, [
            'label'         => __( 'Return Format','acf' ),
            'instructions'  => '',
            'type'          => 'radio',
            'name'          => 'return_format',
            'layout'        => 'horizontal',
            'choices'       => [
                'object'    => __( 'Form Object', 'acf' ),
                'shortcode' => __( 'Form Shortcode', 'acf' ),
                'id'        => __( 'Form ID', 'acf' ),
            ],
        ] );
    }

    /**
     * This filter is appied to the $value after it is loaded
     * from the DB and before it is returned to the template.
     *
     * @param  mixed $value   The value which was loaded from the database.
     * @param  mixed $post_id The $post_id from which the value was loaded.
     * @param  array $field   The field array holding all the field options.
     * @return mixed The modified value.
     */
    public function format_value( $value, $post_id, $field )
    {
        // Bail early if no value
        if ( empty($value) ) {
            return false;
        }

        // Force value to array
        $value = acf_get_array($value);

        if ( $field['return_format'] === 'id' ) {
            $value = acf_get_numeric( $value );
        } elseif ( $field['return_format'] === 'object' ) {
            $value = $this->get_forms( $value, $field );
        } elseif ( $field['return_format'] === 'shortcode' ) {
            $value = $this->get_shortcodes( $value, $field );
        }

        // Convert back from array if neccessary
        if ( ! $field['multiple'] && acf_is_array( $value ) ) {
            $value = current( $value );
        }

        return $value;
    }

    /**
     * This filter is applied to the $value before it is updated in the DB.
     *
     * @param  mixed $value   The value which will be saved to the database.
     * @param  mixed $post_id The $post_id of value will be saved.
     * @param  array $field   The field array holding all the field options.
     * @return mixed The modified value.
     */
    public function update_value( $value, $post_id, $field )
    {
        if ( empty( $value ) ) {
            return $value;
        }

        // Strip empty array values
        if ( is_array($value ) ) {
            $value = array_values( array_filter( $value ) );
        }

        return $value;
    }

    /**
     * Retrieve an array of shortcodes for a given field value.
     *
     * @param  array $value The form(s) to load from the database.
     * @param  array $field The field array holding all the field options.
     * @return mixed The form shortcodes.
     */
    public function get_shortcodes( $value, $field )
    {
        // Bail early if no value
        if ( empty($value) ) {
            return false;
        }

        // Force value to array
        $value = acf_get_array( $value );

        switch ( $field['form_type'] ) {
            case 'contactform7':
                $format = static::WPCF7_SHORTCODE_TEMPLATE;
                break;

            case 'gravityforms':
                $format = static::RGGF_SHORTCODE_TEMPLATE;
                break;

            case 'ninjaforms':
                $format = static::WPNF_SHORTCODE_TEMPLATE;
                break;
        }

        $forms = [];
        foreach ($value as $form_id) {
            $shortcode = strtr( $format, [
                '%form_id%' => $form_id
            ] );

            /**
             * Filters the shortcode.
             *
             * @param  string  $shortcode The full shortcode string.
             * @param  integer $form_id   The form ID.
             * @param  array   $field     The field array holding all the field options.
             * @return string The filtered shortcode string.
             */
            $shortcode = apply_filters( 'acf/fields/form/' . $field['form_type'] . '/format_shortcode', $shortcode, $form_id, $field );
            $shortcode = apply_filters( 'acf/fields/form/' . $field['form_type'] . '/format_shortcode/form_id=' . $form_id, $shortcode, $form_id, $field );

            if ( ! empty( $shortcode ) && ! is_wp_error( $shortcode ) ) {
                $forms[] = $shortcode;
            }
        }

        return $forms;
    }

    /**
     * Retrieve an array of forms for a given field value.
     *
     * @param  array $value The form(s) to load from the database.
     * @param  array $field The field array holding all the field options.
     * @return mixed The form objects.
     */
    public function get_forms( $value, $field )
    {
        // Bail early if no value
        if ( empty($value) ) {
            return false;
        }

        switch ( $field['form_type'] ) {
            case 'contactform7':
                $func = 'get_wpcf7_form';
                break;

            case 'gravityforms':
                $func = 'get_rggf_form';
                break;

            case 'ninjaforms':
                $func = 'get_wpnf_form';
                break;
        }

        // Force value to array
        $value = acf_get_array( $value );

        $forms = [];
        foreach ( $value as $form_id ) {
            $form = $this->{$func}($form_id);
            if ( ! empty( $form ) && ! is_wp_error( $form ) ) {
                $forms[] = $form;
            }
        }

        return $forms;
    }

    /**
     * Retrieve the given form from Contact Form 7.
     *
     * @todo   Move to Adapter pattern.
     * @param  integer $value The form ID to load.
     * @return array|object|boolean The form object.
     */
    private function get_wpcf7_form( $form_id )
    {
        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            return WPCF7_ContactForm::get_instance( $form_id );
        }

        return false;
    }

    /**
     * Retrieve the given form from Gravity Forms.
     *
     * @todo   Move to Adapter pattern.
     * @param  integer $value The form ID to load.
     * @return array|object|boolean The form object.
     */
    private function get_rggf_form( $form_id )
    {
        if ( class_exists( 'GFAPI' ) ) {
            return GFAPI::get_form( $form_id );
        }

        return false;
    }

    /**
     * Retrieve the given form from Ninja Forms.
     *
     * @todo   Move to Adapter pattern.
     * @param  integer $value The form ID to load.
     * @return array|object|boolean The form object.
     */
    private function get_wpnf_form( $form_id )
    {
        if ( function_exists( 'Ninja_Forms' ) ) {
            return Ninja_Forms()->form( $form_id )->get();
        }

        return false;
    }

    /**
     * Retrieve an array of forms.
     *
     * @todo   Move to Adapter pattern.
     * @param  string|null $form_type Filter by form type.
     * @return mixed The form objects.
     */
    public function load_forms( $form_type = null )
    {
        $types = $this->get_form_types();

        $form_type = (array) $form_type;
        if ( ! empty( $form_type ) ) {
            $types = array_intersect_key( $types, array_flip( $form_type ) );
        }

        if ( isset( $types['contactform7'] ) ) {
            $types['contactform7'] = 'load_wpcf7_forms';
        }

        if ( isset( $types['gravityforms'] ) ) {
            $types['gravityforms'] = 'load_rggf_forms';
        }

        if ( isset( $types['ninjaforms'] ) ) {
            $types['ninjaforms'] = 'load_wpnf_forms';
        }

        $data = [];
        foreach ( $types as $type => $func ) {
            $forms = $this->{$func}();
            if ( ! empty( $forms ) && ! is_wp_error( $forms ) ) {
                $data[$type] = $forms;
            }
        }
        reset( $types );

        if ( count( $form_type ) === 1 ) {
            $key = key( $types );
            if ( isset( $data[$key] ) ) {
                return $data[$key];
            }
        }

        return $data;
    }

    /**
     * Retrieve all forms from Contact Form 7.
     *
     * @todo   Move to Adapter pattern.
     * @return mixed[] The form objects.
     */
    private function load_wpcf7_forms()
    {
        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            return WPCF7_ContactForm::find();
        }

        return false;
    }

    /**
     * Retrieve all forms from Gravity Forms.
     *
     * @todo   Move to Adapter pattern.
     * @return mixed[] The form objects.
     */
    private function load_rggf_forms()
    {
        if ( class_exists( 'GFAPI' ) ) {
            return GFAPI::get_forms();
        }

        return false;
    }

    /**
     * Retrieve all forms from Ninja Forms.
     *
     * @todo   Move to Adapter pattern.
     * @return mixed[] The form objects.
     */
    private function load_wpnf_forms()
    {
        if ( function_exists( 'Ninja_Forms' ) ) {
            return Ninja_Forms()->form()->get_forms();
        }

        return false;
    }

    /**
     * Retrieve the ID of the form.
     *
     * @todo   Move to Adapter pattern.
     * @param  array|objecy $form      The form to parse.
     * @param  array        $field     The field array holding all the field options.
     * @return string|integer The form ID.
     */
    public function get_form_id( $form, $field )
    {
        $id = 0;

        switch ( $field['form_type'] ) {
            case 'contactform7':
                /** @var WPCF7_ContactForm $form */
                $id = $form->id();
                break;

            case 'gravityforms':
                /** @var array $form */
                $id = $form['id'];
                break;

            case 'ninjaforms':
                /** @var NF_Database_Models_Form $form */
                $id = $form->get_id();
                break;
        }

        return $id;
    }

    /**
     * Retrieve the title of the form.
     *
     * @todo   Move to Adapter pattern.
     * @param  array|objecy $form      The form to parse.
     * @param  array        $field     The field array holding all the field options.
     * @param  integer      $form_id   The form ID.
     * @param  boolean      $is_search Whether the context is a search query.
     * @return string The form title.
     */
    public function get_form_title( $form, $field, $form_id = 0, $is_search = false )
    {
        $title = '';

        switch ( $field['form_type'] ) {
            case 'contactform7':
                /** @var WPCF7_ContactForm $form */
                $title = $form->title();
                break;

            case 'gravityforms':
                /** @var array $form */
                $title = $form['title'];
                break;

            case 'ninjaforms':
                /** @var NF_Database_Models_Form $form */
                $title = $form->get_setting('title');
                break;
        }

        $title = apply_filters( 'acf/fields/form/result', $title, $form, $field, $form_id);
        $title = apply_filters( 'acf/fields/form/result/name=' . $field['_name'], $title, $form, $field, $form_id);
        $title = apply_filters( 'acf/fields/form/result/key=' . $field['key'], $title, $form, $field, $form_id);

        return $title;
    }

    /**
     * Retrieve an array of available form types (activated plugins).
     *
     * @return array
     */
    public function get_form_types()
    {
        return $this->sources;
    }

    /**
     * Check if we actually have forms that we can use for our field
     *
     * @todo
     * @return bool
     */
    public function hasValidForms()
    {
        // Stop if Gravityforms is not active
        if ( ! class_exists( 'GFAPI' ) ) {
            $this->notices->isGravityformsActive( true, true );

            return false;
        }

        // Check if there are forms and set our choices
        if ( ! $this->forms ) {
            $this->notices->hasActiveGravityForms( true, true );

            return false;
        }

        return true;
    }
}
