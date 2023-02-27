<?php

namespace App\Cms\Exceptions;

use App\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if the file can not be read.
 */
class UnreadableFileException extends \RuntimeException implements Exception
{
}
