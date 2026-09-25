<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $request->user()?->is_admin) {
            abort(403, 'Доступ разрешён только администратору');
        }

        return $next($request);
    }
}
