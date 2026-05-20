<?php

namespace App\Services\Schema;

use Opis\JsonSchema\Validator;

class SchemaValidator
{
    private array $errors = [];

    public function validateDocument(array $document): bool
    {
        $this->errors = [];
        $this->check(DocumentSchema::schema(), $document, 'document');

        foreach ($document['document_tree'] ?? [] as $index => $section) {
            $this->validateSection($section, "document_tree.{$index}");
        }

        return $this->errors === [];
    }

    public function validateElementDefinition(array $element): bool
    {
        $this->errors = [];
        $this->check(ElementSchemas::definition(), $element, 'element');

        if (isset($element['type'], $element['default_props'])) {
            $this->check(ElementSchemas::props($element['type']), $element['default_props'], 'element.default_props');
            $this->validateElementSemantics($element['type'], $element['default_props'], 'element.default_props');
        }

        return $this->errors === [];
    }

    public function validateSectionNode(array $section): bool
    {
        $this->errors = [];
        $this->validateSection($section, 'section');

        return $this->errors === [];
    }

    public function validateContentNode(array $node): bool
    {
        $this->errors = [];
        $this->validateNode($node, 'node');

        return $this->errors === [];
    }

    public function assertDocument(array $document): void
    {
        if (! $this->validateDocument($document)) {
            throw new SchemaValidationException($this->errors);
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function validateSection(array $section, string $path): void
    {
        $this->check(SectionSchemas::section(), $section, $path);

        if (! isset($section['type'], $section['props'])) {
            return;
        }

        $this->check(SectionSchemas::props($section['type']), $section['props'], "{$path}.props");

        foreach ($section['children'] ?? [] as $index => $node) {
            $this->validateNode($node, "{$path}.children.{$index}");
        }

        $this->validateSectionChildren($section, $path);
    }

    private function validateNode(array $node, string $path): void
    {
        $this->check(NodeSchemas::node(), $node, $path);

        if (! isset($node['type'], $node['props'])) {
            return;
        }

        $this->check(NodeSchemas::props($node['type']), $node['props'], "{$path}.props");

        $hasChildren = array_key_exists('children', $node);
        $expectsChildren = in_array($node['type'], NodeSchemas::CONTAINER_TYPES, true);

        if ($expectsChildren && ! $hasChildren) {
            $this->errors[] = "{$path}: container-like node must include children";
        }

        if (! $expectsChildren && $hasChildren) {
            $this->errors[] = "{$path}: leaf node must not include children";
        }

        foreach ($node['children'] ?? [] as $index => $child) {
            $this->validateNode($child, "{$path}.children.{$index}");
        }

        if (($node['type'] ?? null) === 'form_group') {
            $this->validateSequence($node['children'] ?? [], [['text'], ['input', 'textarea']], "{$path}.children");
        }

        if (($node['type'] ?? null) === 'list') {
            foreach ($node['children'] ?? [] as $index => $child) {
                if (($child['type'] ?? null) !== 'list_item') {
                    $this->errors[] = "{$path}.children.{$index}: list children must be list_item nodes";
                }
            }
        }
    }

    private function validateSectionChildren(array $section, string $path): void
    {
        $children = $section['children'] ?? [];

        match ($section['type']) {
            'header' => $this->validateHeader($children, $path),
            'hero' => $this->validateHero($section, $children, $path),
            'logo_cloud' => $this->validateOptionalHeadingThenCount($children, 'image', 4, 8, 2, $path),
            'feature_grid' => $this->validateIntroThenInstances($children, 3, 12, $path),
            'feature_split' => $this->validateFeatureSplit($children, $path),
            'stats_band' => $this->validateOptionalHeadingThenExactInstances($children, $section['props']['columns'] ?? null, $path),
            'testimonial_grid' => $this->validateOptionalHeadingThenInstances($children, 1, 9, $path),
            'faq' => $this->validateFaq($children, $path),
            'cta_band' => $this->validateCtaBand($children, $path),
            'contact_form' => $this->validateContactForm($children, $path),
            'footer' => $this->validateFooter($children, $section['props']['columns'] ?? null, $path),
            default => null,
        };
    }

    private function validateHeader(array $children, string $path): void
    {
        if (count($children) < 2 || count($children) > 3) {
            $this->errors[] = "{$path}: header expects 2 or 3 children";
        }

        $this->expectType($children, 0, 'image', $path);
        $this->expectType($children, 1, 'element_instance', $path);
        if (isset($children[2])) {
            $this->expectType($children, 2, 'element_instance', $path);
        }
    }

    private function validateHero(array $section, array $children, string $path): void
    {
        $index = 0;
        if (isset($children[$index]) && in_array($children[$index]['type'] ?? null, ['badge', 'element_instance'], true)) {
            $index++;
        }
        $this->expectType($children, $index++, 'heading', $path);
        $this->expectType($children, $index++, 'text', $path);
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'element_instance') {
            $index++;
        }
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'image') {
            $index++;
        }
        if ($index !== count($children)) {
            $this->errors[] = "{$path}: hero children are out of order or contain unsupported types";
        }
    }

