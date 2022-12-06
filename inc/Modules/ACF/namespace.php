<?php

namespace App\Cms\Modules\ACF;

use WP_Taxonomy;
use WP_Term;

use function App\Cms\Modules\Polylang\pll_preferred_language;
use function App\Cms\Support\path;

/**
 * Bootstraps the module.
 *
 * @return void
 */
function bootstrap(): void
{
    create_initial_constants();

    register_initial_hooks();

    provide_extra_hooks();
}

/**
 * Defines initial module constants.
 *
 * @return void
 */
function create_initial_constants(): void
{
    define(__NAMESPACE__ . '\LOW_MENU_ORDER',  20);
    define(__NAMESPACE__ . '\HIGH_MENU_ORDER', 50);
}

/**
 * Registers actions and filters for the module.
 *
 * @return void
 */
function register_initial_hooks(): void
{
    add_filter('acf/settings/show_admin', '__return_false');

    add_action('acf/init', __NAMESPACE__ . '\\acf_init');

    if (is_admin()) {
        add_action('acf/render_field_group_settings', __NAMESPACE__ . '\\render_field_group_settings_meta_box_priority');
        add_filter('acf/input/meta_box_priority',     __NAMESPACE__ . '\\change_field_group_meta_box_priority', 10, 2);
    }

    /** Polylang Compatibility */

    add_filter('acf/get_taxonomies',        __NAMESPACE__ . '\\filter_pll_taxonomies');
    add_filter('acf/get_object_taxonomies', __NAMESPACE__ . '\\filter_pll_taxonomies');

    add_action('pll_language_defined',    __NAMESPACE__ . '\\pll_language_defined');
    add_action('pll_no_language_defined', __NAMESPACE__ . '\\pll_no_language_defined');

    if (is_admin()) {
        add_action('acf/options_page/submitbox_before_major_actions', __NAMESPACE__ . '\\display_post_languages_meta_box');
    }
}

/**
 * Extends existing ACF actions and filters.
 *
 * @return void
 */
function provide_extra_hooks(): void
{
    add_filter('acf/pre_render_fields', __NAMESPACE__ . '\\pre_render_fields', 9, 2);
}

/**
 * Fires after ACF is completely initialized.
 *
 * @listens ACF#action:acf/init
 *
 * @return void
 */
function acf_init(): void
{
    /** Register variation for custom filter ({@see pre_render_fields()}) */
    acf_add_filter_variations('acf/pre_render_field', ['type', 'name', 'key'], 0);

    /** Register variation for existing filter ({@see \acf_update_value()}) */
    acf_add_filter_variations('acf/pre_update_value', ['type', 'name', 'key'], 3);
}

/**
 * Fires after ACF has rendered its field group settings.
 *
 * @listens ACF#action:acf/render_field_group_settings
 *
 * @return void
 */
function render_field_group_settings_meta_box_priority(): void
{
    // prirotiy
    acf_render_field_wrap([
        'label'         => __('Priority', 'acf'),
        'instructions'  => __('Customize the position of the field group', 'acf'),
        'type'          => 'select',
        'name'          => 'priority',
        'prefix'        => 'acf_field_group',
        'value'         => $field_group['priority'] ?? null,
        'choices'       => [
            'high'    => __('High', 'acf'),
            'sorted'  => __('Sorted', 'acf'),
            'core'    => __('Core', 'acf'),
            'default' => __('Default (WP)', 'acf'),
            'low'     => __('Low', 'acf'),
        ],
        'allow_null'    => null,
        'placeholder'   => __('Default (ACF)', 'acf'),
    ]);
}

/**
 * Change the priority of the meta box field group.
 *
 * @listens filter:acf/input/meta_box_priority
 *
 * @param  string $priority    The priority within the context where the box
 *                             should show ('high', 'low'). Default 'high'.
 * @param  array  $field_group The field group structure.
 * @return string The mutated $priority.
 */
function change_field_group_meta_box_priority(string $priority, array $field_group): string
{
    return $field_group['priority'] ?? $priority;
}

/**
 * Filters the fields before render.
 *
 * @listens ACF#filter:acf/pre_render_fields
 *
 * @fires XYZ#filter:acf/pre_render_field
 * @fires XYZ#filter:acf/pre_render_field/type={$field_type}
 *
 * @param  array      $fields  ACF Field.
 * @param  int|string $post_id The post ID to render fields on.
 * @return array
 */
