<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\Ids\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_columns_accept_typed_prefixed_ulids(): void
    {
        $id = app(IdGenerator::class)->project();

        Project::create([
            'id' => $id,
            'name' => 'Acme',
        ]);

        $this->assertSame($id, Project::query()->firstOrFail()->id);
        $this->assertSame('varchar', Schema::getColumnType('projects', 'id'));
    }
}
