@php
    $children = $section['children'];
    $heading = $children[0] ?? null;
    $text = $children[1] ?? null;
    $list = ($children[2]['type'] ?? null) === 'list' ? $children[2] : null;
    $cta = collect($children)->firstWhere('type', 'element_instance');
    $imageSide = $section['props']['image_side'] ?? 'right';
    $imageUrl = $section['props']['image_url'] ?? 'placeholder:feature';
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }}">
    <div class="{{ $classes->sectionInner($section) }}">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            @if ($imageSide === 'left')
                <div class="rounded-2xl bg-blue-50 p-6 shadow-sm">
                    <img class="h-80 w-full rounded-xl object-cover" src="{{ $renderer->placeholderSrc($imageUrl) }}" alt="">
                </div>
            @endif

            <div class="flex flex-col gap-5">
                @foreach (array_filter([$heading, $text, $list, $cta]) as $child)
                    {!! $renderer->renderNode($child, $library) !!}
                @endforeach
            </div>

            @if ($imageSide !== 'left')
                <div class="rounded-2xl bg-blue-50 p-6 shadow-sm">
                    <img class="h-80 w-full rounded-xl object-cover" src="{{ $renderer->placeholderSrc($imageUrl) }}" alt="">
                </div>
            @endif
        </div>
    </div>
</section>
