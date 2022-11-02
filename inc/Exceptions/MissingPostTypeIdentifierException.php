<?php

namespace WpLib\Exceptions;

use WpLib\Contracts\Exceptions\Exception;

/**
 * The identifier of a post type was expected.
 */
class MissingPostTypeIdentifierException extends \RuntimeException implements Exception
{
}
