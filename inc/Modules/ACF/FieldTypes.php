<?php

namespace App\Cms\Modules\ACF;

use acf_field;
use App\Cms\Contracts\Bootable;
use WP_Post;

use function App\Cms\Support\with_filters;
use function App\Cms\Support\without_filters;

/**
 * ACF Module: Field Types
 *
 * This module registers a collection of custom field types.
 */
class FieldTypes implements Bootable
{
    public const DEFAULT_FC_ROW_POSITION  = 10;
    public const DEFAULT_FC_ROW_INCREMENT = 0.01;

    /**
     * Array of custom field types to register.
     *
     * @var array
     */
    public $field_types = [];

    /**
     * Cache of oEmbed responses.
     *
     * @var array
     */
    protected $oembed_response_cache = [];

    /**
     * Sets up the module object.
     *
     * @return void
     */
    public function __construct()
    {
        $this->field_types = [
            // FieldTypes\FormField::class,
            // FieldTypes\PostRelationshipField::class,
            // FieldTypes\TermObjectField::class,
            // FieldTypes\UniqueIdField::class,
            // FieldTypes\VideoField::class,
        ];
    }

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('acf/include_field_types', [$this, 'include_field_types']);

        add_action('acf/init', [$this, 'disable_format_value_for_oembed'], 1);

        add_filter('acf/load_value/type=flexible_content',   [$this, 'load_value_for_flexible_content'], 10, 3);
        add_filter('acf/load_value/type=image',              [$this, 'load_value_for_wp_post_thumbnail'], 10, 3);

        add_filter('acf/format_value/type=oembed',           [$this, 'format_value_for_oembed'], 10, 3);
        add_filter('acf/validate_value/type=text',           [$this, 'validate_value_for_constraint_pattern'], 10, 3);
        add_filter('acf/pre_update_value/type=image',        [$this, 'pre_update_value_for_wp_post_thumbnail'], 10, 4);

        add_filter('acf/update_value/type=flexible_content', [$this, 'update_value_for_flexible_content'], 10, 3);
        add_filter('acf/update_value/type=oembed',           [$this, 'update_value_for_oembed'], 10, 3);
        add_filter('acf/update_value/type=text',             [$this, 'update_value_for_wp_post_excerpt'], 10, 3);
        add_filter('acf/update_value/type=textarea',         [$this, 'update_value_for_wp_post_excerpt'], 10, 3);
        add_filter('acf/update_value/type=wysiwyg',          [$this, 'update_value_for_wp_post_excerpt'], 10, 3);

