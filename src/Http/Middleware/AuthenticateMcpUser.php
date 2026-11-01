<?php

declare(strict_types=1);

namespace NoerdMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use NoerdMcp\Providers\NoerdMcpServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an MCP request by its OAuth access token (Passport guard
 * `noerd-mcp`). Without a valid token it answers 401 as JSON — never a
 * redirect to the login page — and laravel/mcp adds the `WWW-Authenticate`
 * header that sends an MCP client into the OAuth flow.
 */
class AuthenticateMcpUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard(NoerdMcpServiceProvider::GUARD);

        if (! $guard->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn() => $guard->user());

        return $next($request);
    }
}
