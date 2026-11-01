<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use NoerdMcp\Services\McpConnectors;
use NoerdMcp\Services\McpServerAccess;

/**
 * Every person's own page: the MCP servers they may connect to in the
 * selected company (with the URL to add in their AI assistant) and the
 * clients they connected (Claude, …), each of which they can disconnect. It
 * only ever reads and revokes the person's own tokens.
 */
new class extends Component {
    public function disconnect(string $clientId, McpConnectors $connectors): void
    {
        $userId = NoerdAuth::id();
        abort_if($userId === null, 403);

        $connectors->disconnect((int) $userId, $clientId);

        unset($this->connectors);
    }

    /**
     * @return list<array{key: string, label: string, description: ?string, url: string}>
     */
    #[Computed]
    public function servers(): array
    {
        $tenantId = TenantHelper::currentTenantId();

        if ($tenantId === null) {
            return [];
        }

        return array_map(fn($definition): array => [
            'key' => $definition->key,
            'label' => __($definition->label),
            'description' => $definition->description === null ? null : __($definition->description),
            'url' => $definition->url(),
        ], app(McpServerAccess::class)->availableFor((int) $tenantId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function connectors(): array
    {
        $userId = NoerdAuth::id();

        return $userId === null ? [] : app(McpConnectors::class)->forUser((int) $userId);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('My MCP Connectors') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::profile-section>
        <h3 class="text-lg font-medium text-gray-900">{{ __('Available MCP servers') }}</h3>
        <p class="mt-1 text-sm text-zinc-600">
            {{ __('Add a server as a connector in your AI assistant (e.g. Claude → Settings → Connectors) with its URL. You sign in with your account; the assistant then works in the company you have selected here, with your permissions.') }}
        </p>

        <ul class="mt-3 divide-y divide-zinc-100 rounded-md border border-zinc-200">
            @forelse ($this->servers as $server)
                <li wire:key="server-{{ $server['key'] }}" class="px-4 py-3">
                    <div class="font-medium text-zinc-900">{{ $server['label'] }}</div>
                    @if ($server['description'])
                        <div class="mt-0.5 text-sm text-zinc-600">{{ $server['description'] }}</div>
                    @endif
                    <div class="mt-1 flex items-center gap-2" x-data="{ copied: false }">
                        <code class="truncate rounded bg-zinc-50 px-2 py-1 text-xs text-zinc-700">{{ $server['url'] }}</code>
                        <button type="button" class="text-xs text-zinc-500 hover:text-zinc-900"
                                x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($server['url']) }}); copied = true; setTimeout(() => copied = false, 1500)">
                            <span x-show="! copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </button>
                    </div>
                </li>
            @empty
                <li class="px-4 py-4 text-sm text-zinc-500">{{ __('No MCP server is allowed for this company. An administrator can allow servers under Setup → MCP Servers.') }}</li>
            @endforelse
        </ul>
    </x-noerd::profile-section>

    <x-noerd::profile-section>
        <h3 class="text-lg font-medium text-gray-900">{{ __('Connected clients') }}</h3>

        <ul class="mt-3 divide-y divide-zinc-100 rounded-md border border-zinc-200">
            @forelse ($this->connectors as $connector)
                <li wire:key="connector-{{ $connector['client_id'] }}" class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                    <div class="min-w-0">
                        <div class="font-medium text-zinc-900">{{ $connector['name'] }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500">
                            @if ($connector['connected_at'])
                                {{ __('Connected :date', ['date' => FormatHelper::dateTime($connector['connected_at'])]) }}
                            @endif
                            @if ($connector['last_used_at'])
                                · {{ __('Last used :date', ['date' => FormatHelper::dateTime($connector['last_used_at'])]) }}
                            @endif
                        </div>
                    </div>
                    <x-noerd::button
                        variant="secondary"
                        wire:click="disconnect(@js($connector['client_id']))"
                        wire:confirm="{{ __('Disconnect this client? It has to ask for your consent again.') }}">
                        {{ __('Disconnect') }}
                    </x-noerd::button>
                </li>
            @empty
                <li class="px-4 py-4 text-sm text-zinc-500">{{ __('You have not connected any client yet.') }}</li>
            @endforelse
        </ul>
    </x-noerd::profile-section>
</x-noerd::page>
