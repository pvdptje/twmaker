<nav data-node-id="{{ $instance['id'] }}" data-node-type="element_instance" data-element-type="nav_link_group" class="{{ $classes->element('nav_link_group', $props) }}">
    @foreach ($props['links'] as $link)
        <a class="{{ $link['active'] ? 'text-blue-600' : 'text-neutral-700' }}" href="{{ $link['href'] }}">{{ $link['label'] }}</a>
    @endforeach
</nav>
