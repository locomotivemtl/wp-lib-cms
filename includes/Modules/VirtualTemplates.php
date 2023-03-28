<?php

namespace Locomotive\Cms\Modules;

use Locomotive\Cms\Contracts\Bootable;
use InvalidArgumentException;
use WP_Post;

/**
 * Module: Virtual Theme Post Templates
 *
 * Registers virtual post templates to perform actions like redirect posts.
 *
 * @todo Replace proxy / noop hooks with a proper observer / subject pattern.
 *
 * @link https://gist.github.com/leoken/4395160
 */
class VirtualTemplates implements Bootable
{
    /**
     * The feature key.
     *
     * @var string
     */
    const FEATURE_NAME = 'virtual-templates';

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('locomotive/virtual-templates/resolution/key=redirection-child',  [$this, 'template_redirect_to_child'], 10, 1);
        add_action('locomotive/virtual-templates/resolution/key=redirection-parent', [$this, 'template_redirect_to_parent'], 10, 1);

        add_filter('locomotive/virtual-templates/post_states/key=redirection-child',  [$this, 'display_post_state_redirection'], 10, 1);
        add_filter('locomotive/virtual-templates/post_states/key=redirection-parent', [$this, 'display_post_state_redirection'], 10, 1);

        add_action('template_redirect', [$this, 'template_redirect'], 1);
        add_filter('theme_templates', [$this, 'theme_templates'], 10, 4);

