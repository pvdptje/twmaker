<label data-node-id="{{ $node['id'] }}" data-node-type="form_group" class="{{ $classes->node($node) }}">
    {!! $renderer->renderChildren($node['children'] ?? [], $library) !!}
</label>
