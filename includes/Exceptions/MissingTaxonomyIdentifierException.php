<?php

namespace Locomotive\Cms\Exceptions;

use Locomotive\Cms\Contracts\Exceptions\Exception;

/**
 * The identifier of a taxonomy was expected.
 */
class MissingTaxonomyIdentifierException extends \RuntimeException implements Exception
{
}
