<article data-node-id="{{ $instance['id'] }}" data-node-type="element_instance" data-element-type="feature_card" class="{{ $classes->element('feature_card', $props) }}">
    @if ($props['icon'])
        <div class="text-blue-600">{{ $props['icon'] }}</div>
    @endif
    <h3 class="text-xl font-semibold text-neutral-950">{{ $props['heading'] }}</h3>
    <p class="text-base leading-7 text-neutral-600">{{ $props['body'] }}</p>
    @if ($props['link'])
        <a class="text-blue-600 underline" href="{{ $props['link']['href'] }}">{{ $props['link']['label'] }}</a>
    @endif
</article>
