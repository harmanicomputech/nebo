<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = ['email' => mb_strtolower($request->string('email')), 'password' => $request->string('password')];

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $inactive = User::where('email', $credentials['email'])->where('is_active', false)->exists();
            Audit::record('login_failed', 'Failed sign-in for '.$credentials['email'].($inactive ? ' (deactivated account)' : ''));

            throw ValidationException::withMessages([
                'email' => $inactive
                    ? 'This account has been deactivated. Contact an administrator.'
                    : 'These details do not match an account.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        Audit::record('login', "{$user->name} signed in", $user);

        return redirect()->intended(route('app.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        Audit::record('logout', "{$user->name} signed out", $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
