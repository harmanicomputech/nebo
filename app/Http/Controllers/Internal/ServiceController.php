<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The services catalogue shown on the public request form. */
class ServiceController extends Controller
{
    public function index(): View
    {
        return view('internal.settings.services', [
            'services' => Service::query()->withCount('requests')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = Str::slug($data['name']) ?: 'service';
        for ($i = 2, $base = $slug; Service::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        Service::create(array_merge($data, ['slug' => $slug, 'sort_order' => $data['sort_order'] ?? 100]));

        return back()->with('success', "{$data['name']} added.");
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $this->validated($request, $service);
        $data['sort_order'] ??= $service->sort_order;
        $service->update($data);

        return back()->with('success', 'Service saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($service)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_public' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
