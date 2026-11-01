<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Services\McpServerAccess;
use NoerdMcp\Support\McpServerRegistry;

/**
 * Tenant admins decide which registered MCP servers the company's people may
 * connect to (e.g. from Claude). A server stays off until an admin allows it;
 * one whose app the tenant does not run cannot be allowed. Admins only —
 * registered with the ComponentAccessGuard and checked in every action.
 */
new class extends Component {
    public bool $saved = false;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function toggle(string $key, McpServerRegistry $registry, McpServerAccess $access): void
    {
        $this->authorizeAdmin();

        $definition = $registry->find($key);
        abort_if($definition === null, 404);

        $tenantId = (int) TenantHelper::currentTenantId();
        $enabled = $access->enabled($tenantId, $definition);

        // Switching off is always possible; switching on needs the app.
        abort_if(! $enabled && ! $access->appUsable($definition), 403);

        McpServerSetting::set($tenantId, $key, ! $enabled);

        unset($this->servers);
        $this->saved = true;
    }

    /**
     * @return list<array{key: string, label: string, description: ?string, url: string, app_usable: bool, enabled: bool}>
     */
    #[Computed]
    public function servers(): array
    {
        $access = app(McpServerAccess::class);
        $tenantId = (int) TenantHelper::currentTenantId();

        return array_values(array_map(fn($definition): array => [
            'key' => $definition->key,
            'label' => __($definition->label),
            'description' => $definition->description === null ? null : __($definition->description),
            'url' => $definition->url(),
            'app_usable' => $access->appUsable($definition),
            'enabled' => $access->enabled($tenantId, $definition),
        ], app(McpServerRegistry::class)->all()));
    }

    private function authorizeAdmin(): void
    {
        abort_unless((bool) NoerdAuth::user()?->isAdmin() && TenantHelper::currentTenantId() !== null, 403);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('MCP Servers') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :showBlock="false">
        <x-slot:tab1>
            <div class="max-w-3xl space-y-6">
                <p class="text-sm text-zinc-600">
                    {{ __('Allow the MCP servers the people of this company may connect to from an AI assistant such as Claude. A server stays off until you allow it. Every access is recorded in the MCP access log.') }}
                </p>

                <ul class="divide-y divide-zinc-100 rounded-md border border-zinc-200">
                    @forelse ($this->servers as $server)
                        <li wire:key="server-{{ $server['key'] }}" class="flex items-start justify-between gap-4 px-4 py-3">
                            <div class="min-w-0">
                                <div class="font-medium text-zinc-900">{{ $server['label'] }}</div>
                                @if ($server['description'])
                                    <div class="mt-0.5 text-sm text-zinc-600">{{ $server['description'] }}</div>
                                @endif
                                <div class="mt-1 truncate font-mono text-xs text-zinc-500">{{ $server['url'] }}</div>
                                @unless ($server['app_usable'])
                                    <div class="mt-1 text-xs text-amber-700">{{ __('The company does not use the app this server belongs to.') }}</div>
                                @endunless
                            </div>

                            <x-noerd::checkbox
                                id="mcp-server-{{ $server['key'] }}"
                                :checked="$server['enabled']"
                                :disabled="! $server['enabled'] && ! $server['app_usable']"
                                wire:click="toggle(@js($server['key']))">
                                {{ __('Allowed') }}
                            </x-noerd::checkbox>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-sm text-zinc-500">{{ __('No module of this installation offers an MCP server.') }}</li>
                    @endforelse
                </ul>

                @if ($saved)
                    <p class="text-xs text-green-600">{{ __('Saved') }}</p>
                @endif
            </div>
        </x-slot:tab1>
    </x-noerd::tab-content>
</x-noerd::page>
