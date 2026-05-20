<?php

namespace App\Services\Generation;

use App\Models\Project;

class ProjectLibraryLoader
{
    public function full(Project $project): array
    {
        return $project->reusableElements()
            ->get()
            ->keyBy('id')
            ->map(fn ($element): array => [
                'id' => $element->id,
                'name' => $element->name,
                'type' => $element->type,
                'default_props' => $element->default_props ?? [],
            ])
            ->all();
    }

    public function digest(Project $project): array
    {
        return $project->reusableElements()
            ->get(['id', 'name', 'type', 'default_props'])
            ->map(fn ($element): array => [
                'id' => $element->id,
                'name' => $element->name,
                'type' => $element->type,
                'summary' => $this->summarize($element->default_props ?? []),
            ])
            ->all();
    }

    private function summarize(array $props): string
    {
        $encoded = json_encode($props, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return strlen($encoded) > 240 ? substr($encoded, 0, 237).'...' : $encoded;
    }
}
