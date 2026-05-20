<main data-page-title="{{ e($document['page_metadata']['title'] ?? '') }}">
    @foreach ($document['document_tree'] as $section)
        {!! $renderer->renderSection($section, $library) !!}
    @endforeach
</main>
