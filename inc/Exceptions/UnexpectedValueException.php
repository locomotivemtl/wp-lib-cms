<?php

namespace App\Cms\Exceptions;

use App\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if a value does not match with a set of values.
 */
class UnexpectedValueException extends \UnexpectedValueException implements Exception
{
}
