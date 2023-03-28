<?php

namespace Locomotive\Cms\Exceptions;

use Locomotive\Cms\Contracts\Exceptions\Exception;

/**
 * Thrown if the file can not be read.
 */
class UnreadableFileException extends \RuntimeException implements Exception
{
}