function pre_render_fields(array $fields, $post_id): array
{
    foreach ($fields as $i => $field) {
        $field = apply_filters('acf/pre_render_field', $field, $post_id);

        $fields[$i] = $field;
    }

    return $fields;
}

/**
 * Determines if the given params match a layout.
 *
 * @see \acf_is_field()
 *
 * @param  mixed $field A field array.
 * @param  mixed $id    An optional identifier to search for.
 * @return bool
 */
function acf_is_field_layout($field, $id = null): bool
{
    return is_array($field) && isset($field['key'], $field['name']);
}

/**
 * Determines if the given identifier is a layout key.
 *
 * @see \acf_is_field_key()
 *
 * @param  string $id The identifier.
 * @return bool
 */
function acf_is_field_layout_key($id): bool
{
    // Check if $id is a string starting with "layout_".
    if (is_string($id) && substr($id, 0, 7) === 'layout_') {
        return true;
    }

    /**
     * Filters whether the $id is a field group key.
     *
     * @event XYZ#filter:acf/is_field_layout_key
     *
     * @param bool   $bool The result.
     * @param string $id   The identifier.
     */
    return apply_filters('acf/is_field_layout_key', false, $id);
}

/**
 * Returns the "taxonomy:slug" string to a term for ACF.
 *
 * @param  WP_Taxonomy|WP_Term|string $taxonomy A taxonomy name, taxonomy object, or term object.
 * @param  WP_Term|string|null        $term     A term slug or term object.
 * @return ?string The encoded ACF term string.
 */
function acf_encode_taxonomy_term($taxonomy, $term = null): ?string
{
    if (null === $term) {
        if ($taxonomy instanceof WP_Term) {
            return acf_encode_term($taxonomy);
        }

        return null;
    }

    if ($taxonomy instanceof WP_Taxonomy) {
        $taxonomy = $taxonomy->name;
    }

    if ($term instanceof WP_Term) {
        $term = $term->slug;
    }

    if (is_string($taxonomy) && is_string($term)) {
        $term = (object) [
            'taxonomy' => $taxonomy,
            'slug'     => $term,
        ];

        return acf_encode_term($term);
    }

    return null;
}

/**
 * Generate the collection of layouts.
 *
 * @param  array $layouts One or many layouts.
 * @param  array $prefs   Customizations to merge with all layouts.
 * @return array
 */
function create_field_layouts(array $layouts, array $prefs = null): array
{
    $uid = uniqid();
    $arr = [];

    foreach ($layouts as $layout) {
        $layout['key'] = $key = sprintf($layout['key'], $uid);

        foreach ($layout['sub_fields'] as $i => $subfield) {
            $layout['sub_fields'][$i]['key'] = sprintf($subfield['key'], $uid);
        }

        if ($prefs !== null) {
            $layout = array_replace_recursive($layout, $prefs);
        }

        $arr[$key] = $layout;
    }

    return $arr;
}

/**
 * Generate a "Tab" field.
 *
 * @param  array $prefs Field customizations.
 * @return array
 */
function create_field_tab(array $prefs = null): array
{
    $field = [
        'key'  => uniqid('field_tab_'),
        'type' => 'tab',
    ];

    if ($prefs === null) {
        return $field;
    }

    return array_replace_recursive($field, $prefs);
}

/**
 * Generate a "Message" field.
 *
 * @param  array $prefs Field customizations.
 * @return array
 */
function create_field_message(array $prefs = null): array
{
    $field = [
        'key'  => uniqid('field_message_'),
        'type' => 'message',
    ];

    if ($prefs === null) {
        return $field;
    }

    return array_replace_recursive($field, $prefs);
}

/**
 * Generate a "Author" field.
 *
 * @param  string     $key   Field key.
 * @param  array|null $prefs Field customizations.
 * @return array
 */
function create_field_author(string $key, array $prefs = null): array
{
    $field = [
        'key'           => "field_{$key}_author_object",
        'name'          => 'author',
        'label'         => __('Choose an author', 'app/cms'),
        'placeholder'   => __('None'),
        'type'          => 'select',
        'required'      => 0,
        'allow_null'    => 1,
        'multiple'      => 0,
        'return_format' => 'value',
    ];

    if ($prefs === null) {
        return $field;
    }

    return array_replace_recursive($field, $prefs);
}

