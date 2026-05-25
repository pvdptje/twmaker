<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Ids\IdGenerator;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'default_team_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function defaultTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'default_team_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function createDefaultTeam(): Team
    {
        if ($this->default_team_id !== null && $this->defaultTeam()->exists()) {
            $team = $this->defaultTeam;

            if (! $team->users()->whereKey($this->id)->exists()) {
                $team->users()->attach($this->id);
            }

            return $team;
        }

        $team = Team::query()->create([
            'id' => app(IdGenerator::class)->team(),
            'name' => "{$this->name}'s Team",
            'owner_user_id' => $this->id,
        ]);

        $team->users()->attach($this->id);

        $this->forceFill(['default_team_id' => $team->id])->save();
        $this->setRelation('defaultTeam', $team);

        return $team;
    }
}
