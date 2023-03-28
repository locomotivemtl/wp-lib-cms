<?php

namespace Locomotive\Cms\Exceptions;

use Locomotive\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if a value does not match with a set of values.
 */
class UnexpectedValueException extends \UnexpectedValueException implements Exception
{
}
