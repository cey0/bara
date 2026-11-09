<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use RuntimeException;

/**
 * Thrown by Model::findOrFail(); the application renders it as a 404.
 */
class ModelNotFoundException extends RuntimeException
{
}
