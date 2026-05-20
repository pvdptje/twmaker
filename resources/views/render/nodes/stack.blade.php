<div data-node-id="{{ $node['id'] }}" data-node-type="stack" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</div>
