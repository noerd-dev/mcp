<?php

declare(strict_types=1);

namespace NoerdMcp\Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use NoerdMcp\Models\McpUser;
use NoerdMcp\Providers\NoerdMcpServiceProvider;
use NoerdMcp\Support\McpRoutes;
use NoerdMcp\Support\McpServerRegistry;
use NoerdMcp\Tests\Fixtures\ZzEchoServer;

/**
 * The made-up `zz-echo` server (app ZZMCP), a connected OAuth client and the
 * JSON-RPC calls an MCP client sends — the package proves its mechanics
 * without any business module.
 */
trait InteractsWithMcpFixtures
{
    protected const string ZZ_APP = 'ZZMCP';

    protected const string ZZ_SERVER = 'zz-echo';

    /** @var array{0: string, 1: string}|null one throwaway key pair per test process */
    private static ?array $zzPassportKeys = null;

    protected function zzRegisterServer(): void
    {
        $this->zzPassportKeys();

        $registry = app(McpServerRegistry::class);
        $registry->register(self::ZZ_SERVER, ZzEchoServer::class, self::ZZ_APP, 'Zz Echo', 'Echoes for tests.');

        McpRoutes::server($registry->find(self::ZZ_SERVER));
        app('router')->getRoutes()->refreshNameLookups();
    }

    /**
     * Passport builds its token guard with the public key, so even a request
     * without a token needs a key pair — a throwaway one, never the host's.
     */
    protected function zzPassportKeys(): void
    {
        if (self::$zzPassportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$zzPassportKeys = [$private, openssl_pkey_get_details($key)['key']];
        }

        config(['passport.private_key' => self::$zzPassportKeys[0], 'passport.public_key' => self::$zzPassportKeys[1]]);
    }

    protected function zzClient(string $name = 'Claude'): Client
    {
        return Passport::clientModel()::query()->forceCreate([
            'name' => $name,
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    /**
     * The person behind the token: as the MCP guard sees them, signed in with
     * an access token of the given client.
     */
    protected function zzActAsMcpUser(int $userId, ?Client $client = null): McpUser
    {
        $user = McpUser::query()->findOrFail($userId);

        return Passport::actingAs($user, ['mcp:use'], NoerdMcpServiceProvider::GUARD, $client ?? $this->zzClient());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function zzCallTool(string $tool, array $arguments = []): TestResponse
    {
        return $this->zzRpc('tools/call', ['name' => $tool, 'arguments' => $arguments]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function zzRpc(string $method, array $params = []): TestResponse
    {
        return $this->postJson('/mcp/' . self::ZZ_SERVER, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], ['Accept' => 'application/json, text/event-stream']);
    }
}
