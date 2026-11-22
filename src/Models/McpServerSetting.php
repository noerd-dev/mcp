<?php

declare(strict_types=1);

namespace NoerdMcp\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;
use NoerdMcp\Enums\McpServerAudience;

/**
 * Whether a tenant admin allowed an MCP server (`server` = registry key) for
 * the tenant, and for whom (`audience`). A missing row means NOT allowed — a
 * server goes live only once an admin switched it on. With the audience
 * `selected` only the people in `mcp_server_users` (and the tenant admins) may
 * use it; who may is decided by {@see \NoerdMcp\Services\McpServerAccess}.
 */
class McpServerSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'mcp_server_settings';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'bool',
        'audience' => McpServerAudience::class,
    ];

    public static function isEnabled(int $tenantId, string $server): bool
    {
        return (bool) static::query()->ofTenant($tenantId)->where('server', $server)->value('enabled');
    }

    public static function set(int $tenantId, string $server, bool $enabled): void
    {
        static::query()->ofTenant($tenantId)->updateOrCreate(
            ['tenant_id' => $tenantId, 'server' => $server],
            ['enabled' => $enabled],
        );
    }

    public static function audienceFor(int $tenantId, string $server): McpServerAudience
    {
        $audience = static::query()->ofTenant($tenantId)->where('server', $server)->value('audience');

        if ($audience instanceof McpServerAudience) {
            return $audience;
        }

        return McpServerAudience::tryFrom((string) $audience) ?? McpServerAudience::All;
    }

    /**
     * Changes who may use the server; whether it is allowed at all stays as
     * it is (a new row starts switched off).
     */
    public static function setAudience(int $tenantId, string $server, McpServerAudience $audience): void
    {
        static::query()->ofTenant($tenantId)->updateOrCreate(
            ['tenant_id' => $tenantId, 'server' => $server],
            ['audience' => $audience],
        );
    }

    /**
     * @return list<int>
     */
    public static function allowedUserIds(int $tenantId, string $server): array
    {
        return McpServerUser::query()->ofTenant($tenantId)->where('server', $server)
            ->pluck('user_id')->map(fn($id): int => (int) $id)->values()->all();
    }

    public static function isUserAllowed(int $tenantId, string $server, int $userId): bool
    {
        return McpServerUser::query()->ofTenant($tenantId)->where('server', $server)->where('user_id', $userId)->exists();
    }

    public static function setUserAllowed(int $tenantId, string $server, int $userId, bool $allowed): void
    {
        $query = McpServerUser::query()->ofTenant($tenantId)->where('server', $server)->where('user_id', $userId);

        if (! $allowed) {
            $query->delete();

            return;
        }

        McpServerUser::query()->ofTenant($tenantId)->firstOrCreate(
            ['tenant_id' => $tenantId, 'server' => $server, 'user_id' => $userId],
        );
    }
}
