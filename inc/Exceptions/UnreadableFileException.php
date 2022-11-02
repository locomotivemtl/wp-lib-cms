<?php

namespace WpLib\Exceptions;

use WpLib\Contracts\Exceptions\Exception;

/**
 * Thrown if the file can not be read.
 */
class UnreadableFileException extends \RuntimeException implements Exception
{
}
