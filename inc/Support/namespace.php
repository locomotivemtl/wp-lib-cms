<?php

namespace App\Cms\Support;

use App\Cms\Exceptions\InvalidArgumentException;

use const App\Cms\PLUGIN_PATH;
use const App\Cms\PLUGIN_URL;

/**
 * Retrieves the directory URI of the framework.
 *
 * If a file is passed in, it'll be appended to the end of the URI.
 *
 * @param  string $file Optional. File to search for in the framework directory.
 * @return string The URI of the file or framework directory.
 */
function uri(string $file = ''): string
{
    $file = ltrim($file, '/');
    if ($file) {
        return rtrim(PLUGIN_URL, '/') . '/' . $file;
    }

    return PLUGIN_URL;
}

/**
 * Retrieves the directory path of the framework.
 *
 * If a file is passed in, it'll be appended to the end of the path.
 *
 * @param  string $file Optional. File to search for in the framework directory.
 * @return string The path of the file or framework directory.
 */
function path(string $file = ''): string
{
    $file = ltrim($file, '/');
    if ($file) {
        return rtrim(PLUGIN_PATH, '/') . '/' . $file;
    }

    return PLUGIN_PATH;
}

/**
 * Outputs a view template.
 *
 * @param  string $path File to search for in the framework directory.
 * @param  array  $data The variables to be passed through to the view.
 * @return void
 */
function view(string $path, array $data = []): void
{
    if ('.php' !== substr($path, -4)) {
        $path = path("resources/views/{$path}.php");
    }

    if (file_exists($path)) {
        extract($data, EXTR_SKIP);
        include $path;
    }
}

/**
 * Load the translated strings for a domain.
 *
 * Loads the plugin's translated strings similar to {@see load_plugin_textdomain()}.
 *
 * @fires  WP#filter:plugin_locale
 *
 * @param  string|null $domain The plugin's text domain.
 * @return bool
 */
function load_textdomain(string $domain = null): bool
{
    if (empty($domain)) {
        $domain = 'app/cms';
    }

    $locale = determine_locale();
    $locale = apply_filters('plugin_locale', $locale, $domain);
    $mofile = $locale . '.mo';
    $mopath = path('resources/languages/' . $mofile);

    return \load_textdomain($domain, $mopath);
}

/**
 * Filter zero-width characters from a string.
 *
 * The set of special characters was based on {@link https://gitorious.org/mediawiki/mediawiki-trunk-phase3/source/includes/Sanitizer.php#L1677}.
 *
 * @link http://tools.ietf.org/html/3454#section-3.1 Characters that will be ignored in IDNs.
 *
 * @param  string $text Sanitized string.
 * @return string
 */
function sanitize_zero_chars(string $text): string
{
    $strip = "/
        \\s|          # General whitespace
        \xc2\xad|     # 00ad SOFT HYPHEN
        \xe1\xa0\x86| # 1806 MONGOLIAN TODO SOFT HYPHEN
        \xe2\x80\x8b| # 200b ZERO WIDTH SPACE
        \xe2\x81\xa0| # 2060 WORD JOINER
        \xef\xbb\xbf| # feff ZERO WIDTH NO-BREAK SPACE
        \xcd\x8f|     # 034f COMBINING GRAPHEME JOINER
        \xe1\xa0\x8b| # 180b MONGOLIAN FREE VARIATION SELECTOR ONE
        \xe1\xa0\x8c| # 180c MONGOLIAN FREE VARIATION SELECTOR TWO
        \xe1\xa0\x8d| # 180d MONGOLIAN FREE VARIATION SELECTOR THREE
        \xe2\x80\x8c| # 200c ZERO WIDTH NON-JOINER
        \xe2\x80\x8d| # 200d ZERO WIDTH JOINER
        [\xef\xb8\x80-\xef\xb8\x8f] # fe00-fe0f VARIATION SELECTOR-1-16
        /xuD";

    $text = preg_replace($strip, '', $text);

    return $text;
}

