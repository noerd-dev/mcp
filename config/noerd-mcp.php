<?php

declare(strict_types=1);

return [

    /*
     | The access log (`mcp_requests`): tool arguments longer than this many
     | characters (as JSON) are stored cut and marked as truncated, and
     | `noerd-mcp:prune-requests` deletes rows older than the retention.
     */
    'log' => [
        'max_argument_bytes' => (int) env('NOERD_MCP_LOG_MAX_ARGUMENT_BYTES', 4000),
        'retention_days' => (int) env('NOERD_MCP_LOG_RETENTION_DAYS', 90),
    ],

    /*
     | OAuth discovery and dynamic client registration for MCP clients. The
     | registration route is public by specification, so it is rate limited —
     | every registration creates an OAuth client.
     */
    'oauth' => [
        'routes' => (bool) env('NOERD_MCP_OAUTH_ROUTES', true),
        'throttle' => env('NOERD_MCP_OAUTH_THROTTLE', '10,1'),
    ],

];
