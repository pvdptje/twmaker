@php
    $children = $section['children'];
    $logo = $children[0] ?? null;
    $nav = $children[1] ?? null;
    $cta = $children[2] ?? null;
@endphp

<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }} border-b border-neutral-200">
    <div class="{{ $classes->sectionInner($section) }} flex items-center justify-between gap-6 py-4">
        @if ($logo)
            <div class="h-10 w-32 overflow-hidden">
                {!! $renderer->renderNode($logo, $library) !!}
            </div>
        @endif

        @if ($nav)
            {!! $renderer->renderNode($nav, $library) !!}
        @endif

        @if ($cta)
            {!! $renderer->renderNode($cta, $library) !!}
        @endif
    </div>
</section>
