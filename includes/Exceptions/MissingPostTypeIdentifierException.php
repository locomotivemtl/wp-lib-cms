<?php

namespace Locomotive\Cms\Exceptions;

use Locomotive\Cms\Contracts\Exceptions\Exception;

/**
 * The identifier of a post type was expected.
 */
class MissingPostTypeIdentifierException extends \RuntimeException implements Exception
{
}
