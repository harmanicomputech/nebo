<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventService;
use App\Support\Permissions\PermissionCatalog;
use App\Support\SampleData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SampleDataTest extends TestCase
{
    private function loadSample(): void
    {
        // Sample data now only exists on installs from before going live; build some the same way.
        SampleData::record(function () {
            $admin = User::create(['name' => 'Ada Okafor', 'email' => 'ada.okafor@nebostage.com', 'password' => SampleData::PASSWORD, 'is_active' => true]);
            $admin->assignRole(PermissionCatalog::SUPER_ADMIN);
            $customer = Customer::create(['name' => 'Funke Adebayo', 'company' => 'Crestview Holdings', 'email' => 'funke.adebayo@crestviewholdings.com.ng', 'source' => 'manual']);
            $start = now()->addDays(30)->startOfDay();
            app(EventService::class)->create($admin, ['name' => 'Marina Trust Town Hall', 'customer_id' => $customer->id, 'event_type' => 'corporate', 'venue' => 'Marina, Lagos',
                'setup_starts_at' => $start->copy()->addHours(8), 'starts_at' => $start->copy()->addHours(12), 'ends_at' => $start->copy()->addHours(20), 'breakdown_ends_at' => $start->copy()->addHours(23)]);
        });
    }

    public function test_an_administrator_clears_sample_data_and_keeps_their_own_records(): void
    {
        $owner = $this->superAdmin();
        $this->loadSample();
        $mine = Customer::create(['name' => 'Ngozi Real', 'email' => 'ngozi@client.ng', 'source' => 'manual']);

        $this->actingAs($owner)->get(route('app.settings.system'))->assertOk()->assertSee('Clear sample data')->assertSee(SampleData::PASSWORD);
        $this->actingAs($owner)->delete(route('app.settings.system.sample.clear'))->assertSessionHas('success');

        $this->assertFalse(SampleData::exists());
        $this->assertSame(0, Event::count());
        $this->assertSame([$owner->id], User::withTrashed()->pluck('id')->all());
        $this->assertTrue($mine->fresh() !== null);
        $this->assertSame(0, DB::table('sequences')->where('key', 'event')->count(), 'reference numbers restart');
        $this->assertDatabaseHas('audit_logs', ['event' => 'sample_data_cleared', 'user_id' => $owner->id]);
        $this->actingAs($owner)->get(route('app.settings.system'))->assertDontSee('Clear sample data');
    }

    public function test_it_will_not_remove_the_last_real_administrator(): void
    {
        $this->loadSample();
        $sampleAdmin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();

        $this->actingAs($sampleAdmin)->delete(route('app.settings.system.sample.clear'))->assertSessionHas('error');
        $this->assertTrue(SampleData::exists());
    }

    public function test_records_of_yours_that_use_sample_data_are_listed_and_only_removed_when_asked(): void
    {
        $owner = $this->superAdmin();
        $this->loadSample();
        $this->actingAs($owner);
        $start = now()->addDays(120)->startOfDay();
        app(EventService::class)->create($owner, ['name' => 'Real Launch', 'customer_id' => Customer::where('company', 'Crestview Holdings')->value('id'), 'event_type' => 'corporate',
            'venue' => 'Eko Hotel, Lagos', 'setup_starts_at' => $start->copy()->addHours(8), 'starts_at' => $start->copy()->addHours(12),
            'ends_at' => $start->copy()->addHours(20), 'breakdown_ends_at' => $start->copy()->addHours(23)]);

        $this->get(route('app.settings.system'))->assertSee('Some records you added use sample data')->assertSee('1 event');
        $this->delete(route('app.settings.system.sample.clear'))->assertSessionHas('error');
        $this->assertTrue(Event::where('name', 'Real Launch')->exists());

        $this->delete(route('app.settings.system.sample.clear'), ['include_mine' => 1])->assertSessionHas('success');
        $this->assertFalse(SampleData::exists());
        $this->assertSame(0, Event::count());
    }

    public function test_no_email_goes_to_sample_people_or_customers(): void
    {
        $this->loadSample();

        Mail::raw('Hello', fn ($m) => $m->to('funke.adebayo@crestviewholdings.com.ng'));
        Mail::raw('Hello', fn ($m) => $m->to('ada.okafor@nebostage.com')->cc('someone@real.ng'));

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame([], $sent[0]->getOriginalMessage()->getTo());
        $this->assertSame('someone@real.ng', $sent[0]->getOriginalMessage()->getCc()[0]->getAddress());
    }
}
