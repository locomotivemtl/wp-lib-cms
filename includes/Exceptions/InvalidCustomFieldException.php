<?php

namespace Locomotive\Cms\Exceptions;

use Locomotive\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if the custom field is not of the expected format.
 */
class InvalidCustomFieldException extends \RuntimeException implements Exception
{
}
