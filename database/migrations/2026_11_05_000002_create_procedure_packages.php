<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_packages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('total', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_catalog')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'is_active', 'show_in_catalog'], 'packages_catalog_index');
        });

        Schema::create('procedure_package_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('procedure_package_id');
            $table->unsignedBigInteger('procedure_id');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
            $table->unique(['tenant_id', 'procedure_package_id', 'procedure_id'], 'package_procedure_unique');
            $table->foreign(['tenant_id', 'procedure_package_id'], 'package_item_package_fk')
                ->references(['tenant_id', 'id'])->on('procedure_packages')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'procedure_id'], 'package_item_procedure_fk')
                ->references(['tenant_id', 'id'])->on('procedures')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_package_items');
        Schema::dropIfExists('procedure_packages');
    }
};
