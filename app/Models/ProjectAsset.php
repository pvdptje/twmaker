<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAsset extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'team_id',
        'original_name',
        'mime_type',
        'disk',
        'path',
        'public_url',
        'width',
        'height',
        'bytes',
    ];

    public function publicHtmlUrl(): string
    {
        $filename = basename((string) $this->path);

        return $filename !== ''
            ? "/assets/{$this->project_id}/{$filename}"
            : $this->public_url;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
