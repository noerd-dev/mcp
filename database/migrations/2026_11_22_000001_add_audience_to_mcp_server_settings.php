<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who of a tenant may use an allowed MCP server: everybody (`all`, the
 * behaviour before this column existed) or only the people listed in
 * `mcp_server_users` (`selected`; tenant admins always may).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('mcp_server_settings', 'audience')) {
            Schema::table('mcp_server_settings', function (Blueprint $table): void {
                $table->string('audience', 20)->default('all')->after('enabled');
            });
        }

        if (! Schema::hasTable('mcp_server_users')) {
            Schema::create('mcp_server_users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('server', 100);
                $table->foreignId('user_id')->constrained('noerd_users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'server', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_server_users');

        if (Schema::hasColumn('mcp_server_settings', 'audience')) {
            Schema::table('mcp_server_settings', function (Blueprint $table): void {
                $table->dropColumn('audience');
            });
        }
    }
};
