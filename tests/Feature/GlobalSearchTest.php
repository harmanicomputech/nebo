<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    public function test_results_are_grouped_by_type(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['name' => 'Olumide Rigger']);

        $this->actingAs($admin)->getJson('/app/search?q=Olumide')
            ->assertOk()
            ->assertJsonPath('groups.0.label', 'Users')
            ->assertJsonPath('groups.0.results.0.title', 'Olumide Rigger');

        $this->actingAs($admin)->getJson('/app/search?q=Viewer')->assertJsonFragment(['label' => 'Roles']);
    }

    public function test_results_respect_permissions(): void
    {
        User::factory()->create(['name' => 'Olumide Rigger']);

        $this->actingAs($this->userWithRole('Crew'))->getJson('/app/search?q=Olumide')
            ->assertOk()->assertJsonPath('groups', []);
    }

    public function test_short_queries_return_nothing_and_page_renders(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/app/search?q=a')->assertJsonPath('groups', []);
        $this->actingAs($admin)->get('/app/search?q=a')->assertOk()->assertSee('Type at least two characters');
    }

    public function test_like_wildcards_are_treated_literally(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($admin)->getJson('/app/search?q=%25%25')->assertJsonPath('groups', []);
    }
}
