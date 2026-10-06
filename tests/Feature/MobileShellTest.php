<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\DemoUsersSeeder;
use Tests\TestCase;

class MobileShellTest extends TestCase
{
    public function test_phones_get_a_tab_bar_with_the_users_four_main_modules_and_more(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('app.events.index'))->assertOk()
            ->assertSee('aria-label="Main"', false)
            ->assertSeeInOrder(['Home', 'Events', 'Requests', 'Equipment', 'More'])
            ->assertSee('aria-label="All modules"', false)
            ->assertSee('data-filters', false);
    }

    public function test_tabs_follow_what_the_role_can_open(): void
    {
        $this->seed(DemoUsersSeeder::class);
        $crew = User::where('email', 'crew@nebostage.test')->firstOrFail();
        $this->actingAs($crew);

        $this->get(route('app.dashboard'));
        $mobile = Navigation::mobile(Navigation::for($crew));

        $this->assertNotContains('Requests', array_column($mobile['tabs'], 'label'));
        $this->assertContains('Events', array_column($mobile['tabs'], 'label'));
        $this->assertLessThanOrEqual(4, count($mobile['tabs']));
    }

    public function test_detail_screens_get_a_back_arrow_to_their_list(): void
    {
        $admin = $this->superAdmin();
        $other = $this->superAdmin();

        $this->actingAs($admin)->get(route('app.users.index'))->assertDontSee('data-back', false);
        $this->actingAs($admin)->get(route('app.users.edit', $other))->assertOk()
            ->assertSee('href="'.route('app.users.index').'" data-back', false);
    }
}
