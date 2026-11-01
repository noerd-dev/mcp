<?php

declare(strict_types=1);

namespace NoerdMcp\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;

/**
 * Whether a tenant admin allowed an MCP server (`server` = registry key) for
 * the tenant. A missing row means NOT allowed — a server goes live only once
 * an admin switched it on.
 */
class McpServerSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'mcp_server_settings';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'bool',
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
}
