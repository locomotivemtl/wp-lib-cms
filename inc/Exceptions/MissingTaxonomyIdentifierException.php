<?php

namespace WpLib\Exceptions;

use WpLib\Contracts\Exceptions\Exception;

/**
 * The identifier of a taxonomy was expected.
 */
class MissingTaxonomyIdentifierException extends \RuntimeException implements Exception
{
}
