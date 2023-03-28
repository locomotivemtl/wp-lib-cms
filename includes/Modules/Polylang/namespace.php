<?php

namespace Locomotive\Cms\Modules\Polylang;

use PLL_Cache;
use PLL_Language;
use PLL_Links_Directory;
use PLL_Model;
use WP_Post;

use const OBJECT;

use Locomotive\Cms\Modules\PageForPosts;

/**
 * Bootstraps the module.
 *
 * @return void
 */
function bootstrap() : void
{
    register_initial_hooks();
}

/**
 * Registers actions and filters for the module.
 *
 * @return void
 */
function register_initial_hooks() : void
{
    $pfp_option = PageForPosts::OPTION_NAME;

    add_action( 'pll_init', __NAMESPACE__ . '\\pll_init' );
    add_filter( 'pll_languages_list', __NAMESPACE__ . '\\filter_languages_list', 10, 2 );

    add_action( "update_option_{$pfp_option}", __NAMESPACE__ . '\\clean_languages_cache' );
}

/**
 * Fires after Polylang is completely initialized.
 *
 * @listens PLL#action:pll_init
 *
 * @return void
 */
function pll_init() : void
{
    add_filter( 'wp_link_query', __NAMESPACE__ . '\\filter_wp_link_query', 10, 2 );
}

/**
 * Adds special pages and active flag to languages list.
 *
 * @listens PLL#filter:pll_languages_list
 *     Filter the list of languages *before* it is stored in the persistent cache.
 *
 * @param  PLL_Language[] $languages The list of language objects.
 * @param  PLL_Model      $model     The PLL_Model object.
 * @return PLL_Language[]
 */
function filter_languages_list( array $languages, PLL_Model $model ) : array
{
    foreach ( $languages as $k => $language ) {
        if ( ! isset( $language->active ) ) {
            $languages[ $k ]->active = true;
        }

        $pages_for_post = get_option( PageForPosts::OPTION_NAME );
        if ( is_array( $pages_for_post ) ) {
            foreach ( $pages_for_post as $post_type => $post_id ) {
                if ( ! empty( $post_id ) ) {
                    $key = "page_for_{$post_type}";
                    $languages[ $k ]->{$key} = $model->post->get( $post_id, $language );
                }
            }
        }
    }

    return $languages;
}

/**
 * Cleans the Polylang language cache. Fires after the value of a specific option
 * has been successfully updated.
 *
 * @return void
 */
function clean_languages_cache() : void
{
    if ( function_exists( 'PLL' ) ) {
        PLL()->model->clean_languages_cache();
    }
}

/**
 * Filters the link query results to add extra information for duplicate titles.
 *
 * @listens WP#filter:wp_link_query
 *     Filters the link query results.
 *
 * @param array $results {
 *     An associative array of query results.
 *
 *     @type array {
 *         @type int    $ID        Post ID.
 *         @type string $title     The trimmed, escaped post title.
 *         @type string $permalink Post permalink.
 *         @type string $info      A 'Y/m/d'-formatted date for 'post' post type,
 *                                 the 'singular_name' post type label otherwise.
 *     }
 * }
 * @param  array $query An array of WP_Query arguments.
 * @return array The mutated $query.
 */
function filter_wp_link_query( array $results, array $query ) : array
{
    $ref = [];

    foreach ( $results as $i => $result ) {
        $title = $result['title'];

        if ( ! isset( $ref[$title] ) ) {
            $ref[$title] = 0;
        }

        $ref[$title]++;
    }

    foreach ( $results as $i => $result ) {
        $title = $result['title'];

        if ( $ref[$title] > 1 ) {
            $lang = pll_get_post_language( $result['ID'] );
            if ( $lang ) {
                $results[$i]['info'] = $lang . ' — ' . $result['info'];
            }
        }
    }

    return $results;
}

/**
 * Returns the list of available languages.
 *
 * This function is an alternative to {@see \pll_languages_list()},
 * which provides safer error handling and wider range of filtering.
 *
 * @param  array       $args  An array of key => value arguments to match against each object.
 * @param  string|null $field Field from the object to place instead of the entire object.
 * @param  string|null $key   Field from the object to use as keys for the new array.
 * @return array
 */
function get_languages_list( array $args = [], $field = null, $key = null ) : array
{
    if ( ! function_exists( 'PLL' ) ) {
        return [];
    }

    $languages = PLL()->model->get_languages_list();

    if ( ! empty( $args ) ) {
        $languages = array_reduce( $languages, function ( array $languages, PLL_Language $language ) use ( $args ) {
            foreach ( $args as $key => $val ) {
                if ( property_exists( $language, $key ) && $val === $language->{$key} ) {
                    $languages[] = $language;
                }
            }

            return $languages;
        }, [] );
    }

    if ( is_null( $field ) && is_null( $key ) ) {
        return $languages;
    }

    $plucked = [];

    foreach ( $languages as $language ) {
        $_key = null;
        $_val = null;

        if ( is_null( $field ) ) {
            $_val = $language;
        } elseif ( property_exists( $language, $field ) ) {
            $_val = $language->{$field};
        } else {
            continue;
        }

        if ( ! is_null( $key ) ) {
            if ( isset( $language->{$key} ) ) {
                $_key = $language->{$key};
            }
        }

        if ( is_null( $_key ) ) {
            $plucked[] = $_val;
        } else {
            $plucked[$_key] = $_val;
        }
    }

    return $plucked;
}

