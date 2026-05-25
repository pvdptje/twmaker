<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Services\Ids\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_users_can_register_with_a_default_team(): void
    {
        $this->post('/register', [
            'name' => 'Pat Builder',
            'email' => 'pat@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertRedirect(route('projects.index'));

        $user = User::query()->where('email', 'pat@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->default_team_id);
        $this->assertDatabaseHas('teams', [
            'id' => $user->default_team_id,
            'owner_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $user->default_team_id,
            'user_id' => $user->id,
        ]);
    }

    public function test_registration_requires_a_strong_password(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Pat Builder',
            'email' => 'pat@example.com',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'pat@example.com']);
    }

    public function test_users_can_log_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'pat@example.com',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $this->post('/login', [
            'email' => 'pat@example.com',
            'password' => 'StrongPass123!',
        ])->assertRedirect(route('projects.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->default_team_id);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_team_members_can_access_team_projects_and_pages(): void
    {
        $owner = User::factory()->create();
        $team = $owner->createDefaultTeam();
        $member = User::factory()->create();
        $team->users()->attach($member->id);

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'team_id' => $team->id,
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'draft',
        ]);

        $this->actingAs($member)
            ->get(route('builder.workspace', [$project, $page]))
            ->assertOk();
    }

    public function test_users_outside_a_team_cannot_access_team_projects_or_pages(): void
    {
        $owner = User::factory()->create();
        $team = $owner->createDefaultTeam();
        $outsider = User::factory()->create();
        $outsider->createDefaultTeam();

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'team_id' => $team->id,
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'draft',
        ]);

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->get(route('builder.workspace', [$project, $page]))
            ->assertNotFound();
    }
}