        if (is_admin()) {
            add_filter('display_post_states', [$this, 'display_post_states'], 10, 2);
        }
    }

    /**
     * Filter the default post display states used in the posts list table.
     *
     * @listens WP#filter:display_post_states documented in wp-admin/includes/template.php
     *
     * @param  array<string, string> $post_states An array of post display states.
     * @param  WP_Post               $post        The current post object.
     * @return array<string, string>
     */
    public function display_post_states(array $post_states, WP_Post $post): array
    {
        $virtual_templates = $this->get_virtual_templates($post);

        if (empty($virtual_templates)) {
            return $post_states;
        }

        $post_template = get_page_template_slug($post);

        if (isset($virtual_templates[$post_template])) {
            /**
             * Filters the post display states based on the special template.
             *
             * The dynamic portion of the hook name, `$post_template`,
             * refers to the post's template.
             *
             * @event filter:locomotive/virtual-templates/post_states/key={$post_template}
             *
             * @param array<string, string> $post_states An array of post display states.
             * @param WP_Post               $post        The current post object.
             */
            $post_states = apply_filters("locomotive/virtual-templates/post_states/key={$post_template}", $post_states, $post);

            /**
             * Filters the post display states based on the special template.
             *
             * @event filter:locomotive/virtual-templates/post_states
             *
             * @param array<string, string> $post_states   An array of post display states.
             * @param string                $post_template The page template.
             * @param WP_Post               $post          The current post object.
             */
            $post_states = apply_filters('locomotive/virtual-templates/post_states', $post_states, $post_template, $post);
        }

        return $post_states;
    }

    /**
     * Returns the theme's post templates for a given post type.
     *
     * @fires filter:locomotive/virtual-templates/virtual-templates
     *
     * @param WP_Post|null $post      Optional. The post being edited, provided for context.
     * @param string       $post_type Optional. Post type to get the templates for. Default 'page'.
     *                                If a post is provided, its post type is used.
     * @return array<string, string> Array of special page templates, keyed by filename,
     *                               with the value of the translated header name.
     */
    public function get_virtual_templates($post = null, $post_type = 'page'): array
    {
        if (!$post_type || !post_type_supports($post_type, static::FEATURE_NAME)) {
            return [];
        }

        $post_templates = [
            'redirection-child'  => __('Redirect to first child'),
            'redirection-parent' => __('Redirect to closest parent'),
        ];

        /**
         * Filters list of special page templates.
         *
         * @event filter:locomotive/virtual-templates/virtual-templates
         *
         * @param string[]     $post_templates Array of special page templates. Keys are filenames, values are translated names.
         * @param WP_Post|null $post           The post being edited, provided for context, or null.
         * @param string       $post_type      Post type to get the templates for.
         */
        $post_templates = apply_filters('locomotive/virtual-templates/templates', $post_templates, $post, $post_type);

        return $post_templates;
    }

    /**
     * Filters list of page templates for a theme.
     *
     * @listens WP#filter:theme_templates
     *
     * @param   string[]     $post_templates Array of page templates. Keys are filenames,
     *                                       values are translated names.
     * @param   WP_Theme     $theme          The theme object.
     * @param   WP_Post|null $post           The post being edited, provided for context, or null.
     * @param   string       $post_type      Post type to get the templates for.
     * @return  string[]
     */
    public function theme_templates($post_templates, $theme, $post, $post_type)
    {
        $virtual_templates = $this->get_virtual_templates($post, $post_type);

        if (!empty($virtual_templates)) {
            $post_templates = array_replace(
                $virtual_templates,
                $post_templates
            );
        }

        return $post_templates;
    }

    /**
     * Fires before determining which template to load,
     * executing any custom template redirections.
     *
     * @listens WP#action:template_redirect
     *
     * @fires action:locomotive/virtual-templates/resolution/key={$post_template}
     * @fires action:locomotive/virtual-templates/resolution
     *
     * @global WP_Post $post
     *
     * @return void
     */
    public function template_redirect()
    {
        if (is_admin() || !is_singular()) {
            return;
        }

        global $post;

        $virtual_templates = $this->get_virtual_templates($post);

        if (empty($virtual_templates)) {
            return;
        }

        $post_template = get_page_template_slug($post);

        if (isset($virtual_templates[$post_template])) {
            /**
             * Fires the template's resolution process.
             *
             * The dynamic portion of the hook name, `$post_template`,
             * refers to the post's template.
             *
             * @event action:locomotive/virtual-templates/resolution/key={$post_template}
             *
             * @param WP_Post $post The post related to the template.
             */
            do_action("locomotive/virtual-templates/resolution/key={$post_template}", $post);

            /**
             * Fires the template's resolution process.
             *
             * @event action:locomotive/virtual-templates/resolution
             *
             * @param string  $post_template The page template.
             * @param WP_Post $post          The post related to the template.
             */
            do_action('locomotive/virtual-templates/resolution', $post_template, $post);
        }
    }

    /**
     * Redirect to the first child post.
     *
     * @listens action:locomotive/virtual-templates/post_states/key=redirection-child
     * @listens action:locomotive/virtual-templates/post_states/key=redirection-parent
     *
     * @param  array<string, string> $post_states An array of post display states.
     * @return array<string, string>
     */
    public function display_post_state_redirection(array $post_states): array
    {
        $post_states['redirects'] = _x('Redirects', 'page label');

        return $post_states;
    }

    /**
     * Redirect to the first child post.
     *
     * @listens action:locomotive/virtual-templates/resolution/key=redirection-child
     *
     * @param  WP_Post $post The page associated to the template.
     * @return void
     */
    public function template_redirect_to_child(WP_Post $post): void
    {
        $child = get_children([
            'numberposts' => 1,
            'post_parent' => $post->ID,
            'post_type'   => $post->post_type,
            'post_status' => 'publish',
            'orderby'     => 'menu_order title',
            'order'       => 'ASC'
        ]);

        if (is_array($child)) {
            $child = reset($child);
        }

        if (
            ($child instanceof WP_Post) &&
            wp_redirect(get_permalink($child->ID), 303, 'wp-lib-cms')
        ) {
            exit;
        }
    }

    /**
     * Redirect to the closest parent post.
     *
     * @listens action:locomotive/virtual-templates/resolution/key=redirection-parent
     *
     * @param  WP_Post $post The page associated to the template.
     * @return void
     */
    public function template_redirect_to_parent(WP_Post $post): void
    {
        if ($post->post_parent > 0) {
            $parent = get_post($post->post_parent);
        }

        if (
            ($parent instanceof WP_Post) &&
            wp_redirect(get_permalink($parent->ID), 303, 'wp-lib-cms')
        ) {
            exit;
        }
    }
}
