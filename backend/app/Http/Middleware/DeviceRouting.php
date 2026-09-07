<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceRouting
{
    /**
     * Redirect mobile User-Agents to the Expo web app so phones get the
     * mobile UI and desktops get the Blade UI. API routes are skipped —
     * both apps share the same API.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // API routes are shared by both apps — never redirect them.
        if ($request->is('api/*')) {
            return $next($request);
        }

        $ua = (string) $request->header('User-Agent');

        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|Opera Mini|IEMobile|Windows Phone/i', $ua)) {
            return redirect()->away(
                config('app.expo_web_url', env('EXPO_WEB_URL', 'http://localhost:19006'))
            );
        }

        return $next($request);
    }
}