/**
 * Generate fields for a person (image, name, role, link).
 *
 * @param  string     $key   Field key.
 * @param  array|null $prefs Optional. Map of field customizations.
 *     The order of fields in map will be respected.
 *     Pass NULL to a specific field to disable it.
 * @return array
 */
function create_fields_person(string $key, array $prefs = null): array
{
    $fields = [
        'name' => [
            'key'                 => "field_{$key}_name",
            'name'                => 'name',
            'label'               => __('Name', 'app/cms'),
            'type'                => 'text',
            'required'            => 0,
        ],
        'role' => [
            'key'                 => "field_{$key}_role",
            'name'                => 'role',
            'label'               => __('Role', 'app/cms'),
            'type'                => 'text',
            'required'            => 0,
        ],
        'biography' => [
            'key'                 => "field_{$key}_biography",
            'name'                => 'biography',
            'label'               => __('Biography', 'app/cms'),
            'type'                => 'textarea',
            'required'            => 0,
            'rows'                => 3,
            'new_lines'           => '',
        ],
        'link' => [
            'key'                 => "field_{$key}_link",
            'name'                => 'link',
            'label'               => __('Link', 'app/cms'),
            'type'                => 'link',
            'return_format'       => 'array',
        ],
        'image' => [
            'key'                 => "field_{$key}_image",
            'name'                => 'image',
            'label'               => __('Image', 'app/cms'),
            'label'               => __('Image', 'app/cms'),
            'type'                => 'image',
            'required'            => 0,
            'save_post_thumbnail' => 0,
            'library'             => 'all',
            'return_format'       => 'id',
            'preview_size'        => 'thumbnail',
        ],
    ];

    if ($prefs !== null) {
        $order  = array_flip(array_keys($prefs));
        $fields = array_replace_recursive($order, $fields, $prefs);
        $fields = array_filter($fields, 'is_array');
    }

    return array_values($fields);
}

/**
 * Sets up the default and current language for ACF.
 *
 * @listens PLL#action:pll_language_defined
 *     Fires when the current language is defined.
 *
 * @return void
 */
function pll_language_defined(): void
{
    $dl = pll_default_language();
    $pl = pll_preferred_language();

    acf_update_setting('default_language', $dl);
    acf_update_setting('current_language', $pl);

    acf_localize_data([
        'language' => $pl,
    ]);
}

/**
 * Registers hooks to set the the default and current language for ACF.
 *
 * @listens PLL#action:pll_no_language_defined
 *     Fires when no language has been defined yet.
 *
 * @return void
 */
function pll_no_language_defined(): void
{
    add_filter('acf/settings/default_language', 'pll_default_language');
    add_filter('acf/settings/current_language', '\\App\\Cms\\Modules\\Polylang\\pll_preferred_language');
}

/**
 * Filters the array of taxonomy names to exclude Polylang's custom taxonomies.
 *
 * @listens ACF#filter:acf/get_taxonomies
 * @listens XYZ#filter:acf/get_object_taxonomies
 *
 * @param  array $taxonomies  An array of taxonomy names.
 * @return array An array of taxonomy names.
 */
function filter_pll_taxonomies(array $taxonomies): array
{
    $pll_taxonomies = get_taxonomies(['_pll' => true], 'names');

    return array_diff($taxonomies, $pll_taxonomies);
}

/**
 * Displays a languages form fields.
 *
 * This meta box allows one to switch between translations.
 *
 * Note: This function uses `constant('OBJECT')` instead of the constant directly
 * to prevent a PHP parse error where it expects to be casting a value to object.
 *
 * @listens ACF#action:acf/options_page/submitbox_before_major_actions
 *     Fires before the major-publishing-actions div.
 *
 * @param  array $page The current options page.
 * @return void
 */
function display_post_languages_meta_box(array $page): void
{
    $view = array_replace($page, [
        'current_language'    => pll_preferred_language(constant('OBJECT')),
        'available_languages' => PLL()->model->get_languages_list(),
    ]);

    acf_get_view(path('resources/views/acf-options-page-meta-box-translations.php'), $view);
}
