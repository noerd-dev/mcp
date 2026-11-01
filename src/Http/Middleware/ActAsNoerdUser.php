<?php

declare(strict_types=1);

namespace NoerdMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Support\McpServerRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs an MCP request as the noerd user behind the token, for ONE registered
 * server (the middleware parameter is its key): the same person becomes the
 * noerd user in the tenant they selected and in their language — tenant
 * scope, permissions, translations and audit trail are theirs. The request is
 * refused unless that tenant runs one of the server's apps, the person may use
 * it and a tenant admin allowed the server. Goes after
 * {@see AuthenticateMcpUser}.
 */
class ActAsNoerdUser
{
    public const string TENANT_ATTRIBUTE = 'noerd-mcp.tenant_id';

    public const string USER_ATTRIBUTE = 'noerd-mcp.user_id';

    public function __construct(private readonly McpServerRegistry $registry) {}

    public function handle(Request $request, Closure $next, string $server): Response
    {
        $definition = $this->registry->find($server);
        $authenticated = $request->user();
        $user = $authenticated === null ? null : NoerdUser::query()->find($authenticated->getAuthIdentifier());

        if ($definition === null) {
            return response()->json(['message' => 'Unknown MCP server.'], 404);
        }

        if (! $user instanceof NoerdUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set(self::USER_ATTRIBUTE, $user->id);

        // Read the persisted selection: `selected_tenant_id` resolves from the
        // session, which an MCP request does not have.
        $tenantId = (int) $user->userSetting()->value('selected_tenant_id');

        if ($tenantId === 0 || ! $user->canAccessTenant($tenantId)) {
            return response()->json(['message' => __('Select a tenant in the application first.')], 403);
        }

        $request->attributes->set(self::TENANT_ATTRIBUTE, $tenantId);

        NoerdAuth::guard()->setUser($user);
        App::setLocale($user->locale);

        // The request carries no session; the tenant the app checks read from
        // there is the one the person selected.
        session()->put('noerd.selected_tenant_id', $tenantId);
        TenantHelper::clearCache();

        if (! AccessHelper::canUseApp(...$definition->apps)) {
            return response()->json(['message' => __('You are not allowed to use :server.', ['server' => __($definition->label)])], 403);
        }

        if (! McpServerSetting::isEnabled($tenantId, $definition->key)) {
            return response()->json([
                'message' => __('An administrator has not allowed the MCP server :server for this company.', ['server' => __($definition->label)]),
            ], 403);
        }

        return $next($request);
    }
}
