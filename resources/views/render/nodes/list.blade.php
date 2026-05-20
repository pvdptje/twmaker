<ul data-node-id="{{ $node['id'] }}" data-node-type="list" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</ul>
