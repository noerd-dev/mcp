<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\ProfileMiddleware;
use NoerdMcp\Enums\McpRequestStatus;
use NoerdMcp\Models\McpRequest;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Tests\Concerns\InteractsWithMcpFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithMcpFixtures::class);

beforeEach(function (): void {
    $this->zzRegisterServer();
});

function zzMcpLogRow(int $tenantId, array $overrides = []): McpRequest
{
    return McpRequest::query()->ofTenant($tenantId)->create(array_merge([
        'tenant_id' => $tenantId,
        'server' => 'zz-echo',
        'method' => 'tools/call',
        'tool' => 'zz_echo',
        'status' => McpRequestStatus::Ok,
        'http_status' => 200,
        'duration_ms' => 12,
    ], $overrides));
}

function zzAccessToken(int $userId, string $clientId): string
{
    $id = bin2hex(random_bytes(20));

    Passport::tokenModel()::query()->forceCreate([
        'id' => $id,
        'user_id' => $userId,
        'client_id' => $clientId,
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);
    Passport::refreshTokenModel()::query()->forceCreate([
        'id' => bin2hex(random_bytes(20)),
        'access_token_id' => $id,
        'revoked' => false,
        'expires_at' => now()->addMonth(),
    ]);

    return $id;
}

it('lets a tenant admin allow and forbid a server', function (): void {
    $admin = createNoerdUserForApp(self::ZZ_APP, profile: Profile::Admin);
    $this->actingAs($admin);

    Livewire::test('noerd-mcp::mcp-servers-page')
        ->assertSee('Zz Echo')
        ->assertSeeHtml('wire:click="toggle(' . e(Js::from(self::ZZ_SERVER)) . ')"')
        ->call('toggle', self::ZZ_SERVER);

    expect(McpServerSetting::isEnabled((int) $admin->selected_tenant_id, self::ZZ_SERVER))->toBeTrue();

    Livewire::test('noerd-mcp::mcp-servers-page')->call('toggle', self::ZZ_SERVER);

    expect(McpServerSetting::isEnabled((int) $admin->selected_tenant_id, self::ZZ_SERVER))->toBeFalse();
});

it('never allows a server whose app the tenant does not run', function (): void {
    $this->actingAs(createNoerdUserForApp('ZZOTHER', profile: Profile::Admin));

    Livewire::test('noerd-mcp::mcp-servers-page')->call('toggle', self::ZZ_SERVER)->assertForbidden();
});

it('keeps the admin screens closed to other people', function (string $component): void {
    $this->actingAs(createNoerdUserForApp(self::ZZ_APP, profile: Profile::User));

    Livewire::test($component)->assertForbidden();
})->with(['noerd-mcp::mcp-servers-page', 'noerd-mcp::mcp-requests-list']);

it('shows an admin the access log of the own tenant only', function (): void {
    // Created first: the fixture selects the tenant of the user it creates.
    $other = createNoerdUserForApp(self::ZZ_APP);
    $admin = createNoerdUserForApp(self::ZZ_APP, profile: Profile::Admin);
    $this->actingAs($admin);

    zzMcpLogRow((int) $admin->selected_tenant_id, ['tool' => 'own_tool']);
    zzMcpLogRow((int) $other->selected_tenant_id, ['tool' => 'foreign_tool']);

    Livewire::test('noerd-mcp::mcp-requests-list')
        ->assertSee('own_tool')
        ->assertDontSee('foreign_tool');
});

it('shows a person the allowed servers and their connected clients, and disconnects one', function (): void {
    $someoneElse = createNoerdUserForApp(self::ZZ_APP);
    $user = createNoerdUserForApp(self::ZZ_APP);
    McpServerSetting::set((int) $user->selected_tenant_id, self::ZZ_SERVER, true);
    $client = $this->zzClient('Claude');
    $ownToken = zzAccessToken($user->id, $client->id);
    $foreignToken = zzAccessToken($someoneElse->id, $client->id);

    $this->actingAs($user);

    Livewire::test('noerd-mcp::mcp-connectors-page')
        ->assertSee(['Zz Echo', url('mcp/' . self::ZZ_SERVER), 'Claude'])
        ->call('disconnect', $client->id)
        ->assertDontSee('Disconnect');

    expect(Passport::tokenModel()::find($ownToken)->revoked)->toBeTrue()
        ->and(Passport::refreshTokenModel()::query()->where('access_token_id', $ownToken)->value('revoked'))->toBeTruthy()
        ->and(Passport::tokenModel()::find($foreignToken)->revoked)->toBeFalse();
});

it('does not offer a server the admin has not allowed', function (): void {
    $this->actingAs(createNoerdUserForApp(self::ZZ_APP));

    Livewire::test('noerd-mcp::mcp-connectors-page')->assertDontSee(url('mcp/' . self::ZZ_SERVER));
});

it('prunes log entries older than the retention', function (): void {
    $user = createNoerdUserForApp(self::ZZ_APP);
    $tenantId = (int) $user->selected_tenant_id;
    $old = zzMcpLogRow($tenantId);
    $old->forceFill(['created_at' => now()->subDays(100)])->save();
    $fresh = zzMcpLogRow($tenantId);

    $this->artisan('noerd-mcp:prune-requests', ['--days' => 90])->assertSuccessful();

    expect(McpRequest::query()->acrossTenants()->pluck('id')->all())->toBe([$fresh->id]);
});

it('serves every screen by its route and shows a log entry with its arguments', function (): void {
    $admin = createNoerdUserForApp(self::ZZ_APP, profile: Profile::Admin);
    $row = zzMcpLogRow((int) $admin->selected_tenant_id, ['arguments' => ['customer' => 'Müller GmbH']]);
    $this->actingAs($admin);

    foreach (['noerd-mcp.servers', 'noerd-mcp.requests', 'noerd-mcp.connectors'] as $route) {
        $this->get(route($route))->assertOk();
    }

    // "My MCP Connectors" is a page of the profile area: its route selects
    // the profile navigation.
    $this->get(route('noerd-mcp.connectors'))->assertOk();

    expect(TenantHelper::getSelectedApp())->toBe(ProfileMiddleware::APP);

    Livewire::test('noerd-mcp::mcp-request-detail', ['modelId' => $row->id])
        ->assertSee(['zz_echo', 'Müller GmbH'])
        ->assertDontSee(__('Save'));
});
