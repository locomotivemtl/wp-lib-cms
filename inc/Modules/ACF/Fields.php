<?php

namespace App\Cms\Modules\ACF;

use App\Cms\Contracts\Bootable;
use App\Cms\Exceptions\InvalidCustomFieldException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function App\Cms\Modules\ACF\acf_is_field_layout;
use function App\Cms\Modules\ACF\acf_is_field_layout_key;

use const WP_CONTENT_DIR;

/**
 * ACF Module: Fields
 */
class Fields implements Bootable
{
    /**
     * Whether to to enable strict typing of data types.
     *
     * In strict mode, only official ACF data types are accepted (fields, groups, and layouts),
     * or a InvalidCustomFieldException will be thrown.
     *
     * @var bool
     */
    protected $strict_types = true;

    /**
     * Whether to enable strict file inclusion.
     *
     * In strict mode, only non-empty array structures are accepted.
     *
     * @var bool
     */
    protected $strict_files = false;

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('acf/include_fields', [ $this, 'autoload_local_fields' ]);
    }



    // Local Fields
    // =========================================================================

    /**
     * Autoload ACF groups, layouts, and fields from local PHP files.
     *
     * @listens ACF#action:acf/include_fields
     *
     * @return void
     * @throws InvalidCustomFieldException
     */
    public function autoload_local_fields() : void
    {
        /**
         * Filters the list of paths that are searched for local ACF PHP fields.
         *
         * @fires filter:xyz/acf/settings/load_php
         *
         * @param  string[] $paths The paths of local ACF fields.
         * @return string[]
         */
        $paths = apply_filters('xyz/acf/settings/load_php', []);

        if (!empty($paths)) {
            $this->import_local_fields($paths);
        }
    }

    /**
     * Register groups, layouts, and fields.
     *
     * @param  array $structs One or more local fields or groups.
     * @return void
     * @throws InvalidCustomFieldException
     */
    public function register_local_fields(array $structs): void
    {
        $this->register_models($structs);
    }

    /**
     * Import ACF groups, layouts, and fields from local PHP files.
     *
     * @param  string|string[] $paths One or more directory paths.
     * @return void
     * @throws InvalidCustomFieldException
     */
    public function import_local_fields($paths) : void
    {
        foreach ((array) $paths as $path) {
            if (file_exists($path)) {
                $directory = new RecursiveDirectoryIterator($path);
                $iterator  = new RecursiveIteratorIterator($directory);

                foreach ($iterator as $file_path) {
                    if ('php' !== pathinfo($file_path, PATHINFO_EXTENSION)) {
                        continue;
                    }

                    $contents = $this->include_file($file_path);

                    if (true === $contents) {
                        // Assume the file has been included previously.
                        continue;
                    }

                    if (false === $contents) {
                        throw new InvalidCustomFieldException(sprintf(
                            'Local ACF fields file [%s] could not be included',
                            ltrim(str_replace(WP_CONTENT_DIR, '', $file_path), '/')
                        ));
                    }

                    $is_empty = empty($contents);

                    if (false === $this->strict_files && ($is_empty || 1 === $contents)) {
                        // Assume the file is an empty placeholder
                        continue;
                    }

                    if (!is_array($contents) || $is_empty) {
                        throw new InvalidCustomFieldException(sprintf(
                            'Local ACF fields file [%s] must return an array of fields and groups, received %s',
                            ltrim(str_replace(WP_CONTENT_DIR, '', $file_path), '/'),
                            (is_object($contents)
                                ? get_class($contents)
                                : (is_scalar($contents)
                                    ? var_export($contents, true)
                                    : gettype($contents)
                                )
                            )
                        ) );
                    }

                    $this->register_models($contents, $file_path);
                }
            }
        }
    }

    /**
     * Include local PHP file within isolated scope.
     *
     * @param  string $file_path The file path to include.
     * @return mixed
     */
    protected function include_file(string $file_path)
    {
        return include_once $file_path;
    }

    /**
     * Registers fields and groups from imported local model structure(s).
     *
     * @param  array   $structs   One or more local model structure(s).
     * @param  ?string $file_path The file path containing the model structure(s).
     * @return void
     * @throws InvalidCustomFieldException
     */
    protected function register_models(array $structs, ?string $file_path = null): void
    {
        if (array_key_exists('key', $structs)) {
            $structs = [ $structs ];
        }

        foreach ($structs as $i => $struct) {
            $registered = $this->register_model($struct);

            if (null === $registered && true === $this->strict_types) {
                if ($file_path) {
                    $message = sprintf(
                        'Expected valid ACF field, group, or layout at offset [%s] in file [%s]',
                        $i,
                        ltrim(str_replace(WP_CONTENT_DIR, '', $file_path), '/')
                    );
                } else {
                    $message = sprintf(
                        'Expected valid ACF field, group, or layout at offset [%s]',
                        $i
                    );
                }
                throw new InvalidCustomFieldException($message);
            }
        }
    }

    /**
     * Register group, layout, and field.
     *
     * Layouts are acknowledged but ignored at this stage;
     * they can only be processed if included by a field.
     *
     * @param  array $struct A local field or group.
     * @return ?bool
     */
    protected function register_model(array $struct): ?bool
    {
        if (acf_is_field_group($struct) && acf_is_field_group_key($struct['key'])) {
            return acf_add_local_field_group($struct);
        }

        if (acf_is_field($struct) && acf_is_field_key($struct['key'])) {
            return acf_add_local_field($struct);
        }

        if (acf_is_field_layout($struct) && acf_is_field_layout_key($struct['key'])) {
            return true;
        }

        return null;
    }



    // Field Value
    // =========================================================================

    /**
     * Return NULL instead of FALSE when a value is blank.
     *
     * @listens filter:acf/format_value
     *
     * @param  mixed   $value   The value of the field as found in the database.
     * @param  integer $post_id The post ID which the value was loaded from.
     * @param  array   $field   The field structure.
     * @return mixed The mutated $value.
     */
    public function format_empty_value($value, $post_id, $field)
    {
        if ( false === $value && 'true_false' !== $field['type'] ) {
            if (
                ( isset( $field['multiple'] ) && $field['multiple'] ) ||
                ( isset( $field['min'], $field['max'] ) )
            ) {
                return [];
            }

            return null;
        }

        return $value;
    }
}
