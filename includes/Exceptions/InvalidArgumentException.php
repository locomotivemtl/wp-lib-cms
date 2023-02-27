<?php

namespace App\Cms\Exceptions;

use App\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if an argument is not of the expected type.
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
}
