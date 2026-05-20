<?php

namespace App\Services\Generation\Stages;

class Repair
{
    public function repairDocument(array $document, array $errors): array
    {
        foreach ($document['document_tree'] ?? [] as $index => $section) {
            $document['document_tree'][$index] = $this->repairSection($section);
        }

        return $document;
    }

    private function repairSection(array $section): array
    {
        if (! isset($section['type'], $section['props']) || ! is_array($section['props'])) {
            return $section;
        }

        if (in_array($section['type'], ['footer', 'stats_band'], true)) {
            $section['props']['columns'] = $this->repairColumnCount(
                $section['props']['columns'] ?? null,
                $section['children'] ?? [],
                $section['type'] === 'footer' ? 1 : 2,
                $section['type'] === 'footer' ? 1 : 3,
            );
        }

        return $section;
    }

    private function repairColumnCount(mixed $value, array $children, int $minimum, int $fallback): int
    {
        $instances = count(array_filter(
            $children,
            fn (array $child): bool => ($child['type'] ?? null) === 'element_instance',
        ));

        if ($instances > 0) {
            return $this->clamp($instances, $minimum, 4);
        }

        if (is_int($value)) {
            return $this->clamp($value, $minimum, 4);
        }

        if (is_numeric($value)) {
            return $this->clamp((int) $value, $minimum, 4);
        }

        if (is_array($value)) {
            return $this->clamp(count($value), $minimum, 4);
        }

        return $fallback;
    }

    private function clamp(int $value, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, $value));
    }
}
