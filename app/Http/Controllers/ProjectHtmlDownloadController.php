<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Project;
use App\Services\Rendering\Renderer;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ProjectHtmlDownloadController extends Controller
{
    public function __invoke(Project $project, Renderer $renderer): BinaryFileResponse
    {
        abort_unless($this->canAccessProject($project), 404);

        $pages = $project->pages()
            ->whereNotNull('html_source')
            ->oldest()
            ->get()
            ->filter(fn (Page $page): bool => trim((string) $page->html_source) !== '')
            ->values();

        abort_if($pages->isEmpty(), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'twmaker-project-');
        abort_if($zipPath === false, 500);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500);
        }

        $usedFilenames = [];

        $pages->each(function (Page $page) use ($zip, $renderer, &$usedFilenames): void {
            $htmlSource = trim((string) $page->html_source);

            $zip->addFromString(
                $this->uniqueFilename($page, $usedFilenames),
                $renderer->renderDownloadHtml($htmlSource, $page->name),
            );
        });

        $zip->close();

        $filename = Str::slug($project->name) ?: 'project';

        return response()
            ->download($zipPath, "{$filename}.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, true>  $usedFilenames
     */
    private function uniqueFilename(Page $page, array &$usedFilenames): string
    {
        $base = Str::slug($page->name) ?: 'page';
        $filename = "{$base}.html";
        $suffix = 2;

        while (isset($usedFilenames[$filename])) {
            $filename = "{$base}-{$suffix}.html";
            $suffix++;
        }

        $usedFilenames[$filename] = true;

        return $filename;
    }

    private function canAccessProject(Project $project): bool
    {
        $teamId = $project->team_id;

        return is_string($teamId)
            && $teamId !== ''
            && auth()->user()->teams()->whereKey($teamId)->exists();
    }
}
