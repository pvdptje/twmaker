<div data-node-id="{{ $node['id'] }}" data-node-type="card" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</div>