/**
 * Returns the list of available translations (language switcher).
 *
 * Unlike Polylang's helper function {@see \pll_get_the_languages()},
 * this function focuses on returning an array of data instead of HTML.
 *
 * @param  array $args Optional. Language switcher parameters.
 *     See {@see \pll_the_languages()} for list of accepted parameters.
 * @return array[] One or more available translation link objects.
 */
function get_the_languages( array $args = [] ) : array
{
    $has_languages = count( get_languages_list( [ 'active' => true ], 'slug' ) ) > 1;
    if ( $has_languages ) {
        $args = [
            'raw'                    => 1,
        ] + $args + [
            'hide_if_no_translation' => true,
        ];

        return pll_the_languages( $args );
    }

    return [];
}

/**
 * Returns the current language or the preferred language on admin side.
 *
 * @param  string $field Optional. The language field to return ({@see \PLL_Language}),
 *     defaults to 'slug', pass the `OBJECT` constant to get the language object.
 * @return \PLL_Language|string|bool The requested field for the preferred language or FALSE.
 */
function pll_preferred_language( $field = 'slug' )
{
    if ( ! function_exists( 'PLL' ) ) {
        return false;
    }

    $lang = pll_current_language( $field );
    if ( $lang ) {
        return $lang;
    }

    $lang = PLL()->pref_lang;
    if ( $lang ) {
        if ( OBJECT === $field ) {
            return $lang;
        }

        return $lang->{$field} ?? false;
    }

    return false;
}

/**
 * Filters the URI for a page.
 *
 * If the language is set from the directory name,
 * the URI for the page will be altered to ensure
 * it contains the correct language for the page.
 *
 * @listens WP#filter:get_page_uri
 *
 * @param  string      $uri  Page URI.
 * @param  WP_Post     $page Page object.
 * @return string|bool Page URI, FALSE on error.
 */
function pll_get_page_uri( $uri, WP_Post $page )
{
    if ( ! function_exists('PLL') ) {
        return $uri;
    }

    $pll = PLL();

    $filters_links = $pll->filters_links;

    if ( isset( $filters_links->cache ) && ( $filters_links->cache instanceof PLL_Cache ) ) {
        $pll_cache = $filters_links->cache;
    } else {
        $pll_cache = null;
    }

    if ( null !== $pll_cache ) {
        $cache_key = "post:{$page->ID}:{$uri}";

        $_uri = $pll_cache->get( $cache_key );
        if ( false !== $_uri ) {
            return $_uri;
        }
    }

    if ( ! $pll->model->is_translated_post_type( $page->post_type ) ) {
        return $uri;
    }

    $lang = $pll->model->post->get_language( $page->ID );

    if ( isset( $pll->links_model ) && ( $pll->links_model instanceof PLL_Links_Directory ) ) {
        $uri = switch_language_in_uri( $uri, $lang );
    }

    /**
     * Filters the URI for a page.
     *
     * @event filter:pll_get_page_uri
     *
     * @param string       $uri  Page URI.
     * @param PLL_Language $lang Language object.
     * @param WP_Post      $page Page object.
     */
    $uri = apply_filters( 'pll_get_page_uri', $uri, $lang, $page );

    if ( null !== $pll_cache ) {
        $pll_cache->set( $cache_key, $uri );
    }

    return $uri;
}

/**
 * Changes the language code in the URI.
 *
 * @access private
 *
 * @see pll_get_page_uri()
 *
 * @param   string       $uri  Page URI.
 * @param   PLL_Language $lang Language object.
 * @return  string  The modified URI.
 */
function switch_language_in_uri( $uri, PLL_Language $lang )
{
    $uri = remove_language_from_uri( $uri );
    return add_language_to_uri( $uri, $lang );
}

/**
 * Removes the language code from the URI.
 *
 * @access private
 *
 * @see switch_language_in_uri()
 *
 * @param  string $uri Page URI.
 * @return string The modified URI.
 */
function remove_language_from_uri( $uri )
{
    $pll = PLL();

    foreach ( $pll->model->get_languages_list() as $language ) {
        if ( ! $pll->options['hide_default'] || $pll->options['default_lang'] != $language->slug ) {
            $languages[] = $language->slug;
        }
    }

    if ( ! empty( $languages ) ) {
        $pattern = '#^' . ( $pll->options['rewrite'] ? '' : 'language\/' ) . '(' . implode( '|', $languages ) . ')(\/|$)#';

        $uri = preg_replace( $pattern, '', $uri );
    }
    return $uri;
}

/**
 * Adds the language code to the URI.
 *
 * @access private
 *
 * @see switch_language_in_uri()
 *
 * @param  string       $uri  Page URI.
 * @param  PLL_Language $lang Language object.
 * @return string The modified URI.
 */
function add_language_to_uri( $uri, PLL_Language $lang )
{
    if ( ! empty( $lang ) ) {
        $pll = PLL();

        $base = $pll->options['rewrite'] ? '' : 'language/';
        $slug = $pll->options['default_lang'] === $lang->slug && $pll->options['hide_default'] ? '' : $base . $lang->slug . '/';

        if ( false === strpos( $uri, $slug ) ) {
            $uri = $slug . $uri;
        }
    }

    return $uri;
}
