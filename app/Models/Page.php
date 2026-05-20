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
        'name',
        'prompt',
        'document_json',
        'rendered_html_cache',
        'status',
        'last_generation_summary',
    ];

    protected function casts(): array
    {
        return [
            'document_json' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
