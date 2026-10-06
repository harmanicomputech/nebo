<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until the web installer has finished, every page leads to it (D69). */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installer::active() && ! $request->is('install', 'install/*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
