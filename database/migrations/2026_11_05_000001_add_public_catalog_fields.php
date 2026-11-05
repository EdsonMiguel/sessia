<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->unique();
            $table->string('phone', 20)->nullable();
        });
        Schema::table('procedures', function (Blueprint $table) {
            $table->boolean('show_in_catalog')->default(false);
            $table->index(['tenant_id', 'show_in_catalog', 'is_active'], 'procedures_public_catalog_index');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropIndex('procedures_public_catalog_index');
            $table->dropColumn('show_in_catalog');
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'phone']);
        });
    }
};
