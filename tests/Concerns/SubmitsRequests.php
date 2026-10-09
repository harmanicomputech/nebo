<?php

namespace Tests\Concerns;

use App\Models\Service;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait SubmitsRequests
{
    /**
     * @return array<string, mixed>
     */
    protected function requestPayload(array $overrides = []): array
    {
        $tz = config('nebo.display_timezone');

        return array_merge([
            'event_name' => 'Annual Gala',
            'event_type' => 'corporate',
            'event_date' => now($tz)->addDays(30)->toDateString(),
            'venue' => 'Eko Hotel, Lagos',
            'phone' => '0803 123 4567',
            'email' => 'Jane@Example.com',
            'services' => [(string) Service::where('slug', 'event-lighting')->value('id'), (string) Service::where('slug', 'stage-rigging')->value('id')],
            'requirements' => 'Stage 12m x 8m, two LED screens, wash lighting, PA for 500 guests.',
            'duration_days' => 1,
            'setup_at' => now($tz)->addDays(29)->setTime(10, 0)->format('Y-m-d\TH:i'),
            'has_existing_design' => 'no',
            'budget_range' => '3m_5m',
            'contact_person' => 'Jane Okafor',
            'company' => 'Acme Nigeria',
            'submission_key' => (string) Str::uuid(),
            'form_started' => Crypt::encryptString((string) now()->subMinute()->timestamp),
            'website' => '',
        ], $overrides);
    }

    protected function submitRequest(array $overrides = []): TestResponse
    {
        return $this->post('/request', $this->requestPayload($overrides));
    }
}
