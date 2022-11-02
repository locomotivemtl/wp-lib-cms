<?php

namespace WpLib\Exceptions;

use WpLib\Contracts\Exceptions\Exception;

/**
 * Thrown if an argument is not of the expected type.
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
}
