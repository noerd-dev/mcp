# noerd/mcp

MCP servers for noerd applications. Modules register their `laravel/mcp` server; this package
serves each one at `/mcp/{key}` behind OAuth (Laravel Passport) for noerd users, in the company the
person selected and with their permissions.

- **Admins** allow the servers per company under Setup → Administration → **MCP Servers** (a server
  is off until allowed) and see every access in the **MCP Access Log** (person, client, tool,
  arguments, result, duration).
- **Every person** finds the server URLs for their assistant (e.g. Claude → Settings → Connectors)
  and their connected clients under **My MCP Connectors** (`/noerd/user/mcp-connectors`, a page of the profile area), where they can
  disconnect a client.

## Installation

```bash
php artisan noerd:install-mcp
```

Publishes the screen YAML, runs the migrations (Passport's OAuth tables are created when missing),
creates Passport's key pair when there is none, adds the setup navigation entries and "My MCP
Connectors" to the profile navigation. A deployment
keeps `storage/oauth-private.key` / `oauth-public.key` in its shared storage (or sets
`PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`). Schedule `noerd-mcp:prune-requests` for the log
retention (`NOERD_MCP_LOG_RETENTION_DAYS`, default 90).

## Registering a server

```php
use NoerdMcp\Support\McpServerRegistry;

public function boot(): void
{
    app(McpServerRegistry::class)->register(
        key: 'accounting',
        serverClass: AccountingServer::class,
        apps: 'ACCOUNTING',
        label: 'Accounting',
        description: 'Quotes, order confirmations, invoices and dunnings for existing customers.',
    );
}
```

Tools use `NoerdMcp\Tools\Concerns\ActsForNoerdUser` for the tenant, the permission checks and the
answer shapes.
