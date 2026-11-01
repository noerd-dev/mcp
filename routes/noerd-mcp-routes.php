<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 | The screens of noerd/mcp. The MCP servers themselves (`/mcp/{key}`) and the
 | OAuth routes are registered by the provider once every module registered
 | its server. Admin screens hang in the setup navigation and admit tenant
 | admins only; "My MCP Connectors" is every person's own page in the profile
 | area — the `profile` middleware selects its navigation
 | (app-configs/profile/navigation.yml, entry added by noerd:install-mcp).
 */
Route::group(['middleware' => ['noerd']], function (): void {
    Route::livewire('noerd/mcp-servers', 'noerd-mcp::mcp-servers-page')->name('noerd-mcp.servers');
    Route::livewire('noerd/mcp-requests', 'noerd-mcp::mcp-requests-list')->name('noerd-mcp.requests');
    Route::livewire('noerd/mcp-request/{modelId}', 'noerd-mcp::mcp-request-detail')->name('noerd-mcp.request.detail');
});

Route::group(['middleware' => ['noerd', 'profile']], function (): void {
    Route::livewire('noerd/user/mcp-connectors', 'noerd-mcp::mcp-connectors-page')->name('noerd-mcp.connectors');
});
