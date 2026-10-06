<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Lookups;
use App\Support\Navigation;
use App\Support\SampleData;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Option lists memoised per request (reset between requests and jobs).
        $this->app->scoped(Lookups::class);
    }

    public function boot(): void
    {
        // Super Administrator has every permission (module.action), including ones
        // added later. Policy methods (update, delete, …) still run for them, so
        // structural rules such as "system roles cannot be deleted" hold for everyone.
        Gate::before(fn (User $user, string $ability) => str_contains($ability, '.') && $user->isSuperAdmin() ? true : null);

        // Behind a proxy or shared-hosting front end, generate https:// links
        // whenever the configured URL is https (D67).
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        Paginator::defaultView('components.ui.pagination');

        // Sample data (D71): note what the sample loader creates, and never
        // email a sample person or customer (their addresses look real).
        Event::listen('eloquent.created: *', fn (string $event, array $payload) => SampleData::capture($payload[0]));
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $sample = SampleData::emails();
            if (! $sample || ! $event->message instanceof Email) {
                return null;
            }
            $keep = fn (array $list) => array_values(array_filter($list, fn (Address $a) => ! isset($sample[mb_strtolower($a->getAddress())])));
            $message = $event->message;
            $message->to(...$keep($message->getTo()))->cc(...$keep($message->getCc()))->bcc(...$keep($message->getBcc()));

            return $message->getTo() || $message->getCc() || $message->getBcc() ? null : false;
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('nebo.auth.max_login_attempts'))
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Data for the internal shell: sidebar, notification bell.
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            $navigation = Navigation::for($user);
            $view->with([
                'navigation' => $navigation,
                'mobile' => Navigation::mobile($navigation),
                'unreadCount' => $user->unreadNotifications()->count(),
                'recentNotifications' => $user->notifications()->latest()->limit(6)->get(),
            ]);
        });

        // Public request form: generous for real people, hard on scripts.
        RateLimiter::for('public-request', fn (Request $request) => [
            Limit::perMinute(5)->by('req-m:'.$request->ip()),
            Limit::perHour(20)->by('req-h:'.$request->ip()),
        ]);

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
