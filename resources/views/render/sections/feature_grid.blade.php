@php
    $children = $section['children'];
    $heading = ($children[0]['type'] ?? null) === 'heading' ? $children[0] : null;
    $textIndex = $heading ? 1 : 0;
    $text = ($children[$textIndex]['type'] ?? null) === 'text' ? $children[$textIndex] : null;
    $start = ($heading ? 1 : 0) + ($text ? 1 : 0);
    $items = array_slice($children, $start);
    $columnsClass = match ($section['props']['columns'] ?? 3) {
        2 => 'lg:grid-cols-2',
        4 => 'lg:grid-cols-4',
        default => 'lg:grid-cols-3',
    };
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }}">
    <div class="{{ $classes->sectionInner($section) }}">
        <div class="mx-auto flex max-w-3xl flex-col gap-4 {{ ($section['props']['alignment'] ?? 'left') === 'center' ? 'items-center text-center' : 'items-start text-left' }}">
            @foreach (array_filter([$heading, $text]) as $child)
                {!! $renderer->renderNode($child, $library) !!}
            @endforeach
        </div>

        <div class="mt-12 grid gap-6 {{ $columnsClass }}">
            @foreach ($items as $child)
                {!! $renderer->renderNode($child, $library) !!}
            @endforeach
        </div>
    </div>
</section>
