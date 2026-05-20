<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'default_design_preferences',
    ];

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

    public function reusableElements(): HasMany
    {
        return $this->hasMany(ReusableElement::class);
    }
}
