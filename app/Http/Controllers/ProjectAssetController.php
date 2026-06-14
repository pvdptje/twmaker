<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectAssetController extends Controller
{
    public function __invoke(Project $project, string $filename): BinaryFileResponse
    {
        abort_unless($this->canAccessProject($project), 404);

        $assetId = pathinfo($filename, PATHINFO_FILENAME);
        $asset = ProjectAsset::query()
            ->whereKey($assetId)
            ->where('project_id', $project->id)
            ->when(is_string($project->team_id) && $project->team_id !== '', fn ($query) => $query->where('team_id', $project->team_id))
            ->firstOrFail();

        abort_unless(basename($asset->path) === $filename, 404);
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return response()->file(Storage::disk($asset->disk)->path($asset->path), [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, max-age=31536000',
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