/**
 * Sanitizes POST values from a checkbox taxonomy metabox.
 *
 * @see \taxonomy_meta_box_sanitize_cb_checkboxes()
 *
 * This function prevents a warning from being throw raised when dealing with a non-array $terms.
 *
 * @param  string $taxonomy The taxonomy name.
 * @param  mixed  $terms    Raw term data from the 'tax_input' field.
 * @return int[] Array of sanitized term IDs.
 */
function taxonomy_meta_box_sanitize_cb_checkboxes(string $taxonomy, $terms): array
{
    return array_map('intval', (array) $terms);
}

/**
 * Get an existing image size.
 *
 * @see WP#has_image_size()
 *
 * @param  string $name The image size to check.
 * @return ?array Image size data.
 */
function get_image_size(string $name): ?array
{
    $sizes = wp_get_additional_image_sizes();
    return $sizes[$name] ?? null;
}

/**
 * Build a localized sentence recommending a particular image size.
 *
 * @param  int|array  $width  Width or size.
 * @param  int|null   $height Height.
 * @param  float|null $ratio  Ratio.
 * @return string
 * @throws InvalidArgumentException If parameters are missing or invalid.
 */
function recommended_image_size($width, $height = null, $ratio = null): string
{
    if (is_array($width)) {
        $size = $width;
        $num  = count($size);

        if (isset($size['width'], $size['height'])) {
            $width  = $size['width'];
            $height = $size['height'];
        } elseif (3 === $num) {
            [$width, $height, $ratio] = $size;
        } elseif (2 === $num) {
            [$width, $height] = $size;
        } else {
            throw new InvalidArgumentException(
                'Expected valid size array'
            );
        }
    }

    if (null === $width || null === $height) {
        throw new InvalidArgumentException(
            'Expected valid width and height'
        );
    }

    if (null === $ratio && $width !== $height) {
        $ratio = round(($width / $height), 2);
    }

    if ($ratio) {
        /* translators: 1: Width, 2: Height, 3: Ratio. */
        $text = _x('Recommended image size: %d??%d (%s).', 'image size: width x height + ratio', 'app/cms');
        return sprintf($text, $width, $height, $ratio);
    }

    /* translators: 1: Width, 2: Height. */
    $text = _x('Recommended image size: %d??%d.', 'image size: width x height', 'app/cms');
    return sprintf($text, $width, $height);
}

/**
 * Build a localized sentence recommending an SVG image or a particular image size.
 *
 * @param  int|array|null $width  Width or size.
 * @param  int|null       $height Height.
 * @param  float|null     $ratio  Ratio.
 * @throws InvalidArgumentException If parameters are missing or invalid.
 * @return string
 */
function recommended_svg_image($width = null, $height = null, $ratio = null): string
{
    if (is_array($width)) {
        $size = $width;
        $num  = count($size);

        if (isset($size['width'], $size['height'])) {
            $width  = $size['width'];
            $height = $size['height'];
        } elseif (3 === $num) {
            [$width, $height, $ratio] = $size;
        } elseif (2 === $num) {
            [$width, $height] = $size;
        } else {
            throw new InvalidArgumentException(
                'Expected valid size array'
            );
        }
    }

    if ($width && $height) {
        if (null === $ratio && $width && $height && $width !== $height) {
            $ratio = round(($width / $height), 2);
        }

        if ($ratio) {
            /* translators: 1: Width, 2: Height, 3: Ratio. */
            $text = _x('An SVG image is recommended or an image size: %d??%d (%s).', 'image size: width x height + ratio', 'app/cms');
            return sprintf($text, $width, $height, $ratio);
        }

        /* translators: 1: Width, 2: Height. */
        $text = _x('An SVG image is recommended or an image size: %d??%d.', 'image size: width x height', 'app/cms');
        return sprintf($text, $width, $height);
    }

    return _x('An SVG image is recommended.', 'image size', 'app/cms');
}

/**
 * Parse the host of a given URL.
 *
 * @param  string  $url  The URL to parse.
 * @return string|null
 */
