<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use NoerdMcp\Tests\Concerns\InteractsWithMcpFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithMcpFixtures::class);

beforeEach(function (): void {
    $this->zzRegisterServer();
});

it('sends a guest on the consent screen to the noerd login and back', function (): void {
    $client = $this->zzClient('Claude');
    $query = http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'zz',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $this->get('/oauth/authorize?' . $query)->assertRedirect(route('noerd.login'));

    expect(session('url.intended'))->toContain('/oauth/authorize');
});

it('shows a person signed in to noerd the consent screen', function (): void {
    $client = $this->zzClient('Claude');
    $user = createNoerdUserForApp(self::ZZ_APP);
    $query = http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'zz',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs($user, 'noerd')
        ->get('/oauth/authorize?' . $query)
        ->assertOk()
        ->assertSee('Claude');

    expect(collect(Route::getRoutes()->getByName('passport.authorizations.approve')->gatherMiddleware()))
        ->toContain('auth:noerd');
});

it('publishes the OAuth discovery of the MCP servers', function (): void {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'));
});
