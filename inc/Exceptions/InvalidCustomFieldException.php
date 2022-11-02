<?php

namespace WpLib\Exceptions;

use WpLib\Contracts\Exceptions\Exception;

/**
 * Thrown if the custom field is not of the expected format.
 */
class InvalidCustomFieldException extends \RuntimeException implements Exception
{
}
