<?php

namespace App\Services\Llm;

use App\Models\Page;
use App\Models\Team;
use App\Models\TeamProviderCredential;
use App\Models\User;
use App\Services\Ids\IdGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TeamProviderCredentials
{
    public function __construct(
        private readonly LlmRegistry $registry,
        private readonly IdGenerator $ids,
    ) {}

    public function currentTeam(): ?Team
    {
        $user = Auth::user();

        return $user instanceof User ? $user->createDefaultTeam() : null;
    }

    public function teamForPage(Page $page): ?Team
    {
        $teamId = $page->team_id ?: $page->project?->team_id;

        if (is_string($teamId) && $teamId !== '') {
            return Team::query()->find($teamId);
        }

        return $this->currentTeam();
    }

    /**
     * @return Collection<int, TeamProviderCredential>
     */
    public function credentials(?Team $team = null): Collection
    {
        $team ??= $this->currentTeam();

        if (! $team instanceof Team) {
            return collect();
        }

        return TeamProviderCredential::query()
            ->where('team_id', $team->id)
            ->orderBy('provider')
            ->get();
    }

    public function save(Team $team, string $provider, ?string $apiKey): TeamProviderCredential
    {
        $apiKey = $apiKey !== null ? trim($apiKey) : null;

        return TeamProviderCredential::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'provider' => $provider,
            ],
            [
                'id' => $this->existingId($team, $provider) ?? $this->ids->teamProviderCredential(),
                'api_key' => $apiKey !== '' ? $apiKey : null,
            ],
        );
    }

    public function delete(Team $team, string $provider): void
    {
        TeamProviderCredential::query()
            ->where('team_id', $team->id)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * @return array<int, array{id: string, label: string, driver: string, models_refreshed_at: mixed}>
     */
    public function configuredProviderOptions(?Team $team = null): array
    {
        $providers = $this->providerOptionsById();
        $configured = $this->credentials($team)
            ->pluck('provider')
            ->filter(fn (mixed $provider): bool => is_string($provider) && isset($providers[$provider]))
            ->values();

        return $configured
            ->map(fn (string $provider): array => $providers[$provider])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string, driver: string, models_refreshed_at: mixed}>
     */
    public function availableProviderOptions(?Team $team = null): array
    {
        $configured = $this->credentials($team)->pluck('provider')->all();

        return collect($this->registry->implementedProviders())
            ->reject(fn (array $provider): bool => in_array((string) $provider['id'], $configured, true))
            ->values()
            ->all();
    }

    public function apiKey(?Team $team, string $provider): ?string
    {
        if ($team instanceof Team) {
            $credential = TeamProviderCredential::query()
                ->where('team_id', $team->id)
                ->where('provider', $provider)
                ->first();

            if ($credential instanceof TeamProviderCredential) {
                $apiKey = trim((string) $credential->api_key);

                if ($apiKey !== '') {
                    return $apiKey;
                }
            }
        }

        $apiKey = trim((string) config("llm.providers.{$provider}.api_key"));

        return $apiKey !== '' ? $apiKey : null;
    }

    public function canFetchModels(?Team $team, string $provider): bool
    {
        if (! (bool) config("llm.providers.{$provider}.requires_api_key", true)) {
            return true;
        }

        return $this->apiKey($team, $provider) !== null;
    }

    /**
     * @return array<string, array{id: string, label: string, driver: string, models_refreshed_at: mixed}>
     */
    private function providerOptionsById(): array
    {
        return collect($this->registry->implementedProviders())
            ->mapWithKeys(fn (array $provider): array => [(string) $provider['id'] => $provider])
            ->all();
    }

    private function existingId(Team $team, string $provider): ?string
    {
        $id = TeamProviderCredential::query()
            ->where('team_id', $team->id)
            ->where('provider', $provider)
            ->value('id');

        return is_string($id) ? $id : null;
    }
}