function parse_url_host($url)
{
    if (empty($url)) {
        return null;
    }

    $host = parse_url($url, PHP_URL_HOST);

    if (empty($host)) {
        return null;
    }

    $host = str_replace('www.', '', $host);

    return $host;
}

/**
 * Determines whether the current request is a WordPress REST API request.
 *
 * @fires filter:is_wp_rest_request
 *
 * @return bool TRUE if it's a WordPress REST API request, FALSE otherwise.
 */
function is_wp_rest_request(): bool
{
    /**
     * Filters whether the current request is a WordPress REST API request.
     *
     * @event filter:is_wp_rest_request
     *
     * @param bool $is_wp_rest_request Whether the current request is a WordPress REST API request.
     */
    return apply_filters('xyz/is_wp_rest_request', defined('REST_REQUEST') && REST_REQUEST);
}

/**
 * Determines whether the current request is a WordPress CLI request.
 *
 * @fires filter:is_wp_cli_request
 *
 * @return bool TRUE if it's a WordPress CLI request, FALSE otherwise.
 */
function is_wp_cli_request(): bool
{
    /**
     * Filters whether the current request is a WordPress CLI request.
     *
     * @event filter:is_wp_cli_request
     *
     * @param bool $is_wp_cli_request Whether the current request is a WordPress CLI request.
     */
    return apply_filters('xyz/is_wp_cli_request', defined('WP_CLI') && WP_CLI);
}

/**
 * Determines whether the current request is a WordPress importer request.
 *
 * @fires filter:is_wp_import_request
 *
 * @return bool TRUE if it's a WordPress importer request, FALSE otherwise.
 */
function is_wp_import_request(): bool
{
    /**
     * Filters whether the current request is a WordPress importer request.
     *
     * @event filter:is_wp_import_request
     *
     * @param bool $is_wp_import_request Whether the current request is a WordPress importer request.
     */
    return apply_filters('xyz/is_wp_import_request', defined('WP_LOAD_IMPORTERS') && WP_LOAD_IMPORTERS);
}

/**
 * Determines whether the current request is a WordPress XML-RPC request.
 *
 * @fires filter:is_wp_xmlrpc_request
 *
 * @return bool TRUE if it's a WordPress XML-RPC request, FALSE otherwise.
 */
function is_wp_xmlrpc_request(): bool
{
    /**
     * Filters whether the current request is a WordPress XML-RPC request.
     *
     * @event filter:is_wp_xmlrpc_request
     *
     * @param bool $is_wp_xmlrpc_request Whether the current request is a WordPress XML-RPC request.
     */
    return apply_filters('xyz/is_wp_xmlrpc_request', defined('XMLRPC_REQUEST') && XMLRPC_REQUEST);
}

/**
 * Determines whether the current request is a WordPress Admin AJAX request.
 *
 * @fires filter:is_admin_doing_ajax
 *
 * @return bool TRUE if it's an AJAX request from the WordPress Admin, FALSE otherwise.
 */
function is_admin_doing_ajax(): bool
{
    $is_admin_doing_ajax = false;

    if (wp_doing_ajax()) {
        /**
         * Get admin URL and referrer.
         *
         * @@see \check_admin_referer()
         */
        $admin_url = strtolower(admin_url());
        $referrer  = strtolower(wp_get_referer());

        $is_admin_doing_ajax = (0 === strpos($referrer, $admin_url));
    }

    /**
     * Filters whether the current admin request is a WordPress AJAX request.
     *
     * @event filter:is_admin_doing_ajax
     *
     * @param bool $wp_doing_ajax Whether the current request is a WordPress Admin AJAX request.
     */
    return apply_filters('xyz/is_admin_doing_ajax', $is_admin_doing_ajax);
}

/**
 * Determines whether the current request is a WordPress Frontend AJAX request.
 *
 * @fires filter:is_frontend_doing_ajax
 *
 * @return bool TRUE if it's an AJAX request from the WordPress Frontend, FALSE otherwise.
 */
