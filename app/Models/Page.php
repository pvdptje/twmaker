<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'team_id',
        'name',
        'prompt',
        'html_source',
        'rendered_html_cache',
        'status',
        'last_generation_summary',
    ];

    protected static function booted(): void
    {
        static::creating(function (Page $page): void {
            if ($page->team_id !== null) {
                return;
            }

            if ($page->project_id !== null) {
                $page->team_id = Project::query()->whereKey($page->project_id)->value('team_id');
            }

            if ($page->team_id === null && auth()->check()) {
                $page->team_id = auth()->user()->defaultTeam?->id;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function generationEvents(): HasMany
    {
        return $this->hasMany(GenerationEvent::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }
}