        if (is_admin()) {
            add_action('acf/add_meta_boxes', [$this, 'filter_meta_boxes'], 10, 3);

            add_filter('acf/get_field_group_style',  [$this, 'filter_field_group_style'], 10, 1);

            add_filter('acf/fields/wysiwyg/toolbars',  [$this, 'register_wysiwyg_toolbars'], 10, 1);

            add_action('acf/render_field/type=select', [$this, 'render_field_for_select']);

            add_action('acf/render_field_settings/type=flexible_content', [$this, 'render_field_settings_for_sorting_layouts']);
            add_action('acf/render_field_settings/type=image',            [$this, 'render_field_settings_for_wp_post_thumbnail']);
            add_action('acf/render_field_settings/type=select',           [$this, 'render_field_settings_for_custom_values']);
            add_action('acf/render_field_settings/type=text',             [$this, 'render_field_settings_for_constraint_pattern']);
            add_action('acf/render_field_settings/type=text',             [$this, 'render_field_settings_for_wp_post_excerpt']);
            add_action('acf/render_field_settings/type=textarea',         [$this, 'render_field_settings_for_wp_post_excerpt']);
            add_action('acf/render_field_settings/type=wysiwyg',          [$this, 'render_field_settings_for_wp_post_excerpt']);
        }
    }

    /**
     * Registers custom field types.
     *
     * @param  array $field_types One or more custom field types.
     * @return void
     */
    public function register_field_types(array $field_types): void
    {
        foreach ($field_types as $field_type) {
            acf_register_field_type($field_type);
        }
    }

    /**
     * Registers predefined custom field types.
     *
     * @listens ACF#action:acf/include_field_types
     *
     * @return void
     */
    public function include_field_types(): void
    {
        if (!empty($this->field_types)) {
            $this->register_field_types($this->field_types);
        }
    }

    /**
     * Fires after metaboxes have been added.
     *
     * This function performs two routines:
     * - Remove the post thumbnail meta box if the featured image field is present.
     * - ~~Merge "hide_on_screen" values from all field groups instead of just the first field group.
     *   {@see \ACF_Form_Post::add_meta_boxes()}~~
     *
     * Note: I've disable the merging of "hide_on_screen" because {@see \ACF_Ajax_Check_Screen}
     * can not be reliably handled.
     *
     * @listens action:acf/add_meta_boxes
     *
     * @param  string  $post_type    The post type.
     * @param  WP_Post $post         The post being edited.
     * @param  array   $field_groups The field groups added.
     * @return void
     */
    public function filter_meta_boxes(string $post_type, WP_Post $post, array $field_groups): void
    {
        $thumbnail_support = (current_theme_supports('post-thumbnails', $post_type) && post_type_supports($post_type, 'thumbnail'));
        // $hide_on_screen    = [];

        foreach ($field_groups as $field_group) {
            /*
            // Merge items to hide from the edit screen
            if ( ! empty( $field_group['hide_on_screen'] ) && is_array( $field_group['hide_on_screen'] ) ) {
                array_push( $hide_on_screen, ...$field_group['hide_on_screen'] );
            }
            */

            // Lookup post thumbnail proxy
            if ($thumbnail_support) {
                $fields = acf_get_fields($field_group);
                foreach ($fields as $field) {
                    if ('image' === $field['type'] && !empty($field['save_post_thumbnail'])) {
                        remove_meta_box('postimagediv', $post_type, 'side');
                        return;
                    }
                }
            }
        }

        /*
        $form_post = acf_get_instance( ACF_Form_Post::class );

        if ( empty( $hide_on_screen ) && ! empty( $form_post->style ) ) {
            $form_post->style = null;
        } else {
            $hide_on_screen = array_values( array_unique( $hide_on_screen ) );
            $field_group    = acf_validate_field_group( [
                'hide_on_screen' => $hide_on_screen,
            ] );

            $form_post->style = acf_get_field_group_style( $field_group );
        }
        */
    }

    /**
     * Sanitizes the CSS to ensure rules have a selector.
     *
     * @listens filter:acf/get_field_group_style
     *     Filters the generated CSS styles for field groups.
     *
     * @param  ?string $style The CSS styles.
     * @return ?string
     */
    public function filter_field_group_style(?string $style): ?string
    {
        if (' {display: none;}' === $style) {
            return null;
        }

        return $style;
    }



    // Field Type: Flexible Content
    // =========================================================================

    /**
     * Modify the value of a Flexible Content field after it is loaded from the database.
     *
     * @listens filter:acf/load_value/type=flexible_content
     *
     * @param  mixed      $value   The value of the field as found in the database.
     * @param  int|string $post_id The post ID which the value was loaded from.
     * @param  array      $field   The field structure.
     * @return mixed The mutated $value.
     */
    public function load_value_for_flexible_content($value, $post_id, array $field)
    {
        // Value will only be NULL on a new post
        if ($value === null) {
            $value = $this->add_initial_layouts_for_flexible_content($value, $field);
        }

        return $value;
    }

    /**
     * Inject the initial layouts (e.g., required) and sort layout orders.
     *
     * @param  array $value The value of the field as found in the database.
     * @param  array $field The field structure.
     * @return mixed The mutated $value.
     */
    protected function add_initial_layouts_for_flexible_content($value, array $field)
    {
        $value = acf_get_array($value);

        $layouts = [];
        foreach ($field['layouts'] as $k => $layout) {
            if (!empty($layout['initial'])) {
                $value[] = $layout['name'];
            }
        }

        return $value;
    }

    /**
     * Modify the value of a Flexible Content field before it is saved to the database.
     *
     * @listens filter:acf/update_value/type=flexible_content
     *
     * @param  array      $value   The value of the field as found in the database.
     * @param  int|string $post_id The post ID which the value was loaded from.
     * @param  array      $field   The field structure.
     * @return mixed The mutated $value.
     */
    public function update_value_for_flexible_content($value, $post_id, array $field)
    {
        if (!empty($value)) {
            $value = $this->sort_layouts_for_flexible_content($value, $field);
        }

        return $value;
    }

    /**
     * Sort layouts of Flexible Content field.
     *
     * @param  mixed $value The value of the field as found in the $_POST array.
     * @param  array $field The field structure.
     * @return array
     */
    protected function sort_layouts_for_flexible_content($value, array $field)
    {
        if (empty($field['sort'])) {
            return $value;
        }

        // Sort layouts into names
        $layouts = [];
        foreach ($field['layouts'] as $k => $layout) {
            $layouts[$layout['name']] = $layout;
        }

        $value = acf_get_array($value);

        $position = static::DEFAULT_FC_ROW_POSITION;

        foreach ($value as $i => $row) {
            // Bail early if row is malformed
            if (empty($row['acf_fc_layout'])) {
                continue;
            }

            // Get layout name
            $l = $row['acf_fc_layout'];

            // Bail early if layout doesn't exist
            if (empty($layouts[$l])) {
                continue;
            }

            // Get layout
            $layout = $layouts[$l];

            // Default layout priority
            if (!isset($layout['position'])) {
                $layout['position'] = $position += static::DEFAULT_FC_ROW_INCREMENT;
            }

            // Set position on row for sorting
            $value[$i]['xyz_fc_position'] = $layout['position'];
        }

        uasort($value, function ($a, $b) {
            $pA = $a['xyz_fc_position'];
            $pB = $b['xyz_fc_position'];

            if ($pA == $pB) {
                return 0;
            }

            if ($pA < $pB) {
                return -1;
            }

            return 1;
        });

        return $value;
    }

    /**
     * Add a custom "sort" setting into Flexible Content field settings.
     *
     * @listens action:acf/render_field_settings/type=flexible_content
     *
     * @param  acf_field|array $field ACF Field.
     * @return void
     */
    public function render_field_settings_for_sorting_layouts(array $field): void
    {
        // sort_layouts
        acf_render_field_setting($field, [
            'label'         => __('Sort Layouts', 'acf'),
            'instructions'  => __('Automatically sort layouts by their internal layout order', 'acf'),
            'name'          => 'sort',
            'type'          => 'true_false',
            'ui'            => 1,
        ]);
    }



    // Field Type: Image
    // =========================================================================

    /**
     * Load the post thumbnail.
     *
     * @listens filter:acf/load_value/type=image
     *
     * @param  mixed      $value   The value of the field as found in the database.
     * @param  int|string $post_id The post ID which the value was loaded from.
     * @param  array      $field   The field structure.
     * @return mixed The mutated $value.
     */
    public function load_value_for_wp_post_thumbnail($value, $post_id, array $field)
    {
        if (!empty($field['save_post_thumbnail'])) {
            return get_post_thumbnail_id($post_id);
        }

        return $value;
    }

    /**
     * If the field is a "post_thumbnail", set the selected image as the post thumbnail
     * then short-circuit the update_value logic.
     *
     * @listens filter:acf/pre_update_value
     *
     * @param  ?bool      $check   The value to return instead of updating. Default NULL.
     * @param  mixed      $value   The value of the field as found in the $_POST array.
     * @param  int|string $post_id The post ID to save against.
     * @param  array      $field   The field structure.
     * @return ?bool
     */
    public function pre_update_value_for_wp_post_thumbnail($check, $value, $post_id, array $field)
    {
        if ($field['type'] === 'image' && !empty($field['save_post_thumbnail'])) {
            update_post_meta($post_id, '_thumbnail_id', $value);
            return true;
        }

        return $check;
    }

    /**
     * Sets the selected image as the post thumbnail.
     *
     * @listens filter:acf/update_value/type=image
     *
     * @param  mixed      $value   The value of the field as found in the $_POST array.
     * @param  int|string $post_id The post ID to save against.
     * @param  array      $field   The field structure.
     * @return mixed
     */
    public function update_value_for_wp_post_thumbnail($value, $post_id, array $field)
    {
        if (!empty($field['save_post_thumbnail'])) {
            update_post_meta($post_id, '_thumbnail_id', $value);
        }

        return $value;
    }

    /**
     * Add a custom "post_thumbnail" setting into Image field settings.
     *
     * @listens action:acf/render_field_settings/type=image
     *
     * @param  acf_field|array $field ACF Field.
     * @return void
     */
    public function render_field_settings_for_wp_post_thumbnail(array $field): void
    {
        // save_post_thumbnail
        acf_render_field_setting($field, [
            'label'         => __('Save as Featured Image', 'acf'),
            'instructions'  => __('Associate the selected image as the post thumbnail', 'acf'),
            'name'          => 'save_post_thumbnail',
            'type'          => 'true_false',
            'ui'            => 1,
        ]);
    }



    // Field Type: oEmbed
    // =========================================================================

    /**
     * Disable {@see \acf_field_oembed::format_value()} to replace
     * ACF/WP formatting with custom formatting of oEmbed results.
     *
     * @listens action:acf/init
     *
     * @return void
     */
    public function disable_format_value_for_oembed(): void
    {
        $field_type = acf_get_field_type('oembed');

        remove_filter('acf/format_value/type=oembed', [$field_type, 'format_value']);
    }

    /**
     * Format the $value after it is loaded from the database.
     *
     * @listens filter:acf/format_value/type=oembed
     *
     * @param  mixed   $value   The value which was loaded from the database.
     * @param  int|string $post_id The post ID from which the value was loaded.
     * @param  array   $field   The field structure.
     * @return string|null
     */
    public function format_value_for_oembed($value, $post_id, $field)
    {
        if (!empty($value)) {
            $value = $this->oembed_get_html($value, $post_id, $field);
        }

        return $value;
    }

    /**
     * Ensures a detailed oEmbed object is saved.
     *
     * @listens filter:acf/update_value/type=oembed
     *     Filter the $value before it is saved to the database.
     *
     * @param  mixed   $value   The value of the field as found in the $_POST array.
     * @param  int|string $post_id The post ID to save against.
     * @param  array   $field   The field structure.
     * @return string|null
     */
    public function update_value_for_oembed($value, $post_id, $field)
    {
        if (!empty($value)) {
            $this->oembed_get_html($value, $post_id, $field);
        }

        return $value;
    }

    /**
     * Attempts to fetch the embed HTML for a provided URL using oEmbed.
     *
     * Checks for a cached result (stored as custom post or in the post meta).
     *
     * @global \WP_Embed $wp_embed
     *
     * @see \WP_Embed::shortcode()
     *
     * @param  mixed      $value   The URL to cache.
     * @param  int|string $post_id The post ID to save against.
     * @param  array      $field   The field structure.
     * @return mixed The embed HTML on success, otherwise the original URL.
     */
    public function oembed_get_html($value, $post_id, array $field)
    {
        if (empty($value)) {
            return $value;
        }

        $attr = [
            'width'  => $field['width'],
            'height' => $field['height'],
        ];

        $func = function () use ($attr, $value) {
            $func = function () use ($attr, $value) {
                global $wp_embed;

                return $wp_embed->shortcode($attr, $value);
            };

            return with_filters($func, [
                ['oembed_dataparse', [$this, 'oembed_dataparse'], 0, 3],
                ['oembed_result',    [$this, 'oembed_result'], 0, 3],
            ]);
        };

        $html = without_filters($func, 'embed_oembed_html');

        if ($html) {
            return $html;
        }

        return $value;
    }

    /**
     * Prepares to cache the oEmbed response.
     *
     * @listens WP#filter:oembed_dataparse
     *
     * @param  string $html The returned oEmbed HTML.
     * @param  object $data A data object result from an oEmbed provider.
     * @param  string $url  The URL of the content to be embedded.
     * @return string
     */
    public function oembed_dataparse($html, $data, $url)
    {
        if (is_object($data)) {
            $this->oembed_response_cache[$url] = $data;
        }

        return $html;
    }

    /**
     * Caches the oEmbed response.
     *
     * @listens WP#filter:oembed_result
     *
     * @param  string $html The returned oEmbed HTML.
     * @param  string $url  URL of the content to be embedded.
     * @param  array  $args Optional arguments, usually passed from a shortcode.
     * @return string
     */
    public function oembed_result($html, $url, $args)
    {
        if (isset($this->oembed_response_cache[$url])) {
            $data = $this->oembed_response_cache[$url];
            $post = get_post();

            if (!empty($post->ID)) {
                unset($args['discover']);

                $key_suffix    = md5($url . serialize($args));
                $cachekey_data = '_oembed_data_' . $key_suffix;

                $data = json_decode(json_encode($data), true);
                update_post_meta($post->ID, $cachekey_data, $data);
            }
        }

        return $html;
    }



    // Field Type: Select
    // =========================================================================

    /**
     * Inject custom "tags" setting into Select field.
     *
     * @listens action:acf/render_field/type=select
     *
     * @param  acf_field|array $field ACF Field.
     * @return void
     */
    public function render_field_for_select($field): void
    {
        if (empty($field['tags'])) {
            return;
        }

        $atts = [
            'id'                    => $field['id'] . '-select2',
            'data-tags'             => $field['tags'],
            'data-token-separators' => '[","]',
        ];
        $html = '<script ' . acf_esc_atts($atts) . '></script>' . "\n";

        echo $html;
    }

    /**
     * Add a custom "tags" setting into Select field settings.
     *
     * @listens action:acf/render_field_settings/type=select
     *
     * @param  acf_field|array $field ACF Field.
     * @return void
     */
    public function render_field_settings_for_custom_values($field): void
    {
        // multiple
        acf_render_field_setting($field, [
            'label'         => __('Create Values', 'acf'),
            'instructions'  => __('Allow new values to be created whilst editing', 'acf'),
            'name'          => 'tags',
            'type'          => 'true_false',
            'ui'            => 1,
        ]);
    }



    // Field Type: Text
    // =========================================================================

    /**
     * Sets the value as the post excerpt.
     *
     * @listens filter:acf/update_value/type=text
     * @listens filter:acf/update_value/type=textarea
     * @listens filter:acf/update_value/type=wysiwyg
     *     Filter the $value before it is saved to the database.
     *
     * @param  mixed      $value   The value of the field as found in the $_POST array.
     * @param  int|string $post_id The post ID to save against.
     * @param  array      $field   The field structure.
     * @return mixed
     */
    public function update_value_for_wp_post_excerpt($value, $post_id, array $field)
    {
        if (!empty($value) && !empty($field['save_post_excerpt'])) {
            wp_update_post([
                'ID'           => $post_id,
                'post_excerpt' => wp_strip_all_tags($value),
            ]);
        }

        return $value;
    }

    /**
     * Validates the $value before it is saved to the database.
     *
     * @listens filter:acf/validate_value/type=text
     *
     * @param  bool   $valid Whether or not the $value is valid.
     * @param  mixed  $value The value to be saved.
     * @param  array  $field The field structure.
     * @param  string $input The input name of the field.
     * @return bool|string TRUE if the $value is valid, FALSE if invalid.
     *     Can also be returned as a custom error message.
     */
    public function validate_value_for_constraint_pattern($valid, $value, array $field)
    {
        if (!empty($field['pattern'])) {
            $pattern = '/^' . $field['pattern'] . '$/';

            if (!preg_match($pattern, $value)) {
                $valid = __('Value must match the requested format', 'acf');
            }
        }

        return $valid;
    }

    /**
     * Add a custom "pattern" setting into Text field settings.
     *
     * @listens action:acf/render_field_settings/type=text
     *
     * @param  acf_field|array $field ACF Field.
     * @return void
     */
    public function render_field_settings_for_constraint_pattern($field): void
    {
        // pattern
        acf_render_field_setting($field, [
            'label'         => __('Pattern', 'acf'),
            'instructions'  => __('A regular expression that the value is checked against. The pattern must match the entire value.', 'acf'),
            'name'          => 'pattern',
            'type'          => 'text',
        ]);
    }

    /**
     * Add a custom "post_excerpt" setting into Image field settings.
     *
     * @listens action:acf/render_field_settings/type=text
     * @listens action:acf/render_field_settings/type=textarea
     * @listens action:acf/render_field_settings/type=wysiwyg
     *
     * @param  acf_field|array $field ACF Field.
     * @return void
     */
    public function render_field_settings_for_wp_post_excerpt(array $field): void
    {
        // save_post_excerpt
        acf_render_field_setting($field, [
            'label'         => __('Save as Excerpt', 'acf'),
            'instructions'  => __('Associate the text as the post excerpt', 'acf'),
            'name'          => 'save_post_excerpt',
            'type'          => 'true_false',
            'ui'            => 1,
        ]);
    }



    // Field Type: WYSIWYG
    // =========================================================================

    /**
     * Register additional custom toolbars for TinyMCE.
     *
     * @listens action:acf/fields/wysiwyg/toolbars
     *
     * @param  array $field One or many TinyMCE toolbar configsets.
     * @return array
     */
    public function register_wysiwyg_toolbars(array $toolbars): array
    {
        $toolbars['Simple'] = [
            1 => ['bold', 'italic', 'link', 'unlink', 'undo', 'redo'],
        ];

        $toolbars['Notes'] = [
            1 => ['bold', 'italic', 'bullist', 'numlist', 'link', 'unlink', 'undo', 'redo'],
        ];

        return $toolbars;
    }
}