function is_frontend_doing_ajax(): bool
{
    $is_frontend_doing_ajax = false;

    if (wp_doing_ajax()) {
        /**
         * Get admin URL and referrer.
         *
         * @@see \check_admin_referer()
         */
        $admin_url = strtolower(admin_url());
        $referrer  = strtolower(wp_get_referer());

        $is_frontend_doing_ajax = (false === strpos($referrer, $admin_url));
    }

    /**
     * Filters whether the current frontend request is a WordPress AJAX request.
     *
     * @event filter:is_frontend_doing_ajax
     *
     * @param bool $wp_doing_ajax Whether the current request is a frontend WordPress AJAX request.
     */
    return apply_filters('xyz/is_frontend_doing_ajax', $is_frontend_doing_ajax);
}

/**
 * Hooks a function or method to a specific action event.
 *
 * @param  string    $tag             The name of the action to hook the $function_to_add callback to.
 * @param  callable  $function_to_add The callback to be run when the action is applied.
 * @param  int|float $priority        Optional. Used to specify the order in which the functions
 *                                    associated with a particular action are executed. Default 10.
 * @param  int       $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return bool Returns FALSE if the $function_to_add is not callable, otherwise returns TRUE.
 */
function maybe_add_action(string $tag, $function_to_add, $priority = 10, int $accepted_args = 1): bool
{
    // Bail early if no callable
    if (!is_callable($function_to_add)) {
        return false;
    }

    return \add_action($tag, $function_to_add, $priority, $accepted_args);
}

/**
 * Hooks a function or method to a specific filter event.
 *
 * @param  string    $tag             The name of the filter to hook the $function_to_add callback to.
 * @param  callable  $function_to_add The callback to be run when the filter is applied.
 * @param  int|float $priority        Optional. Used to specify the order in which the functions
 *                                    associated with a particular action are executed. Default 10.
 * @param  int       $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return bool Returns FALSE if the $function_to_add is not callable, otherwise returns TRUE.
 */
function maybe_add_filter(string $tag, $function_to_add, $priority = 10, int $accepted_args = 1): bool
{
    // Bail early if no callable
    if (!is_callable($function_to_add)) {
        return false;
    }

    return \add_filter($tag, $function_to_add, $priority, $accepted_args);
}

/**
 * Hooks a function or method to a specific filter event as if it was an action event.
 *
 * The filtered value is unaffected by the callback.
 *
 * @param  string    $tag             The name of the action to hook the $function_to_add callback to.
 * @param  callable  $function_to_add The callback to be run when the action is applied.
 * @param  int|float $priority        Optional. Used to specify the order in which the functions
 *                                    associated with a particular action are executed. Default 10.
 * @param  int       $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return bool Returns FALSE if the $function_to_add is not callable, otherwise returns TRUE.
 */
function add_action_on_filter(string $tag, $function_to_add, $priority = 10, int $accepted_args = 1): bool
{
    $proxy_function = function ($value) use ($function_to_add) {
        call_user_func_array($function_to_add, func_get_args());
        return $value;
    };

    return \add_filter($tag, $proxy_function, $priority, $accepted_args);
}

/**
 * Call the callback with one or all handlers disabled for the given action or filter.
 *
 * Temporarily disables the specified hook, or all hooks, from a specified filter or action
 * before calling $callback.
 *
 * @link https://gist.github.com/westonruter/6647252
 *
 * @global array $wp_filter Stores all of the filters.
 *
 * @param  callable      $callback The callable to be called while the filter is disabled.
 * @param  string        $tag      The filter to remove hooks from.
 * @param  callable|null $handler  The filter callback to be removed.
 * @return mixed Returns the return value of the callback.
 */
function without_filters(callable $callback, $tag, $handler = null)
{
    global $wp_filter;

    if ($handler !== null) {
        return without_filter($callback, $tag, $handler);
    }

    $wp_hook = null;

    if (isset($wp_filter[$tag]) && $wp_filter[$tag] instanceof WP_Hook) {
        $wp_hook = $wp_filter[$tag];
        unset($wp_filter[$tag]);
    }

    $retval = call_user_func($callback);

    if (isset($wp_hook)) {
        $wp_filter[$tag] = $wp_hook;
    }

    return $retval;
}

