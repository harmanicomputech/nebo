<x-layouts.app :title="$staff->exists ? 'Edit '.$staff->name : 'Add person'">
    <x-ui.page-header :title="$staff->exists ? 'Edit '.$staff->name : 'Add person'" :breadcrumbs="['Administration' => null, 'Staff & crew' => route('app.staff.index'), ($staff->exists ? $staff->name : 'Add') => null]" />
    <form method="POST" action="{{ $staff->exists ? route('app.staff.update', $staff) : route('app.staff.store') }}" class="max-w-2xl" data-once>
        @csrf @if ($staff->exists) @method('PUT') @endif
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Full name" name="name" :value="$staff->name" required class="sm:col-span-2" />
                <x-ui.select label="Role" name="role" :options="$roles" :value="$staff->role" placeholder="Choose" required />
                <x-ui.input label="Phone" name="phone" type="tel" :value="$staff->phone" />
                <x-ui.input label="Email" name="email" type="email" :value="$staff->email" />
                <x-ui.select label="Login account" name="user_id" :options="$users" :value="$staff->user_id" placeholder="No account" hint="Link a user so they see their events and get notified." />
                <x-ui.textarea label="Notes" name="notes" :value="$staff->notes" rows="2" class="sm:col-span-2" />
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active)) class="size-4 rounded border-ink-300 text-brand-600">Active (can be booked on events)</label>
            </div>
        </x-ui.card>
        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="$staff->exists ? route('app.staff.show', $staff) : route('app.staff.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save</x-ui.button>
        </div>
    </form>
</x-layouts.app>