    private function validateFeatureSplit(array $children, string $path): void
    {
        $index = 0;
        $this->expectType($children, $index++, 'heading', $path);
        $this->expectType($children, $index++, 'text', $path);
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'list') {
            $index++;
        }
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'element_instance') {
            $index++;
        }
        if ($index !== count($children)) {
            $this->errors[] = "{$path}: feature_split children are out of order or contain unsupported types";
        }
    }

    private function validateFaq(array $children, string $path): void
    {
        $this->expectType($children, 0, 'heading', $path);
        $pairNodes = array_slice($children, 1);
        if (count($pairNodes) < 6 || count($pairNodes) > 24 || count($pairNodes) % 2 !== 0) {
            $this->errors[] = "{$path}: faq expects 3 to 12 question/answer pairs";
        }
        for ($i = 0; $i < count($pairNodes); $i += 2) {
            $this->expectNodeType($pairNodes[$i] ?? [], 'heading', "{$path}.children.".($i + 1));
            $this->expectNodeType($pairNodes[$i + 1] ?? [], 'text', "{$path}.children.".($i + 2));
        }
    }

    private function validateCtaBand(array $children, string $path): void
    {
        if (count($children) < 2 || count($children) > 3) {
            $this->errors[] = "{$path}: cta_band expects 2 or 3 children";
        }

        $this->expectType($children, 0, 'heading', $path);
        $last = count($children) - 1;
        if ($last > 0) {
            $this->expectType($children, $last, 'element_instance', $path);
        }
        if (count($children) === 3) {
            $this->expectType($children, 1, 'text', $path);
        }
    }

    private function validateContactForm(array $children, string $path): void
    {
        $this->expectType($children, 0, 'heading', $path);
        $index = 1;
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'text') {
            $index++;
        }
        $formGroups = 0;
        while (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'form_group') {
            $formGroups++;
            $index++;
        }
        if ($formGroups < 2 || $formGroups > 6) {
            $this->errors[] = "{$path}: contact_form expects 2 to 6 form_group nodes";
        }
        $this->expectType($children, $index++, 'element_instance', $path);
        if ($index !== count($children)) {
            $this->errors[] = "{$path}: contact_form children are out of order or contain unsupported types";
        }
    }

    private function validateFooter(array $children, ?int $columns, string $path): void
    {
        $this->expectType($children, 0, 'image', $path);
        $index = 1;
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'text') {
            $index++;
        }
        $instances = 0;
        while (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'element_instance') {
            $instances++;
            $index++;
        }
        if ($instances !== $columns) {
            $this->errors[] = "{$path}: footer expects {$columns} nav_link_group instances";
        }
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'text') {
            $index++;
        }
        if ($index !== count($children)) {
            $this->errors[] = "{$path}: footer children are out of order or contain unsupported types";
        }
    }

    private function validateOptionalHeadingThenCount(array $children, string $type, int $min, int $max, int $level, string $path): void
    {
        $index = 0;
        if (isset($children[0]) && ($children[0]['type'] ?? null) === 'heading') {
            if (($children[0]['props']['level'] ?? null) !== $level) {
                $this->errors[] = "{$path}.children.0: heading level must be {$level}";
            }
            $index = 1;
        }
        $count = count($children) - $index;
        if ($count < $min || $count > $max) {
            $this->errors[] = "{$path}: expects {$min} to {$max} {$type} children";
        }
        for ($i = $index; $i < count($children); $i++) {
            $this->expectType($children, $i, $type, $path);
        }
    }

    private function validateIntroThenInstances(array $children, int $min, int $max, string $path): void
    {
        $index = 0;
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'heading') {
            $index++;
        }
        if (isset($children[$index]) && ($children[$index]['type'] ?? null) === 'text') {
            $index++;
        }
        $count = count($children) - $index;
        if ($count < $min || $count > $max) {
            $this->errors[] = "{$path}: expects {$min} to {$max} element instances";
        }
        for ($i = $index; $i < count($children); $i++) {
            $this->expectType($children, $i, 'element_instance', $path);
        }
    }

    private function validateOptionalHeadingThenInstances(array $children, int $min, int $max, string $path): void
    {
        $index = isset($children[0]) && ($children[0]['type'] ?? null) === 'heading' ? 1 : 0;
        $count = count($children) - $index;
        if ($count < $min || $count > $max) {
            $this->errors[] = "{$path}: expects {$min} to {$max} element instances";
        }
        for ($i = $index; $i < count($children); $i++) {
            $this->expectType($children, $i, 'element_instance', $path);
        }
    }

    private function validateOptionalHeadingThenExactInstances(array $children, ?int $expected, string $path): void
    {
        $index = isset($children[0]) && ($children[0]['type'] ?? null) === 'heading' ? 1 : 0;
        $count = count($children) - $index;
        if ($count !== $expected) {
            $this->errors[] = "{$path}: expects exactly {$expected} element instances";
        }
        for ($i = $index; $i < count($children); $i++) {
            $this->expectType($children, $i, 'element_instance', $path);
        }
    }

    private function validateSequence(array $children, array $expectedTypes, string $path): void
    {
        if (count($children) !== count($expectedTypes)) {
            $this->errors[] = "{$path}: expected ".count($expectedTypes).' children';
        }

        foreach ($expectedTypes as $index => $types) {
            if (isset($children[$index]) && ! in_array($children[$index]['type'] ?? null, $types, true)) {
                $this->errors[] = "{$path}.{$index}: expected one of ".implode(', ', $types);
            }
        }
    }

    private function validateElementSemantics(string $type, array $props, string $path): void
    {
        if ($type === 'cta_group' && ! isset($props['primary']) && ! isset($props['secondary'])) {
            $this->errors[] = "{$path}: cta_group requires primary or secondary";
        }

        if ($type === 'cta_group' && ($props['primary'] ?? null) === null && ($props['secondary'] ?? null) === null) {
            $this->errors[] = "{$path}: cta_group requires primary or secondary";
        }
    }

    private function expectType(array $children, int $index, string $type, string $path): void
    {
        if (! isset($children[$index])) {
            $this->errors[] = "{$path}.children.{$index}: missing {$type} node";

            return;
        }

        $this->expectNodeType($children[$index], $type, "{$path}.children.{$index}");
    }

    private function expectNodeType(array $node, string $type, string $path): void
    {
        if (($node['type'] ?? null) !== $type) {
            $this->errors[] = "{$path}: expected {$type}";
        }
    }

    private function check(array $schema, mixed $data, string $path): void
    {
        $validator = new Validator;
        $result = $validator->validate(
            $this->jsonValue($this->normalizeDataForSchema($data, $schema)),
            $this->jsonValue($this->normalizeSchema($schema))
        );

        if (! $result->isValid()) {
            $this->errors[] = "{$path}: JSON schema validation failed";
        }
    }

    private function jsonValue(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private function normalizeDataForSchema(mixed $data, array $schema): mixed
    {
        if (is_array($data) && $this->schemaAcceptsObject($schema)) {
            $properties = $schema['properties'] ?? [];

            foreach ($properties as $property => $propertySchema) {
                if (array_key_exists($property, $data) && is_array($propertySchema)) {
                    $data[$property] = $this->normalizeDataForSchema($data[$property], $propertySchema);
                }
            }

            return (object) $data;
        }

        if (is_array($data) && ($schema['type'] ?? null) === 'array' && isset($schema['items']) && is_array($schema['items'])) {
            return array_map(fn (mixed $item): mixed => $this->normalizeDataForSchema($item, $schema['items']), $data);
        }

        return $data;
    }

    private function normalizeSchema(mixed $schema): mixed
    {
        if (! is_array($schema)) {
            return $schema;
        }

        $normalized = [];
        foreach ($schema as $key => $value) {
            if ($key === 'properties' && $value === []) {
                $normalized[$key] = (object) [];

                continue;
            }

            $normalized[$key] = $this->normalizeSchema($value);
        }

        return $normalized;
    }

    private function schemaAcceptsObject(array $schema): bool
    {
        $type = $schema['type'] ?? null;

        return $type === 'object' || (is_array($type) && in_array('object', $type, true));
    }
}
