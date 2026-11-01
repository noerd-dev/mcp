<?php

declare(strict_types=1);

namespace NoerdMcp\Enums;

/**
 * The outcome of one logged MCP request: answered, answered with an error (a
 * tool refusal, a validation error, a JSON-RPC error), or refused before the
 * server ran (no tenant, no app, not allowed by the admin).
 */
enum McpRequestStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Ok => __('OK'),
            self::Error => __('Error'),
            self::Refused => __('Refused'),
        };
    }
}
