<?php

namespace App\Cms\Exceptions;

use App\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if the custom field is not of the expected format.
 */
class InvalidCustomFieldException extends \RuntimeException implements Exception
{
}
