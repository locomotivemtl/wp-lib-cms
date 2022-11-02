<?php

namespace WpLib\Exceptions;

use WpLib\Contracts\Exceptions\Exception;

/**
 * Thrown if a value does not match with a set of values.
 */
class UnexpectedValueException extends \UnexpectedValueException implements Exception
{
}
