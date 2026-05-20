<?php

namespace App\Services\Generation\Stages;

use RuntimeException;

class PromptBuilder
{
    public function system(string $stage): string
    {
        $path = resource_path("prompts/{$stage}.system.md");

        if (! is_file($path)) {
            throw new RuntimeException("Missing prompt file [{$path}].");
        }

        return file_get_contents($path) ?: '';
    }
}
