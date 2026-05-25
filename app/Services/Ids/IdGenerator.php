<?php

namespace App\Services\Ids;

use Illuminate\Support\Str;
use InvalidArgumentException;

class IdGenerator
{
    private const PREFIXES = [
        'project' => 'proj_',
        'team' => 'team_',
        'page' => 'page_',
        'page_version' => 'ver_',
        'section' => 'sec_',
        'node' => 'node_',
        'element' => 'elem_',
        'element_instance' => 'inst_',
        'generation_event' => 'evt_',
        'team_provider_credential' => 'cred_',
    ];

    public function make(string $entity): string
    {
        if (! array_key_exists($entity, self::PREFIXES)) {
            throw new InvalidArgumentException("Unknown ID entity [{$entity}].");
        }

        return self::PREFIXES[$entity].strtolower((string) Str::ulid());
    }

    public function project(): string
    {
        return $this->make('project');
    }

    public function team(): string
    {
        return $this->make('team');
    }

    public function page(): string
    {
        return $this->make('page');
    }

    public function pageVersion(): string
    {
        return $this->make('page_version');
    }

    public function section(): string
    {
        return $this->make('section');
    }

    public function node(): string
    {
        return $this->make('node');
    }

    public function element(): string
    {
        return $this->make('element');
    }

    public function elementInstance(): string
    {
        return $this->make('element_instance');
    }

    public function generationEvent(): string
    {
        return $this->make('generation_event');
    }

    public function teamProviderCredential(): string
    {
        return $this->make('team_provider_credential');
    }
}
