<?php

namespace App\Services\Generation\Stages;

use RuntimeException;

class ElementResolver
{
    public function assertReferencesResolve(array $document, array $library): void
    {
        foreach ($document['document_tree'] ?? [] as $section) {
            $this->walk($section['children'] ?? [], $library);
        }
    }

    private function walk(array $nodes, array $library): void
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'element_instance') {
                $libraryId = $node['props']['library_id'] ?? null;

                if (! is_string($libraryId) || ! array_key_exists($libraryId, $library)) {
                    throw new RuntimeException("Element instance references missing library_id [{$libraryId}].");
                }
            }

            $this->walk($node['children'] ?? [], $library);
        }
    }
}
