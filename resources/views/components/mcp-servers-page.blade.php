<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use NoerdMcp\Enums\McpServerAudience;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Services\McpServerAccess;
use NoerdMcp\Support\McpServerDefinition;
use NoerdMcp\Support\McpServerRegistry;

/**
 * Tenant admins decide which registered MCP servers the company's people may
 * connect to (e.g. from Claude), and whether a server is open to everybody or
 * only to the people they select (tenant admins always may). A server stays
 * off until an admin allows it; one whose app the tenant does not run cannot
 * be allowed. Admins only — registered with the ComponentAccessGuard and
 * checked in every action.
 */
new class extends Component {
    public bool $saved = false;

    public string $memberSearch = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function toggle(string $key, McpServerAccess $access): void
    {
        $this->authorizeAdmin();

        $definition = $this->definition($key);
        $tenantId = (int) TenantHelper::currentTenantId();
        $enabled = $access->enabled($tenantId, $definition);

        // Switching off is always possible; switching on needs the app.
        abort_if(! $enabled && ! $access->appUsable($definition), 403);

        McpServerSetting::set($tenantId, $key, ! $enabled);

        $this->afterSave();
    }

    public function setAudience(string $key, string $audience): void
    {
        $this->authorizeAdmin();

        $definition = $this->definition($key);
        $audience = McpServerAudience::tryFrom($audience);
        abort_if($audience === null, 422);

        McpServerSetting::setAudience((int) TenantHelper::currentTenantId(), $definition->key, $audience);

        $this->afterSave();
    }

    public function toggleUser(string $key, int $userId): void
    {
        $this->authorizeAdmin();

        $definition = $this->definition($key);
        $tenantId = (int) TenantHelper::currentTenantId();
        abort_unless(Tenant::query()->findOrFail($tenantId)->users()->whereKey($userId)->exists(), 404);

        McpServerSetting::setUserAllowed(
            $tenantId,
            $definition->key,
            $userId,
            ! McpServerSetting::isUserAllowed($tenantId, $definition->key, $userId),
        );

        $this->afterSave();
    }

    /**
     * @return list<array{key: string, label: string, description: ?string, url: string, app_usable: bool, enabled: bool, audience: string, user_ids: list<int>}>
     */
    #[Computed]
    public function servers(): array
    {
        $access = app(McpServerAccess::class);
        $tenantId = (int) TenantHelper::currentTenantId();

        return array_values(array_map(fn(McpServerDefinition $definition): array => [
            'key' => $definition->key,
            'label' => __($definition->label),
            'description' => $definition->description === null ? null : __($definition->description),
            'url' => $definition->url(),
            'app_usable' => $access->appUsable($definition),
            'enabled' => $access->enabled($tenantId, $definition),
            'audience' => $access->audience($tenantId, $definition)->value,
            'user_ids' => McpServerSetting::allowedUserIds($tenantId, $definition->key),
        ], app(McpServerRegistry::class)->all()));
    }

    /**
     * The tenant's members, narrowed by the search; admins always may use a
     * server and are shown as such.
     *
     * @return list<array{id: int, name: string, email: string, is_admin: bool}>
     */
    #[Computed]
    public function members(): array
    {
        $tenant = Tenant::query()->find(TenantHelper::currentTenantId());

        if ($tenant === null) {
            return [];
        }

        $search = mb_strtolower(Str::squish($this->memberSearch));

        return $tenant->users()->orderBy('name')->get()
            ->map(fn($user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'is_admin' => $user->isSuperAdmin() || $user->pivot->profile_key === Profile::Admin->value,
            ])
            ->filter(fn(array $member): bool => $search === ''
                || str_contains(mb_strtolower($member['name']), $search)
                || str_contains(mb_strtolower($member['email']), $search))
            ->values()
            ->all();
    }

    private function definition(string $key): McpServerDefinition
    {
        $definition = app(McpServerRegistry::class)->find($key);
        abort_if($definition === null, 404);

        return $definition;
    }

    private function afterSave(): void
    {
        unset($this->servers);
        $this->saved = true;
    }

    private function authorizeAdmin(): void
    {
        abort_unless((bool) NoerdAuth::user()?->isAdmin() && TenantHelper::currentTenantId() !== null, 403);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('MCP Server Admin') }}</x-noerd::modal-title>
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
                                wire:click="toggle({{ \Illuminate\Support\Js::from($server['key']) }})">
                                {{ __('Allowed') }}
                            </x-noerd::checkbox>
                        </li>

                        @if ($server['enabled'])
                            <li wire:key="server-{{ $server['key'] }}-audience" class="space-y-3 bg-zinc-50 px-4 py-3">
                                <fieldset class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                    <legend class="sr-only">{{ __('Who may use this server') }}</legend>
                                    @foreach (['all' => __('All people of the company'), 'selected' => __('Selected people')] as $audience => $audienceLabel)
                                        <label class="flex cursor-pointer items-center gap-2 text-zinc-900">
                                            <input
                                                type="radio"
                                                name="mcp-audience-{{ $server['key'] }}"
                                                value="{{ $audience }}"
                                                class="text-brand-primary focus:ring-brand-border h-4 w-4 cursor-pointer border-gray-400"
                                                @checked($server['audience'] === $audience)
                                                wire:click="setAudience({{ \Illuminate\Support\Js::from($server['key']) }}, {{ \Illuminate\Support\Js::from($audience) }})" />
                                            {{ $audienceLabel }}
                                        </label>
                                    @endforeach
                                </fieldset>

                                @if ($server['audience'] === 'selected')
                                    <div class="space-y-2">
                                        <p class="text-xs text-zinc-600">{{ __('Only the people ticked here may connect to this server. Administrators always may.') }}</p>

                                        <x-noerd::text-input
                                            type="search"
                                            wire:model.live.debounce.300ms="memberSearch"
                                            placeholder="{{ __('Search people') }}"
                                            class="max-w-sm" />

                                        <ul class="max-h-72 divide-y divide-zinc-100 overflow-y-auto rounded-md border border-zinc-200 bg-white">
                                            @forelse ($this->members as $member)
                                                <li wire:key="server-{{ $server['key'] }}-member-{{ $member['id'] }}" class="px-3 py-1.5">
                                                    <x-noerd::checkbox
                                                        id="mcp-server-{{ $server['key'] }}-user-{{ $member['id'] }}"
                                                        :checked="$member['is_admin'] || in_array($member['id'], $server['user_ids'], true)"
                                                        :disabled="$member['is_admin']"
                                                        wire:click="toggleUser({{ \Illuminate\Support\Js::from($server['key']) }}, {{ $member['id'] }})">
                                                        {{ $member['name'] }}
                                                        <span class="font-normal text-zinc-500">{{ $member['email'] }}</span>
                                                        @if ($member['is_admin'])
                                                            <span class="font-normal text-zinc-500">· {{ __('Always allowed (administrator)') }}</span>
                                                        @endif
                                                    </x-noerd::checkbox>
                                                </li>
                                            @empty
                                                <li class="px-3 py-2 text-sm text-zinc-500">{{ __('No person found.') }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endif
                            </li>
                        @endif
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
