<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Support\Settings;
use Illuminate\View\View;

/**
 * Public landing page. Loads only public data (settings and offered
 * services); internal data is never loaded from public controllers.
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
            'services' => Service::query()->offered()->get(['name', 'icon', 'description']),
        ]);
    }
}
