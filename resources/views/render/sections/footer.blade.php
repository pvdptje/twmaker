@php
    $children = $section['children'];
    $logo = $children[0] ?? null;
    $tagline = ($children[1]['type'] ?? null) === 'text' ? $children[1] : null;
    $start = $tagline ? 2 : 1;
    $copyright = collect($children)->reverse()->firstWhere('type', 'text');
    $links = array_values(array_filter(array_slice($children, $start), fn ($child) => ($child['type'] ?? null) === 'element_instance'));
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }} border-t border-neutral-200">
    <div class="{{ $classes->sectionInner($section) }}">
        <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
            <div class="flex max-w-sm flex-col gap-4">
                @foreach (array_filter([$logo, $tagline]) as $child)
                    {!! $renderer->renderNode($child, $library) !!}
                @endforeach
            </div>

            <div class="flex flex-wrap gap-8">
                @foreach ($links as $child)
                    {!! $renderer->renderNode($child, $library) !!}
                @endforeach
            </div>
        </div>

        @if ($copyright && $copyright !== $tagline)
            <div class="mt-10 border-t border-neutral-200 pt-6">
                {!! $renderer->renderNode($copyright, $library) !!}
            </div>
        @endif
    </div>
</section>
