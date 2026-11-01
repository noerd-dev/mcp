<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use NoerdMcp\Enums\McpRequestStatus;
use NoerdMcp\Models\McpRequest;
use NoerdMcp\Models\McpServerSetting;
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
