<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Project;
use App\Services\Rendering\Renderer;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PageHtmlDownloadController extends Controller
{
    public function __invoke(Project $project, Page $page, Renderer $renderer): Response
    {
        abort_unless($page->project_id === $project->id, 404);

        $htmlSource = trim((string) ($page->html_source ?? ''));
        abort_if($htmlSource === '', 404);

        $filename = Str::slug($page->name) ?: 'page';

        return response($renderer->renderDownloadHtml($htmlSource, $page->name))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'.html"');
    }
}
