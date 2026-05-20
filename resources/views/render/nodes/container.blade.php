<div data-node-id="{{ $node['id'] }}" data-node-type="container" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</div>
