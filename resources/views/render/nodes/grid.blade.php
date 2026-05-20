<div data-node-id="{{ $node['id'] }}" data-node-type="grid" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</div>
