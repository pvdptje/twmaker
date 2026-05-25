<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteGenerationRunPage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'site_generation_run_id',
        'target_page_id',
        'sort_order',
        'name',
        'slug',
        'brief',
        'source',
        'status',
        'error_message',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SiteGenerationRun::class, 'site_generation_run_id');
    }

    public function targetPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'target_page_id');
    }
}
