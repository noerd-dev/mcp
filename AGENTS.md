# AGENTS.md — noerd/mcp

Contributor notes for humans and AI agents working on the MCP module. The rules for building WITH
noerd come from the `noerd/noerd` Boost guideline and skills; the module-specific rules are in
`resources/boost/guidelines/core.blade.php`, rendered into the host project's agent files by
`php artisan boost:update`.

## What this module is

The MCP layer of every noerd application: it serves the MCP servers modules register
(`McpServerRegistry`) at `/mcp/{key}` behind Passport OAuth for noerd users, logs every access
(`mcp_requests`), lets tenant admins allow servers per company (`mcp_server_settings`) and shows
every person their connected assistants. Modules (accounting, liefertool, …) depend on it and ship
only their server and tools; it never knows a business module.

## Layout

- `src/Support/` — registry, server definition, the route chain (`McpRoutes`)
- `src/Http/Middleware/` — `AuthenticateMcpUser`, `LogMcpRequest`, `ActAsNoerdUser`
- `src/Services/` — `McpRequestLogger`, `McpServerAccess`, `McpConnectors`
- `src/Models/` — `McpUser` (Passport view of `NoerdUser`), `McpServerSetting`, `McpRequest`
- `src/Tools/Concerns/ActsForNoerdUser.php` — the shared tool trait
- `resources/views/components/` — `mcp-servers-page`, `mcp-requests-list`, `mcp-request-detail`,
  `mcp-connectors-page` (Livewire namespace `noerd-mcp::`)
- `app-configs/noerd-mcp/` — list/detail YAML; `config/noerd-mcp.php`; `database/migrations/`
  (OAuth tables while missing, the module's tables)

## Working on the module

- Tests bind the host's `Tests\TestCase` (MySQL, `RefreshDatabase`):
  `php artisan test --compact app-modules/noerd-mcp/tests`; they use the `zz-echo` fixture server
- Format with the module's `pint.json`: `vendor/bin/pint --config app-modules/noerd-mcp/pint.json app-modules/noerd-mcp`
- Keep the module free of business modules (`ModuleBoundaryTest`)
- When a feature changes: update `resources/lang/de.json`, the tests, `README.md` and the guideline
- Releasing: bump `"version"` in `composer.json` to the tag in the tagged commit
