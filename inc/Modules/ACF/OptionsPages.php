<?php

namespace App\Cms\Modules\ACF;

use App\Cms\Contracts\Bootable;

use function App\Cms\Modules\Polylang\pll_preferred_language;

/**
 * ACF Module: Options Pages
 *
 * This module registers a collection of custom options pages.
 */
abstract class OptionsPages implements Bootable
{
    /**
     * Store of WordPress Admin menu URLs
     *
     * @var string[]
     */
    protected $menu_urls;

    /**
     * Boots the module and registers actions and filters.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('acf/init', [$this, 'register_options_pages'], 1);

        if (is_admin() && function_exists('PLL')) {
            add_filter('clean_url', [$this, 'change_options_page_url']);
        }
    }

    /**
     * Registers custom options pages.
     *
     * @listens ACF#action:acf/init
     *
     * @return void
     */
    abstract public function register_options_pages(): void;

    /**
     * Customizes the URLs of the custom options pages.
     *
     * @listens ACF#filter:clean_url
     *
     * @param  string $url The cleaned URL to be returned.
     * @return string
     */
    public function change_options_page_url(string $url): string
    {
        if ($this->menu_urls && in_array($url, $this->menu_urls)) {
            $url = add_query_arg(['lang' => pll_preferred_language()], $url);
        }

        return $url;
    }
}
