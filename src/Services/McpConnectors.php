<?php

declare(strict_types=1);

namespace NoerdMcp\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use NoerdMcp\Models\McpRequest;

/**
 * The MCP clients (Claude, ChatGPT, …) a person connected through OAuth: one
 * entry per OAuth client holding a live access token of the person, with
 * when it was connected and last used. Disconnecting revokes every token of
 * the person for that client, refresh tokens included — the client has to
 * ask for consent again.
 */
class McpConnectors
{
    /**
     * @return list<array{client_id: string, name: string, connected_at: ?CarbonInterface, last_used_at: ?CarbonInterface, expires_at: ?CarbonInterface}>
     */
    public function forUser(int $userId): array
    {
        $tokens = Passport::tokenModel()::query()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->where(fn($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('client')
            ->get()
            ->groupBy('client_id');

        $lastUsed = McpRequest::query()
            ->acrossTenants()
            ->where('user_id', $userId)
            ->whereIn('client_id', $tokens->keys()->all())
            ->groupBy('client_id')
            ->pluck(DB::raw('MAX(created_at)'), 'client_id');

        return $tokens->map(function ($clientTokens, $clientId) use ($lastUsed): array {
            $client = $clientTokens->first()->client;
            $lastUsedAt = $lastUsed[$clientId] ?? null;

            return [
                'client_id' => (string) $clientId,
                'name' => $client?->name ?? __('Unknown client'),
                'connected_at' => $clientTokens->min('created_at'),
                'last_used_at' => $lastUsedAt === null ? null : now()->parse($lastUsedAt),
                'expires_at' => $clientTokens->max('expires_at'),
            ];
        })->sortBy('name')->values()->all();
    }

    public function disconnect(int $userId, string $clientId): void
    {
        DB::transaction(function () use ($userId, $clientId): void {
            $tokenIds = Passport::tokenModel()::query()
                ->where('user_id', $userId)
                ->where('client_id', $clientId)
                ->pluck('id');

            Passport::tokenModel()::query()->whereIn('id', $tokenIds)->update(['revoked' => true]);
            Passport::refreshTokenModel()::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        });
    }
}
