<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Same answer whether or not the account exists, so emails cannot be probed.
        $user = User::where('email', mb_strtolower($request->string('email')))->where('is_active', true)->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If that email belongs to an active account, a reset link is on its way.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->string('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                Audit::record('password_reset', "{$user->name} reset their password by email", $user);
                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been reset. Sign in with the new password.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
