<?php

namespace App\Http\Middleware;

use App\Modules\Platform\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        app(CurrentTenant::class)->get();

        return $next($request);
    }
}
