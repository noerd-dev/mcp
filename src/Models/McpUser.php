<?php

declare(strict_types=1);

namespace NoerdMcp\Models;

use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Noerd\Models\NoerdUser;
use NoerdMcp\Http\Middleware\ActAsNoerdUser;

/**
 * A noerd user as the OAuth server (Passport) sees them: the same row of
 * `noerd_users`, with the token methods Passport needs. It only backs the
 * `noerd-mcp` guard; {@see ActAsNoerdUser} turns it back into the
 * noerd user, so tenant scope, permissions and audits stay those of the
 * NoerdUser.
 */
class McpUser extends NoerdUser implements OAuthenticatable
{
    use HasApiTokens;
}
