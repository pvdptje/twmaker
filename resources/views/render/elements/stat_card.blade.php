<div data-node-id="{{ $instance['id'] }}" data-node-type="element_instance" data-element-type="stat_card" class="{{ $classes->element('stat_card', $props) }}">
    <div class="text-3xl font-semibold text-neutral-950">{{ $props['value'] }}</div>
    <div class="text-sm text-neutral-600">{{ $props['label'] }}</div>
    @if ($props['trend_label'])
        <div class="text-xs text-emerald-700">{{ $props['trend_label'] }}</div>
    @endif
</div>
