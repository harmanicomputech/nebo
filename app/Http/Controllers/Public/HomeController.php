<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\View\View;

/**
 * Public landing page. Uses settings only; internal data is never loaded
 * from public controllers.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'company' => [
                'name' => Settings::string('company.name'),
                'email' => Settings::string('company.email'),
                'phone' => Settings::string('company.phone'),
                'coverage' => Settings::string('company.coverage'),
            ],
            // Phase 3 moves these to the services table, managed in the console.
            'services' => [
                ['icon' => 'panels-top-left', 'name' => 'Stage & Staging'],
                ['icon' => 'route', 'name' => 'Trussing & Rigging'],
                ['icon' => 'shield', 'name' => 'Barricades'],
                ['icon' => 'lightbulb', 'name' => 'Event Lighting'],
                ['icon' => 'monitor-down', 'name' => 'LED Screens & Displays'],
                ['icon' => 'speaker', 'name' => 'Sound & Audio Production'],
                ['icon' => 'video', 'name' => 'Photography & Videography'],
                ['icon' => 'radio', 'name' => 'Livestreaming'],
                ['icon' => 'sparkles', 'name' => 'Full Event Production'],
            ],
        ]);
    }
}
