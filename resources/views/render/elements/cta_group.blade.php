<div data-node-id="{{ $instance['id'] }}" data-node-type="element_instance" data-element-type="cta_group" class="{{ $classes->element('cta_group', $props) }}">
    @if ($props['primary'])
        <a class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 font-semibold text-white" href="{{ $props['primary']['href'] }}">{{ $props['primary']['label'] }}</a>
    @endif
    @if ($props['secondary'])
        <a class="inline-flex items-center justify-center rounded-md border border-neutral-300 px-4 py-2 font-semibold text-neutral-950" href="{{ $props['secondary']['href'] }}">{{ $props['secondary']['label'] }}</a>
    @endif
</div>
