<?php

namespace App\Services\Generation\Stages;

use App\Services\Schema\SchemaValidator;

class Validator
{
    public function __construct(private readonly SchemaValidator $validator) {}

    public function assertValidDocument(array $document): void
    {
        $this->validator->assertDocument($document);
    }
}
