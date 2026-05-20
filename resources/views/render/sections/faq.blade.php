@php
    $heading = $section['children'][0] ?? null;
    $pairs = array_chunk(array_slice($section['children'], 1), 2);
    $columnsClass = ($section['props']['layout'] ?? 'single_column') === 'two_column' ? 'lg:grid-cols-2' : 'lg:grid-cols-1';
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }}">
    <div class="{{ $classes->sectionInner($section) }}">
        @if ($heading)
            <div class="mx-auto mb-10 max-w-3xl text-center">
                {!! $renderer->renderNode($heading, $library) !!}
            </div>
        @endif

        <div class="grid gap-4 {{ $columnsClass }}">
            @foreach ($pairs as $pair)
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    @foreach ($pair as $child)
                        {!! $renderer->renderNode($child, $library) !!}
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
