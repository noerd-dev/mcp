<?php

declare(strict_types=1);

namespace NoerdMcp\Providers;

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;
use Noerd\Providers\NoerdModuleServiceProvider;
use Noerd\Services\ProfileNavigationRegistry;
use Noerd\Support\ComponentAccessGuard;
use NoerdMcp\Commands\NoerdMcpInstallCommand;
use NoerdMcp\Commands\NoerdMcpUpdateCommand;
use NoerdMcp\Commands\PruneMcpRequestsCommand;
use NoerdMcp\Models\McpUser;
use NoerdMcp\Support\McpRoutes;
use NoerdMcp\Support\McpServerRegistry;

/**
 * Serves every registered MCP server of the installation behind Passport
 * OAuth for noerd users: the `noerd-mcp` token guard, the OAuth discovery and
 * client registration routes of laravel/mcp, the consent screen, and one route
 * `/mcp/{key}` per server with authentication, access log and the tenant
 * checks. Hosts configure nothing of this by hand.
 */
class NoerdMcpServiceProvider extends NoerdModuleServiceProvider
{
    public const string GUARD = 'noerd-mcp';

    public const string USER_PROVIDER = 'noerd_mcp_users';

    /** @var list<string> */
    public const array ADMIN_COMPONENTS = [
        'noerd-mcp::mcp-servers-page',
        'noerd-mcp::mcp-requests-list',
        'noerd-mcp::mcp-request-detail',
    ];

    protected string $moduleKey = 'noerd-mcp';

    protected array $commands = [
        NoerdMcpInstallCommand::class,
        NoerdMcpUpdateCommand::class,
        PruneMcpRequestsCommand::class,
    ];

    protected array $configs = ['noerd-mcp'];

    protected function registerModule(): void
    {
        $this->app->singleton(McpServerRegistry::class);

        // The token guard of the MCP routes, on the noerd users. Set at
        // runtime so the host's config/auth.php stays untouched; a host that
        // defines the guard itself keeps its own.
        config([
            'auth.guards.' . self::GUARD => config('auth.guards.' . self::GUARD) ?? [
                'driver' => 'passport',
                'provider' => self::USER_PROVIDER,
            ],
            'auth.providers.' . self::USER_PROVIDER => config('auth.providers.' . self::USER_PROVIDER) ?? [
                'driver' => 'eloquent',
                'model' => McpUser::class,
            ],
        ]);

        // The person approves a client on Passport's consent screen while
        // signed in to the application — on the noerd guard.
        if (config('passport.guard') === null) {
            config(['passport.guard' => 'noerd']);
        }
    }

    protected function bootModule(): void
    {
        ComponentAccessGuard::registerAdminComponents(self::ADMIN_COMPONENTS);

        // Every person's own page in the profile area of the core.
        $this->app->make(ProfileNavigationRegistry::class)
            ->register('My MCP Connectors', 'noerd-mcp.connectors', 'cpu-chip', 50);

        // The consent screen an MCP client (Claude) shows after the login.
        Passport::authorizationView(fn(array $parameters) => view('mcp::authorize', $parameters));

        // Every module registers its server in its own boot(): the routes are
        // added once all providers booted.
        $this->app->booted(function (): void {
            if ($this->app->routesAreCached()) {
                return;
            }

            $this->registerMcpRoutes();
        });
    }

    private function registerMcpRoutes(): void
    {
        if (config('noerd-mcp.oauth.routes', true)) {
            Route::middleware('throttle:' . config('noerd-mcp.oauth.throttle', '10,1'))
                ->group(fn() => Mcp::oauthRoutes());
        }

        foreach ($this->app->make(McpServerRegistry::class)->all() as $definition) {
            McpRoutes::server($definition);
        }
    }
}
