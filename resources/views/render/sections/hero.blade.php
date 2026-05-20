@php
    $children = $section['children'];
    $variant = $section['props']['variant'] ?? 'centered';
    $isSplit = in_array($variant, ['split_left_image', 'split_right_image'], true);
    $badge = in_array($children[0]['type'] ?? null, ['badge', 'element_instance'], true) ? $children[0] : null;
    $offset = $badge ? 1 : 0;
    $heading = $children[$offset] ?? null;
    $text = $children[$offset + 1] ?? null;
    $cta = ($children[$offset + 2]['type'] ?? null) === 'element_instance' ? $children[$offset + 2] : null;
    $image = collect($children)->firstWhere('type', 'image');
    $visualUrl = $image ? null : ($section['props']['image_url'] ?? 'placeholder:hero');
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }} overflow-hidden">
    <div class="{{ $classes->sectionInner($section) }}">
        @if ($isSplit)
            <div class="grid items-center gap-12 lg:grid-cols-2">
                @if ($variant === 'split_left_image')
                    <div class="rounded-2xl bg-neutral-100 p-4 shadow-sm">
                        @if ($image)
                            {!! $renderer->renderNode($image, $library) !!}
                        @else
                            <img class="h-96 w-full rounded-xl object-cover" src="{{ $renderer->placeholderSrc($visualUrl) }}" alt="">
                        @endif
                    </div>
                @endif

                <div class="flex flex-col gap-6 {{ ($section['props']['alignment'] ?? 'left') === 'center' ? 'items-center text-center' : 'items-start text-left' }}">
                    @foreach (array_filter([$badge, $heading, $text, $cta]) as $child)
                        {!! $renderer->renderNode($child, $library) !!}
                    @endforeach
                </div>

                @if ($variant !== 'split_left_image')
                    <div class="rounded-2xl bg-neutral-100 p-4 shadow-sm">
                        @if ($image)
                            {!! $renderer->renderNode($image, $library) !!}
                        @else
                            <img class="h-96 w-full rounded-xl object-cover" src="{{ $renderer->placeholderSrc($visualUrl) }}" alt="">
                        @endif
                    </div>
                @endif
            </div>
        @else
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
                @foreach (array_filter([$badge, $heading, $text, $cta, $image]) as $child)
                    {!! $renderer->renderNode($child, $library) !!}
                @endforeach
            </div>
        @endif
    </div>
</section>
