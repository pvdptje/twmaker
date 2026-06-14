<?php

namespace App\Services\Assets;

use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\ImageAttachments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectAssetLibrary
{
    public const MAX_SELECTED_ASSETS = 1;

    public const MAX_UPLOAD_BYTES = 104_857_600;

    public const MAX_UPLOAD_KILOBYTES = 102_400;

    public function __construct(private readonly IdGenerator $ids) {}

    public function storeUploaded(Project $project, UploadedFile $file): ProjectAsset
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if (! in_array($mime, ImageAttachments::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'assetUpload' => 'Only PNG, JPEG, or WebP images can be added to own assets.',
            ]);
        }

        $bytes = (int) $file->getSize();
        if ($bytes <= 0 || $bytes > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages([
                'assetUpload' => 'Images must be smaller than 100 MB.',
            ]);
        }

        $size = @getimagesize($file->getRealPath());
        if ($size === false) {
            throw ValidationException::withMessages([
                'assetUpload' => 'That image could not be read.',
            ]);
        }

        $id = $this->ids->projectAsset();
        $path = "project-assets/{$project->id}/{$id}.".$this->extensionForMime($mime);

        Storage::disk('public')->putFileAs(
            "project-assets/{$project->id}",
            $file,
            basename($path),
        );

        return ProjectAsset::query()->create([
            'id' => $id,
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'original_name' => $this->safeOriginalName($file),
            'mime_type' => $mime,
            'disk' => 'public',
            'path' => $path,
            'public_url' => $this->publicUrl($project, basename($path)),
            'width' => (int) ($size[0] ?? 0) ?: null,
            'height' => (int) ($size[1] ?? 0) ?: null,
            'bytes' => $bytes,
        ]);
    }

    /**
     * @param  array<int, string>  $assetIds
     * @return array<int, ProjectAsset>
     */
    public function selectedAssetsForPage(Page $page, array $assetIds): array
    {
        $projectId = (string) $page->project_id;
        $teamId = $page->team_id;
        $assetIds = array_values(array_unique(array_filter(
            $assetIds,
            fn (mixed $id): bool => is_string($id) && str_starts_with($id, 'asset_'),
        )));

        if ($assetIds === []) {
            return [];
        }

        $wanted = array_slice($assetIds, 0, self::MAX_SELECTED_ASSETS);
        $positions = array_flip($wanted);

        $assets = ProjectAsset::query()
            ->where('project_id', $projectId)
            ->when(is_string($teamId) && $teamId !== '', fn ($query) => $query->where('team_id', $teamId))
            ->whereIn('id', $wanted)
            ->get()
            ->all();

        usort($assets, fn (ProjectAsset $a, ProjectAsset $b): int => ($positions[$a->id] ?? 0) <=> ($positions[$b->id] ?? 0));

        return $assets;
    }

    /**
     * @param  array<int, ProjectAsset>  $assets
     * @return array<int, array{base64: string, mime_type: string}>
     */
    public function imageAttachments(array $assets): array
    {
        $images = [];

        foreach ($assets as $asset) {
            if (! Storage::disk($asset->disk)->exists($asset->path)) {
                continue;
            }

            $bytes = Storage::disk($asset->disk)->get($asset->path);
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            $images[] = [
                'base64' => base64_encode($bytes),
                'mime_type' => $asset->mime_type,
            ];
        }

        return $images;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function publicUrl(Project $project, string $filename): string
    {
        return "/assets/{$project->id}/{$filename}";
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = trim($file->getClientOriginalName());

        return $name !== '' ? str($name)->limit(255, '')->toString() : 'image';
    }
}
