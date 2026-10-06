<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\SettingsRequest;
use App\Support\Audit\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('internal.settings.edit', [
            'values' => collect(SettingsRequest::KEYS)->mapWithKeys(fn (string $key) => [$key => Settings::string($key)])->all(),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $old = [];
        $new = [];

        foreach (SettingsRequest::KEYS as $key) {
            $value = (string) ($request->validated(str_replace('.', '_', $key)) ?? '');

            if ($value !== Settings::string($key)) {
                $old[$key] = Settings::string($key);
                $new[$key] = $value;
                Settings::set($key, $value);
            }
        }

        if ($new !== []) {
            Audit::record('settings_changed', 'Settings changed: '.implode(', ', array_keys($new)), null, $old, $new);
        }

        return back()->with('success', $new === [] ? 'Nothing changed.' : 'Settings saved.');
    }
}
