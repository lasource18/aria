<?php

namespace App\Http\Middleware;

use App\Models\Org;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOrgMember
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->route('org');

        if (! $orgId) {
            abort(404, 'Organization not found');
        }

        $org = Org::find($orgId);

        if (! $org) {
            abort(404, 'Organization not found');
        }

        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if (! $org->hasMember($user)) {
            abort(403, 'You are not a member of this organization');
        }

        return $next($request);
    }
}
