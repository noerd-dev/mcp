@verbatim
## Noerd MCP Module

MCP servers for noerd applications (Composer package `noerd/mcp`, namespace `NoerdMcp` — no `Noerd\`
prefix; the monorepo mounts it at `app-modules/noerd-mcp`). It is a SUPPORT module: no tenant app,
no navigation of its own (its admin screens hang in the setup navigation). It integrates
`laravel/mcp` and `laravel/passport` for every module — a module ships only its server and tools.
The framework rules come from the `noerd/noerd` guideline — this block only adds what is specific.

### Serving a module's MCP server

- A module registers its `Laravel\Mcp\Server` in its provider's `boot()`:
  `app(NoerdMcp\Support\McpServerRegistry::class)->register(key: 'accounting', serverClass: AccountingServer::class, apps: 'ACCOUNTING', label: 'Accounting', description: '…')`.
  `key` is kebab-case and stable — it is the URL `/mcp/{key}` (an assistant's connector) and the
  key of the tenant setting; `apps` are the tenant apps that give access (one is enough); `label`
  and `description` are English translation keys of that module
- The provider adds every registered server once all providers booted, through
  `Support\McpRoutes::server()` — the ONE middleware chain: `AuthenticateMcpUser` (Passport token
  on the `noerd-mcp` guard, 401 JSON without one) → `LogMcpRequest` → `ActAsNoerdUser:{key}` (the
  token's user becomes the noerd user of the tenant persisted in their settings, in their
  language; refused without a tenant, without one of the server's apps or while the tenant admin
  has not allowed the server). A host registers NOTHING — no `routes/ai.php`, no `api` guard, no
  `OAuthenticatable` user class, no consent view
- Tools use `NoerdMcp\Tools\Concerns\ActsForNoerdUser` (`tenantId()`, `forbidden()`, `mayRead()` /
  `mayCreate()` / `mayWrite()`, `answer()`, `link()`). A tool checks the permission itself, reads
  every record with `withoutGlobalScopes()->where('tenant_id', …)` and answers a refusal as
  `Response::error()`, never as an exception. Logic belongs in a service of the module
- Tests of a module's tools call `Server::tool(Tool::class, [...])` as the acting noerd user; the
  HTTP chain is this package's business

### OAuth (Passport)

- Guard `noerd-mcp` (driver `passport`) on the user provider `noerd_mcp_users`
  (`Models\McpUser extends NoerdUser implements OAuthenticatable` — the same `noerd_users` row),
  set at runtime unless the host defines it; `passport.guard` defaults to `noerd` (the consent
  screen runs on the signed-in session); `Passport::authorizationView()` renders laravel/mcp's
  `mcp::authorize`. `Mcp::oauthRoutes()` (discovery + dynamic client registration) is throttled
  by `noerd-mcp.oauth.throttle`
- The OAuth tables come with this package (`create_oauth_tables_for_mcp`, each only while
  missing). The key pair: `noerd:install-mcp` / `noerd:update-mcp` run `passport:keys` when there is
  none; a deployment keeps `storage/oauth-*.key` in its shared storage or sets
  `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`

### Approval, access log, connectors

- `Models\McpServerSetting` (`mcp_server_settings`, tenant + `server` key + `enabled`): a MISSING
  row means NOT allowed — a server goes live only once a tenant admin switched it on in
  `noerd-mcp::mcp-servers-page` (route `noerd-mcp.servers`, Setup → Administration). A server
  whose apps the tenant does not run cannot be switched on. `Services\McpServerAccess` is the rule
  the screens ask (`appUsable()`, `enabled()`, `availableFor()`); `ActAsNoerdUser` enforces it.
  A module whose server existed before the approval allows it with a data migration of its own
  for the tenants already connected (accounting: `allow_accounting_mcp_server_for_connected_tenants`)
- `Models\McpRequest` (`mcp_requests`, no `updated_at`) is written ONLY by
  `Services\McpRequestLogger`: every request of an authenticated person — tenant (null when the
  person had none), user, server, OAuth client id + name, JSON-RPC method, tool, arguments (JSON
  over `noerd-mcp.log.max_argument_bytes` stored as `{_truncated, _preview}`), status
  (`Enums\McpRequestStatus` ok | error — a JSON-RPC error or a tool result with `isError` — |
  refused — 401/403/404), HTTP status, message, duration, IP. Notifications and pings are skipped;
  a logging failure is reported, never thrown. The arguments carry business data: the log is for
  tenant admins only (`mcp-requests-list` / `mcp-request-detail`, routes `noerd-mcp.requests` /
  `noerd-mcp.request.detail`, read-only); `noerd-mcp:prune-requests {--days=}` deletes old rows —
  the host schedules it
- `noerd-mcp::mcp-connectors-page` (route `noerd-mcp.connectors`, `/noerd/user/mcp-connectors`,
  every person) is a page of the core's profile area: registered on the `ProfileNavigationRegistry`
  ("My MCP Connectors", sort 50) and rendered in `<x-noerd::profile-layout>`. It lists the servers the
  selected tenant may use with the URL to add in an assistant, and the person's connected OAuth
  clients (`Services\McpConnectors::forUser()` — live access tokens grouped by client, last use
  from the log); "Disconnect" revokes all their tokens of that client, refresh tokens included
- The admin components are registered with `ComponentAccessGuard` AND check `isAdmin()` in
  `mount()` and every action

### Structure

- `src/Support/` (`McpServerRegistry`, `McpServerDefinition`, `McpRoutes`), `src/Http/Middleware/`,
  `src/Services/`, `src/Models/`, `src/Enums/`, `src/Tools/Concerns/`, `src/Commands/`
- Livewire namespace `noerd-mcp::` (`resources/views/components/`); YAML
  `app-configs/noerd-mcp/{lists,details}/` (resolved through the owner-app fallback; keep the module
  copy and the installed project copy in sync); config `config/noerd-mcp.php` (merged);
  translations `resources/lang/de.json` (English keys)

### Commands

- `php artisan noerd:install-mcp` — publishes the YAML, offers the migrations, creates the Passport
  keys when missing, adds "MCP Servers" and "MCP Access Log" to the setup navigation
- `php artisan noerd:update-mcp` — the same without migrations (picked up by `noerd:update-all`)
- `php artisan noerd-mcp:prune-requests {--days=}` — log retention (default
  `noerd-mcp.log.retention_days`, 90)

### Tests

- Pest tests in `tests/Feature`, bound to the host `Tests\TestCase` + `RefreshDatabase`; run with
  `php artisan test --compact app-modules/noerd-mcp/tests`
- `tests/Concerns/InteractsWithMcpFixtures`: the made-up `zz-echo` server (app `ZZMCP`, fixtures
  `ZzEchoServer`, `ZzEchoTool`, `ZzFailTool`) registered with its route, a throwaway Passport key
  pair, `zzClient()`, `zzActAsMcpUser()` (`Passport::actingAs()` on the `noerd-mcp` guard) and the
  JSON-RPC calls (`zzCallTool()`, `zzRpc()`) — never a business module
@endverbatim
