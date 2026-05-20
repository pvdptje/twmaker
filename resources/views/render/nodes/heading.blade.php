@php($tag = 'h'.($node['props']['level'] ?? 2))
<{{ $tag }} data-node-id="{{ $node['id'] }}" data-node-type="heading" class="{{ $classes->node($node) }}">{{ $node['props']['text'] }}</{{ $tag }}>
