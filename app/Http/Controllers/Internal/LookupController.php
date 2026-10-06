<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Option lists. Keys are permanent (records store them), so editing changes
 * the label, order and active state only.
 */
class LookupController extends Controller
{
    public function index(Request $request, Lookups $lookups, ?string $group = null): View
    {
        $group ??= array_key_first(Lookups::GROUPS);
        abort_unless(isset(Lookups::GROUPS[$group]), 404);

        return view('internal.settings.options', [
            'group' => $group,
            'groups' => Lookups::GROUPS,
            'items' => $lookups->all($group),
        ]);
    }

    public function store(Request $request, string $group): RedirectResponse
    {
        abort_unless(isset(Lookups::GROUPS[$group]), 404);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        $key = Str::snake(Str::ascii($data['label'])) ?: 'option';
        $base = $key;
        for ($i = 2; Lookup::where(['group' => $group, 'key' => $key])->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        Lookup::create(['group' => $group, 'key' => Str::limit($key, 50, ''), 'label' => $data['label'], 'sort_order' => $data['sort_order'] ?? 100, 'is_active' => true, 'is_system' => false]);

        return back()->with('success', "{$data['label']} added.");
    }

    public function update(Request $request, Lookup $lookup): RedirectResponse
    {
        abort_unless(isset(Lookups::GROUPS[$lookup->group]), 404);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100', Rule::unique('lookups', 'label')->where('group', $lookup->group)->ignore($lookup)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['sort_order'] ??= $lookup->sort_order;
        $lookup->update($data);

        return back()->with('success', 'Option saved.');
    }
}
