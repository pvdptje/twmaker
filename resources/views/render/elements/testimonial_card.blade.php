<figure data-node-id="{{ $instance['id'] }}" data-node-type="element_instance" data-element-type="testimonial_card" class="{{ $classes->element('testimonial_card', $props) }}">
    <blockquote class="text-base leading-7 text-neutral-950">{{ $props['quote'] }}</blockquote>
    <figcaption class="text-sm text-neutral-600">{{ $props['author_name'] }}@if ($props['author_title']) - {{ $props['author_title'] }}@endif</figcaption>
</figure>
