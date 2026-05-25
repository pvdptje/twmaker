<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'team_id',
        'name',
        'description',
        'default_design_preferences',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->team_id !== null || ! auth()->check()) {
                return;
            }

            $project->team_id = auth()->user()->defaultTeam?->id;
        });
    }

    protected function casts(): array
    {
        return [
            'default_design_preferences' => 'array',
        ];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
