<?php

namespace Locomotive\Cms\Exceptions;

use Locomotive\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if an argument is not of the expected type.
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
}
