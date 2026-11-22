<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use NoerdMcp\Enums\McpRequestStatus;
use NoerdMcp\Enums\McpServerAudience;
use NoerdMcp\Models\McpRequest;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Models\McpServerUser;
use NoerdMcp\Services\McpServerAccess;
use NoerdMcp\Support\McpServerRegistry;
use NoerdMcp\Tests\Concerns\InteractsWithMcpFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithMcpFixtures::class);

beforeEach(function (): void {
    $this->zzRegisterServer();
    $this->user = createNoerdUserForApp(self::ZZ_APP);
    $this->tenantId = (int) $this->user->selected_tenant_id;
    // An MCP request has no session: the tenant is the one persisted in the
    // person's settings.
    $this->user->setting->update(['selected_tenant_id' => $this->tenantId, 'locale' => 'en']);
});

it('answers 401 without an access token and logs nothing', function (): void {
    $this->zzCallTool('zz_echo', ['text' => 'hi'])->assertUnauthorized();

    expect(McpRequest::query()->acrossTenants()->count())->toBe(0);
});

it('refuses a server the tenant admin has not allowed, and logs the refusal', function (): void {
    $this->zzActAsMcpUser($this->user->id);

    $this->zzCallTool('zz_echo', ['text' => 'hi'])->assertForbidden();

    $log = McpRequest::query()->acrossTenants()->sole();

    expect($log->status)->toBe(McpRequestStatus::Refused)
        ->and($log->tenant_id)->toBe($this->tenantId)
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->error)->toContain('has not allowed');
});

it('runs an allowed server in the person\'s tenant and logs tool, arguments and client', function (): void {
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    $this->zzActAsMcpUser($this->user->id, $this->zzClient('Claude'));

    $this->zzCallTool('zz_echo', ['text' => 'hello'])
        ->assertOk()
        ->assertSee('hello')
        ->assertSee('\"tenant_id\":' . $this->tenantId, false);

    $log = McpRequest::query()->ofTenant($this->tenantId)->sole();

    expect($log->status)->toBe(McpRequestStatus::Ok)
        ->and($log->method)->toBe('tools/call')
        ->and($log->tool)->toBe('zz_echo')
        ->and($log->arguments)->toBe(['text' => 'hello'])
        ->and($log->client_name)->toBe('Claude')
        ->and($log->server)->toBe(self::ZZ_SERVER);
});

it('logs a tool refusal as an error with its message', function (): void {
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    $this->zzActAsMcpUser($this->user->id);

    $this->zzCallTool('zz_fail')->assertOk();

    $log = McpRequest::query()->ofTenant($this->tenantId)->sole();

    expect($log->status)->toBe(McpRequestStatus::Error)
        ->and($log->error)->toBe('Zz refused the request.');
});

it('cuts long arguments in the log', function (): void {
    config(['noerd-mcp.log.max_argument_bytes' => 300]);
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    $this->zzActAsMcpUser($this->user->id);

    $this->zzCallTool('zz_echo', ['text' => str_repeat('x', 1000)])->assertOk();

    $arguments = McpRequest::query()->ofTenant($this->tenantId)->sole()->arguments;

    expect($arguments['_truncated'])->toBeTrue()
        ->and(mb_strlen($arguments['_preview']))->toBe(300);
});

it('does not log notifications', function (): void {
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    $this->zzActAsMcpUser($this->user->id);

    $this->postJson('/mcp/' . self::ZZ_SERVER, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

    expect(McpRequest::query()->acrossTenants()->count())->toBe(0);
});

it('refuses a tenant that does not run the server\'s app, even when it was allowed', function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    $user->setting->update(['selected_tenant_id' => $tenant->id]);
    McpServerSetting::set($tenant->id, self::ZZ_SERVER, true);

    $this->zzActAsMcpUser($user->id);

    $this->zzCallTool('zz_echo')->assertForbidden();

    expect(McpRequest::query()->ofTenant($tenant->id)->sole()->status)->toBe(McpRequestStatus::Refused);
});

it('refuses a person without a selected tenant', function (): void {
    $user = NoerdUser::factory()->create();
    $this->zzActAsMcpUser($user->id);

    $this->zzCallTool('zz_echo')->assertForbidden();

    expect(McpRequest::query()->acrossTenants()->sole()->tenant_id)->toBeNull();
});

it('refuses a person the admin did not select when the server is open to selected people only', function (): void {
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    McpServerSetting::setAudience($this->tenantId, self::ZZ_SERVER, McpServerAudience::SelectedUsers);
    $this->zzActAsMcpUser($this->user->id);

    $this->zzCallTool('zz_echo', ['text' => 'hi'])->assertForbidden();

    $log = McpRequest::query()->ofTenant($this->tenantId)->sole();

    expect($log->status)->toBe(McpRequestStatus::Refused)
        ->and($log->error)->toContain('not allowed you');
});

it('lets a selected person use a server open to selected people only', function (): void {
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    McpServerSetting::setAudience($this->tenantId, self::ZZ_SERVER, McpServerAudience::SelectedUsers);
    McpServerSetting::setUserAllowed($this->tenantId, self::ZZ_SERVER, $this->user->id, true);
    $this->zzActAsMcpUser($this->user->id);

    $this->zzCallTool('zz_echo', ['text' => 'hello'])->assertOk()->assertSee('hello');
});

it('always lets a tenant admin in, selected or not', function (): void {
    $admin = NoerdUser::factory()->create();
    Tenant::query()->findOrFail($this->tenantId)->addMember($admin, Profile::Admin->value);
    $admin->setting->update(['selected_tenant_id' => $this->tenantId, 'locale' => 'en']);
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    McpServerSetting::setAudience($this->tenantId, self::ZZ_SERVER, McpServerAudience::SelectedUsers);
    $this->zzActAsMcpUser($admin->id);

    $this->zzCallTool('zz_echo', ['text' => 'hello'])->assertOk();
});

it('offers a server open to selected people only to the people selected', function (): void {
    $definition = app(McpServerRegistry::class)->find(self::ZZ_SERVER);
    $access = app(McpServerAccess::class);
    McpServerSetting::set($this->tenantId, self::ZZ_SERVER, true);
    $this->actingAs($this->user, \Noerd\Helpers\NoerdAuth::GUARD);

    expect($access->availableFor($this->tenantId))->toHaveCount(1);

    McpServerSetting::setAudience($this->tenantId, self::ZZ_SERVER, McpServerAudience::SelectedUsers);

    expect($access->availableFor($this->tenantId))->toBe([])
        ->and($access->allowsUser($this->tenantId, $definition, $this->user))->toBeFalse();

    McpServerSetting::setUserAllowed($this->tenantId, self::ZZ_SERVER, $this->user->id, true);

    expect($access->availableFor($this->tenantId))->toHaveCount(1);
});

it('forgets the selection of a person who leaves the tenant', function (): void {
    McpServerSetting::setUserAllowed($this->tenantId, self::ZZ_SERVER, $this->user->id, true);

    Tenant::query()->findOrFail($this->tenantId)->removeMember($this->user);

    expect(McpServerUser::query()->acrossTenants()->count())->toBe(0);
});