/**
 * Call the callback with one handler disabled for the given action or filter.
 *
 * Temporarily disables the specified hook from a specified filter or action
 * before calling $callback.
 *
 * @link https://gist.github.com/westonruter/6647252
 *
 * @param  callable $callback The callable to be called while the filter is disabled.
 * @param  string   $tag      The filter to remove hooks from.
 * @param  callable $handler  The filter callback to be removed.
 * @return mixed Returns the return value of the callback.
 */
function without_filter(callable $callback, $tag, $handler)
{
    $priority = has_filter($tag, $handler);

    if (false !== $priority) {
        remove_filter($tag, $handler, $priority);
    }

    $retval = call_user_func($callback);

    if (false !== $priority) {
        // For array_slice(), can't use NULL since cast to integer
        $accepted_args = PHP_INT_MAX;
        add_filter($tag, $handler, $priority, $accepted_args);
    }

    return $retval;
}

/**
 * Call the callback with one handler added for the given action or filter.
 *
 * Temporarily enables the specified hook from a specified filter or action
 * before calling $callback.
 *
 * @param  callable  $callback The callable to be called while the filter is enabled.
 * @param  array     $filters  {
 *     @type string    $tag           The filter to add hooks to.
 *     @type callable  $handler       The filter callback to be added.
 *     @type int|float $priority      Optional. Used to specify the order in which the functions
 *                                    associated with a particular action are executed. Default 10.
 *     @type int       $accepted_args Optional. The number of arguments the function accepts. Default 1.
 * }
 * @return mixed Returns the return value of the callback.
 * @throws InvalidArgumentException If the hooks are invalid.
 */
function with_filters(callable $callback, array $filters)
{
    foreach ($filters as $i => $filter_params) {
        if (count($filter_params) < 2) {
            throw new InvalidArgumentException(sprintf(
                'Filter at offset %d requires at least a hook name and a function name',
                $i
            ));
        }

        add_filter(...array_slice($filter_params, 0, 4));
    }

    $retval = call_user_func($callback);

    foreach ($filters as $filter_params) {
        remove_filter(...array_slice($filter_params, 0, 3));
    }

    return $retval;
}

/**
 * Call the callback with one handler added for the given action or filter.
 *
 * Temporarily enables the specified hook from a specified filter or action
 * before calling $callback.
 *
 * @param  callable  $callback      The callable to be called while the filter is enabled.
 * @param  string    $tag           The filter to add hooks to.
 * @param  callable  $handler       The filter callback to be added.
 * @param  int|float $priority      Optional. Used to specify the order in which the functions
 *                                  associated with a particular action are executed. Default 10.
 * @param  int       $accepted_args Optional. The number of arguments the function accepts. Default 1.
 * @return mixed Returns the return value of the callback.
 */
function with_filter(callable $callback, $tag, $handler, $priority = 10, int $accepted_args = 1)
{
    add_filter($tag, $handler, $priority, $accepted_args);

    $retval = call_user_func($callback);

    remove_filter($tag, $handler, $priority);

    return $retval;
}

/**
 * Helper to create links to edit.php with params.
 *
 * @see \WP_Posts_List_Table::get_edit_link()
 *
 * @param  array  $args  Associative array of URL parameters for the link.
 * @param  string $label Link text.
 * @param  string $class Optional. Class attribute. Default empty string.
 * @return string The formatted link string.
 */
function get_edit_link(array $args, string $label, string $class = ''): string
{
    $url = add_query_arg($args, 'edit.php');

    $class_html   = '';
    $aria_current = '';
    if (!empty($class)) {
        $class_html = sprintf(
            ' class="%s"',
            esc_attr($class)
        );

        if ('current' === $class) {
            $aria_current = ' aria-current="page"';
        }
    }

    return sprintf(
        '<a href="%s"%s%s>%s</a>',
        esc_url($url),
        $class_html,
        $aria_current,
        $label
    );
}
