<li data-node-id="{{ $node['id'] }}" data-node-type="list_item" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</li>
