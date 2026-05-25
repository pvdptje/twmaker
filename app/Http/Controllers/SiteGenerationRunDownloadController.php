<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Project;
use App\Models\SiteGenerationRun;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteGenerationRunDownloadController extends Controller
{
    public function __invoke(Project $project, Page $page, SiteGenerationRun $siteGenerationRun): BinaryFileResponse
    {
        abort_unless($this->canAccessProject($project), 404);
        abort_unless($page->project_id === $project->id, 404);
        abort_unless($siteGenerationRun->project_id === $project->id, 404);
        abort_unless($siteGenerationRun->source_page_id === $page->id, 404);
        abort_unless($siteGenerationRun->status === 'completed', 404);
        abort_unless(is_string($siteGenerationRun->zip_path) && $siteGenerationRun->zip_path !== '', 404);

        $disk = $siteGenerationRun->zip_disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($siteGenerationRun->zip_path), 404);

        return response()->download(
            Storage::disk($disk)->path($siteGenerationRun->zip_path),
            $siteGenerationRun->zip_filename ?: 'site.zip',
            ['Content-Type' => 'application/zip'],
        );
    }

    private function canAccessProject(Project $project): bool
    {
        $teamId = $project->team_id;

        return is_string($teamId)
            && $teamId !== ''
            && auth()->user()->teams()->whereKey($teamId)->exists();
    }
}
