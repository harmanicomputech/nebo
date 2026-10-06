<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Enums\AssetStatusGroup;
use App\Http\Controllers\Controller;
use App\Models\AssetStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Asset statuses. Admins can add statuses and rename any of them. The
 * behaviour of system statuses (group, allocatable, manual) is fixed because
 * the allocation engine relies on it; custom statuses are always manual.
 */
class AssetStatusController extends Controller
{
    public function index(): View
    {
        return view('internal.inventory.setup.statuses', [
            'statuses' => AssetStatus::query()->withCount('assets')->orderBy('sort_order')->orderBy('label')->get(),
            'groups' => collect(AssetStatusGroup::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all(),
            'tones' => array_combine(AssetStatus::TONES, array_map('ucfirst', AssetStatus::TONES)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'group' => ['required', Rule::enum(AssetStatusGroup::class)],
            'is_allocatable' => ['sometimes', 'boolean'],
        ]);

        $code = Str::snake(Str::ascii($data['label']));
        $base = $code;
        for ($i = 2; AssetStatus::where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        $data['sort_order'] ??= 100;

        // array_merge, not +: the computed flags must override anything submitted.
        $status = AssetStatus::create(array_merge($data, [
            'code' => $code,
            // Only "available"-group statuses may be allocatable; custom statuses are always manual.
            'is_allocatable' => $data['group'] === AssetStatusGroup::Available->value && $request->boolean('is_allocatable'),
            'is_manual' => true,
            'is_system' => false,
            'is_active' => true,
        ]));

        return back()->with('success', "Status {$status->label} added.");
    }

    public function update(Request $request, AssetStatus $status): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['is_active' => ['sometimes', 'boolean']]);

        // System statuses keep their behaviour and can't be switched off.
        if ($status->is_system) {
            unset($data['is_active']);
        } elseif ($request->has('is_active') && ! $request->boolean('is_active') && $status->assets()->exists()) {
            return back()->withErrors(['status' => "Assets still have the status {$status->label}. Change them first."]);
        }

        $data['sort_order'] ??= $status->sort_order;
        $status->update($data);

        return back()->with('success', 'Status saved.');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'tone' => ['required', Rule::in(AssetStatus::TONES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
