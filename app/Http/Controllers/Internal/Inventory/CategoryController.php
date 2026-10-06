<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Equipment categories and subcategories (one level). */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('internal.inventory.setup.categories', [
            'categories' => EquipmentCategory::query()->whereNull('parent_id')->withTrashed()
                ->with(['children' => fn ($q) => $q->withTrashed()->withCount('equipment')])
                ->withCount('equipment')->orderBy('sort_order')->orderBy('name')->get(),
            'parents' => EquipmentCategory::query()->whereNull('parent_id')->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $data['parent_id'] ?? null);

        $category = EquipmentCategory::create($data + ['is_active' => true]);

        return back()->with('success', "Category {$category->name} added.");
    }

    public function update(Request $request, EquipmentCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        if (($data['parent_id'] ?? null) && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'A category with subcategories cannot become a subcategory.']);
        }

        $category->update($data);

        return back()->with('success', 'Category saved.');
    }

    /** Archive: hidden from new equipment; existing equipment keeps it. */
    public function destroy(EquipmentCategory $category): RedirectResponse
    {
        if ($category->children()->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['category' => 'Archive its subcategories first.']);
        }

        $category->update(['is_active' => false]);
        $category->delete();

        return back()->with('success', "Category {$category->name} archived. Equipment already in it keeps it.");
    }

    public function restore(int $id): RedirectResponse
    {
        $category = EquipmentCategory::onlyTrashed()->findOrFail($id);
        $category->restore();
        $category->update(['is_active' => true]);

        return back()->with('success', "Category {$category->name} restored.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EquipmentCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('equipment_categories', 'name')->where('parent_id', $request->input('parent_id'))->ignore($category)],
            'parent_id' => ['nullable', 'integer', Rule::exists('equipment_categories', 'id')->whereNull('parent_id')->whereNull('deleted_at'), Rule::notIn([$category?->id])],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['sort_order'] ??= 0;

        return $data;
    }

    private function uniqueSlug(string $name, ?int $parentId): string
    {
        $base = Str::slug(($parentId ? EquipmentCategory::find($parentId)?->name.' ' : '').$name) ?: 'category';
        $slug = $base;

        for ($i = 2; EquipmentCategory::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
