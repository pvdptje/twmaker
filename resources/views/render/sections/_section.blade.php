<section data-node-id="{{ $section['id'] }}" data-node-type="{{ $section['type'] }}" class="{{ $classes->section($section) }}">
    <div class="{{ $classes->sectionInner($section) }}">
        {!! $renderer->renderChildren($section['children'], $library) !!}
    </div>
</section>
