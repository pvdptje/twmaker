<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteGenerationRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'team_id',
        'project_id',
        'source_page_id',
        'status',
        'provider',
        'model',
        'planned_pages',
        'generated_page_ids',
        'zip_disk',
        'zip_path',
        'zip_filename',
        'zip_byte_size',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_pages' => 'array',
            'generated_page_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'source_page_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SiteGenerationRunPage::class)->orderBy('sort_order');
    }
}
