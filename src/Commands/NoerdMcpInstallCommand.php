<?php

declare(strict_types=1);

namespace NoerdMcp\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Noerd\Commands\Concerns\InstallsNoerdModule;

/**
 * Installs noerd/mcp, a support module without a tenant app: publishes the
 * YAML of its screens, offers the migrations (the OAuth tables included),
 * creates Passport's encryption keys when there are none, adds the admin
 * screens to the setup navigation and "My MCP Connectors" to the profile
 * navigation.
 */
class NoerdMcpInstallCommand extends Command
{
    use InstallsNoerdModule;

    protected $signature = 'noerd:install-mcp';

    protected $description = 'Install noerd/mcp: MCP servers behind OAuth, tenant approval, access log and connectors';

    protected function getModuleName(): string
    {
        return 'Noerd MCP';
    }

    protected function getAppConfigsDir(): ?string
    {
        return $this->getModuleRoot() . '/app-configs/noerd-mcp';
    }

    protected function ensureModuleSetup(): void
    {
        $this->ensurePassportKeys();

        $this->ensureSetupNavigation('Administration', [
            'title' => 'MCP Server Admin',
            'route' => 'noerd-mcp.servers',
            'heroicon' => 'cpu-chip',
        ]);

        $this->ensureSetupNavigation('Administration', [
            'title' => 'MCP Access Log',
            'route' => 'noerd-mcp.requests',
            'heroicon' => 'clipboard-document-list',
        ]);

        $this->ensureProfileNavigation('My Account', [
            'title' => 'My MCP Connectors',
            'route' => 'noerd-mcp.connectors',
            'heroicon' => 'cpu-chip',
        ]);
    }

    /**
     * Passport signs the access tokens with a key pair; without one no MCP
     * client can connect. Keys given through the environment win.
     */
    private function ensurePassportKeys(): void
    {
        if (config('passport.private_key') || file_exists(Passport::keyPath('oauth-private.key'))) {
            return;
        }

        Artisan::call('passport:keys', ['--no-interaction' => true]);
        $this->info('Passport encryption keys created.');
    }
}
