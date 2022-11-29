<?php

namespace App\Cms\Exceptions;

use App\Cms\Contracts\Exceptions\Exception;

/**
 * The identifier of a taxonomy was expected.
 */
class MissingTaxonomyIdentifierException extends \RuntimeException implements Exception
{
}
