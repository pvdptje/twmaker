<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageScreenshotController extends Controller
{
    public function __invoke(Project $project, Page $page): BinaryFileResponse
    {
        abort_unless($page->project_id === $project->id, 404);
        abort_unless($this->canAccessProject($project), 404);
        abort_unless($page->team_id === $project->team_id, 404);

        $disk = (string) config('filesystems.default', 'local');
        $path = (string) ($page->screenshot_path ?? '');

        abort_if($path === '', 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return response()->file(Storage::disk($disk)->path($path), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function canAccessProject(Project $project): bool
    {
        $teamId = $project->team_id;

        return is_string($teamId)
            && $teamId !== ''
            && auth()->user()->teams()->whereKey($teamId)->exists();
    }
}
