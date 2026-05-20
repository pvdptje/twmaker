<?php

namespace App\Services\Schema;

use RuntimeException;

class SchemaValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode('; ', $errors));
    }
}
