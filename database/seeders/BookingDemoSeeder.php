<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\EventRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\RequestIntake;
use App\Services\Booking\RequestWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Demo requests through the real intake and workflow (never production). */
class BookingDemoSeeder extends Seeder
{
    public function run(RequestIntake $intake, RequestWorkflow $workflow): void
    {
        if (EventRequest::where('event_name', 'Annual Leadership Conference')->exists()) {
            return;
        }

        $admin = User::where('email', 'ada.okafor@nebostage.com')->first();
        $pm = User::where('email', 'ibrahim.musa@nebostage.com')->first() ?? $admin;
        $svc = fn (string ...$slugs) => Service::whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $demo = [
            ['Annual Leadership Conference', 'conference', 24, 'Eko Hotel, Victoria Island, Lagos', 'Funke Adebayo', 'Crestview Holdings', 'funke.adebayo@crestviewholdings.com.ng', '08034417256', ['stage-staging', 'event-lighting', 'led-screens-displays', 'sound-audio-production'], '5m_10m', [RequestStatus::UnderReview, RequestStatus::QuotationPreparation, RequestStatus::QuotationSent]],
            ['Harvest Praise Concert', 'church', 40, 'Old Parade Ground, Abuja', 'Pastor Daniel Okon', 'Grace Assembly', 'events@graceassemblyabuja.org', '08097315520', ['stage-staging', 'trussing-rigging', 'event-lighting', 'sound-audio-production', 'livestreaming'], '10m_plus', [RequestStatus::Contacted, RequestStatus::SiteAssessment]],
            ['Chidi & Amaka Wedding', 'wedding', 12, 'Landmark Event Centre, Lagos', 'Chidi Obi', null, 'chidiobi.events@gmail.com', '07062918840', ['event-lighting', 'led-screens-displays', 'photography-videography'], '3m_5m', []],
            ['Product Launch: Volt Phone X', 'product_launch', 18, 'Transcorp Hilton, Abuja', 'Ngozi Eze', 'Volt Electronics', 'ngozi.eze@voltelectronics.ng', '08152084417', ['full-event-production'], '10m_plus', [RequestStatus::UnderReview, RequestStatus::QuotationPreparation, RequestStatus::QuotationSent, RequestStatus::Confirmed, RequestStatus::Approved]],
            ['Port Harcourt Food Festival', 'festival', 60, 'Liberation Stadium, Port Harcourt', 'Tamuno Briggs', 'PH Events Co', 'tamuno@phevents.com.ng', '08036621490', ['stage-staging', 'barricades', 'sound-audio-production'], 'discuss', []],
        ];

        foreach ($demo as [$name, $type, $days, $venue, $contact, $company, $email, $phone, $services, $budget, $path]) {
            $request = $intake->submit([
                'event_name' => $name, 'event_type' => $type, 'event_date' => now()->addDays($days)->toDateString(),
                'venue' => $venue, 'contact_person' => $contact, 'company' => $company, 'email' => $email, 'phone' => $phone,
                'services' => $svc(...$services), 'duration_days' => 1, 'setup_at' => now()->addDays($days - 1)->setTime(8, 0)->toDateTimeString(),
                'has_existing_design' => false, 'budget_range' => $budget, 'submission_key' => (string) Str::uuid(),
                'requirements' => 'Stage approx. 12m x 8m, two LED side screens, full wash and spot lighting, PA for about 800 guests, three cameras.',
            ]);

            if ($path) {
                $workflow->assign($admin, $request, $pm);
                foreach ($path as $status) {
                    $workflow->transition($pm, $request->fresh(), $status, 'Confirmed with the client by phone.');
                }
            }
        }
    }
}
