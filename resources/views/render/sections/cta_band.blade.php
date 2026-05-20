@php
    $children = $section['children'];
    $heading = $children[0] ?? null;
    $text = count($children) === 3 ? $children[1] : null;
    $cta = $children[count($children) - 1] ?? null;
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }}">
    <div class="{{ $classes->sectionInner($section) }}">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-8 py-12">
            <div class="mx-auto flex max-w-4xl flex-col items-center gap-6 text-center">
                @foreach (array_filter([$heading, $text, $cta]) as $child)
                    {!! $renderer->renderNode($child, $library) !!}
                @endforeach
            </div>
        </div>
    </div>
</section>
