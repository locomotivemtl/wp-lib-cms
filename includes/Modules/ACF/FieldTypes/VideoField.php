<?php

namespace Locomotive\Cms\Modules\ACF\FieldTypes;

use acf_field_image;

use function Locomotive\Cms\Support\path;
use function Locomotive\Cms\Support\uri;

/**
 * Video Field
 *
 * The Video field allows a video file to be uploaded and selected
 * by using the native WordPress media modal.
 *
 * This field type is based on the official Image field type.
 *
 * Last update: 2022-04-11 12:50 EST for ACF v5.10.2
 *
 * Todo: Add support for multiple sources à la WP View's `[video]` shortcode.
 */
class VideoField extends acf_field_image
{
    /**
     * Setup the field type data.
     *
     * @return void
     */
    public function initialize()
    {
        // extends
        parent::initialize();

        // vars
        $this->name  = 'video';
        $this->label = __('Video', 'acf');
    }

    /**
     * Enqueue scripts for the field.
     *
     * @listens action:acf/input/admin_enqueue_scripts
     *     Added in includes/fields/class-acf-field.php
     *
     * @return  void
     */
    public function input_admin_enqueue_scripts()
    {
        // localize
        acf_localize_text([
            'Select Video'    => __('Select Video', 'acf'),
            'Edit Video'      => __('Edit Video', 'acf'),
            'Update Video'    => __('Update Video', 'acf'),
            'All video files' => __('All video files', 'acf'),
        ]);

        $handle = 'acf-field-video';

        if (!wp_script_is($handle, 'registered')) {
            wp_register_script(
                $handle,
                uri('resources/scripts/acf-field-video.js'),
                ['acf-input'],
                '',
                true
            );
        }

        if (!wp_style_is($handle, 'registered')) {
            wp_register_style(
                $handle,
                uri('resources/styles/acf-field-video.css'),
                ['acf-input']
            );
        }

        wp_enqueue_script($handle);
        wp_enqueue_style($handle);
    }

    /**
     * Create the HTML interface for the field.
     *
     * @listens filter:acf/render_field
     *     Added in includes/fields/class-acf-field.php
     *
     * @see \edit_form_image_editor()
     *     The attachment is rendered in the same way WordPress does.
     *
     * @overrides acf_field_image::render_field()
     *     Registers the renders much of the same view.
     *
     * @param   array  $field  The field dataset.
     * @return  void
     */
    public function render_field($field)
    {
        $uploader = acf_get_setting('uploader');

        // Enqueue uploader scripts
        if ('wp' === $uploader) {
            acf_enqueue_uploader();
        }

        // Elements and attributes.
        $div_attrs = [
            'class'             => 'acf-video-uploader',
            'data-library'      => $field['library'],
            'data-mime_types'   => $field['mime_types'],
            'data-uploader'     => $uploader,
        ];
        $wrap_attrs = [
            'class' => 'show-if-value video-wrap',
        ];
        $video_attrs = [
            'controls' => true,
            'preload'  => 'metadata',
        ];
        $source_attrs = [
            'src'    => '',
            # 'class'  => 'wp-video-shortcode',
        ];

        // Detect value.
        if ($field['value'] && is_numeric($field['value'])) {
            $video = wp_get_attachment_url($field['value']);
            if ($video) {
                $mime = wp_check_filetype($video, wp_get_mime_types());

                $source_attrs['src']  = $video;
                $source_attrs['type'] = $mime['type'];

                $div_attrs['class'] .= ' has-value';
            }
        }

        // view
        $view = [
            'field_object' => $this,
            'field'        => $field,
            'uploader'     => $uploader,
            'div_attrs'    => $div_attrs,
            'wrap_attrs'   => $wrap_attrs,
            'video_attrs'  => $video_attrs,
            'source_attrs' => $source_attrs,
        ];

        // load view
        acf_get_view(path('resources/views/acf-field-video.php'), $view);
    }

    /**
     * Create extra settings for the field.
     *
     * @listens filter:acf/render_field_settings
     *     Added in includes/fields/class-acf-field.php
     *
     * @overrides acf_field_image::render_field_settings()
     *     Registers the same field settings but with different localized labels.
     *
     * @param   array  $field  The field dataset.
     * @return  void
     */
    public function render_field_settings($field)
    {
        // clear numeric settings
        $clear = [
            'min_size',
            'max_size',
        ];

        foreach ($clear as $k) {
            if (empty($field[$k])) {
                $field[$k] = '';
            }
        }

        // return_format
        acf_render_field_setting($field, [
            'label'         => __('Return Format', 'acf'),
            'instructions'  => '',
            'type'          => 'radio',
            'name'          => 'return_format',
            'layout'        => 'horizontal',
            'choices'       => [
                'array' => __('Video Array', 'acf'),
                'url'   => __('Video URL', 'acf'),
                'id'    => __('Video ID', 'acf'),
            ],
        ]);

        // library
        acf_render_field_setting($field, [
            'label'         => __('Library', 'acf'),
            'instructions'  => __('Limit the media library choice', 'acf'),
            'type'          => 'radio',
            'name'          => 'library',
            'layout'        => 'horizontal',
            'choices'       => [
                'all'           => __('All', 'acf'),
                'uploadedTo'    => __('Uploaded to post', 'acf'),
            ],
        ]);

        acf_render_field_setting($field, [
            'label'         => '',
            'type'          => 'text',
            'name'          => 'min_size',
            'prepend'       => __('File size', 'acf'),
            'append'        => 'MB',
        ]);

        acf_render_field_setting($field, [
            'label'         => '',
            'type'          => 'text',
            'name'          => 'max_size',
            'prepend'       => __('File size', 'acf'),
            'instructions'  => __('Restrict which video files can be uploaded', 'acf'),
            'append'        => 'MB',
        ]);

        // allowed type
        acf_render_field_setting($field, [
            'label'         => __('Allowed file types', 'acf'),
            'instructions'  => __('Comma separated list. Leave blank for all types', 'acf'),
            'type'          => 'text',
            'name'          => 'mime_types',
        ]);
    }
}
