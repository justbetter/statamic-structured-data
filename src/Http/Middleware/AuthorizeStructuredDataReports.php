<?php

namespace Justbetter\StatamicStructuredData\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeStructuredDataReports
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $permission = config()->string('justbetter.structured-data.reports.permissions.view');

        abort_unless($user instanceof Authorizable && $user->can($permission), 403);

        return $next($request);
    }
}
