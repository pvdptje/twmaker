@php
    $children = $section['children'];
    $heading = ($children[0]['type'] ?? null) === 'heading' ? $children[0] : null;
    $items = array_slice($children, $heading ? 1 : 0);
    $columnsClass = match ($section['props']['columns'] ?? 3) {
        2 => 'lg:grid-cols-2',
        4 => 'lg:grid-cols-4',
        default => 'lg:grid-cols-3',
    };
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }}">
    <div class="{{ $classes->sectionInner($section) }}">
        @if ($heading)
            <div class="mx-auto mb-10 max-w-3xl text-center">
                {!! $renderer->renderNode($heading, $library) !!}
            </div>
        @endif

        <div class="grid gap-6 {{ $columnsClass }}">
            @foreach ($items as $child)
                {!! $renderer->renderNode($child, $library) !!}
            @endforeach
        </div>
    </div>
</section>
