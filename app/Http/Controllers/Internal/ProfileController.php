<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('internal.profile.edit', ['user' => $request->user()->load('roles')]);
    }

    /**
     * Users can change their own name, phone and job title. Email and roles
     * are changed by an administrator.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->string('password')])->save();
        Audit::record('password_changed', "{$user->name} changed their password", $user);

        // Sign out other devices that used the old password.
        auth()->logoutOtherDevices($request->string('password'));

        return back()->with('success', 'Password changed. Other devices have been signed out.');
    }
}
