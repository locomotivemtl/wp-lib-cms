<?php

namespace App\Cms\Exceptions;

use App\Cms\Contracts\Exceptions\Exception;

/**
 * The identifier of a post type was expected.
 */
class MissingPostTypeIdentifierException extends \RuntimeException implements Exception
{
}
