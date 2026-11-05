<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function base(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->id();
        $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
        $table->timestamps();
        $table->unique(['tenant_id', 'id']);
    }

    private function link(Blueprint $table, string $column, string $parent, bool $nullable = false): void
    {
        $table->unsignedBigInteger($column)->nullable($nullable);
        $table->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($parent)->restrictOnDelete();
    }

    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('pix_key', 140)->nullable();
            $table->string('pix_receiver', 120)->nullable();
            $table->string('pix_city', 120)->nullable();
        });
        Schema::create('service_contracts', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'customer_id', 'customers');
            $this->link($table, 'address_id', 'customer_addresses', true);
            $table->string('type', 12);
            $table->string('status', 12)->default('active');
            $table->decimal('total', 12, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->time('preferred_time')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->text('notes')->nullable();
            $table->index(['tenant_id', 'type', 'status']);
        });
        Schema::create('contract_items', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'service_contract_id', 'service_contracts');
            $this->link($table, 'procedure_id', 'procedures');
            $table->string('procedure_name', 120);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->unique(['tenant_id', 'service_contract_id', 'procedure_id'], 'contract_procedure_unique');
        });
        Schema::create('contract_events', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'service_contract_id', 'service_contracts');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event', 30);
            $table->text('reason')->nullable();
        });
        Schema::create('plan_periods', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'service_contract_id', 'service_contracts');
            $table->date('month');
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->unique(['tenant_id', 'service_contract_id', 'month'], 'plan_month_unique');
        });
        Schema::create('plan_period_items', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'plan_period_id', 'plan_periods');
            $this->link($table, 'contract_item_id', 'contract_items');
            $table->unsignedSmallInteger('quantity');
            $table->unique(['tenant_id', 'plan_period_id', 'contract_item_id'], 'period_item_unique');
        });
        Schema::create('appointments', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'customer_id', 'customers');
            $table->json('address_snapshot');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('occupied_from');
            $table->dateTime('occupied_until');
            $table->unsignedSmallInteger('preparation_minutes')->default(0);
            $table->unsignedSmallInteger('travel_minutes')->default(0);
            $table->string('status', 12)->default('scheduled');
            $table->string('recurrence_key', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unique(['tenant_id', 'recurrence_key']);
            $table->index(['tenant_id', 'status', 'occupied_from', 'occupied_until'], 'agenda_occupation_index');
        });
        Schema::create('appointment_items', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'appointment_id', 'appointments');
            $this->link($table, 'procedure_id', 'procedures');
            $this->link($table, 'contract_item_id', 'contract_items', true);
            $this->link($table, 'plan_period_item_id', 'plan_period_items', true);
            $table->string('procedure_name', 120);
            $table->unsignedSmallInteger('duration_minutes');
        });
        Schema::create('appointment_events', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'appointment_id', 'appointments');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event', 30);
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
        });
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $this->base($table);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->index(['tenant_id', 'starts_at', 'ends_at']);
        });
        Schema::create('service_executions', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'appointment_item_id', 'appointment_items');
            $this->link($table, 'contract_item_id', 'contract_items', true);
            $this->link($table, 'plan_period_item_id', 'plan_period_items', true);
            $table->dateTime('performed_at');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->unique(['tenant_id', 'appointment_item_id'], 'execution_once_unique');
        });
        Schema::create('receivables', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'customer_id', 'customers');
            $this->link($table, 'service_contract_id', 'service_contracts');
            $this->link($table, 'plan_period_id', 'plan_periods', true);
            $table->string('source_key', 100);
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->unique(['tenant_id', 'source_key']);
        });
        Schema::create('installments', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'receivable_id', 'receivables');
            $table->unsignedSmallInteger('number');
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->unique(['tenant_id', 'receivable_id', 'number'], 'installment_number_unique');
            $table->index(['tenant_id', 'due_on']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'customer_id', 'customers');
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('method', 20);
            $table->uuid('idempotency_key');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'reversed_at', 'paid_at'], 'payments_month_index');
        });
        Schema::create('payment_allocations', function (Blueprint $table) {
            $this->base($table);
            $this->link($table, 'payment_id', 'payments');
            $this->link($table, 'installment_id', 'installments');
            $table->decimal('amount', 12, 2);
            $table->unique(['tenant_id', 'payment_id', 'installment_id'], 'allocation_unique');
        });
    }

    public function down(): void
    {
        foreach (['payment_allocations', 'payments', 'installments', 'receivables', 'service_executions', 'schedule_blocks', 'appointment_events', 'appointment_items', 'appointments', 'plan_period_items', 'plan_periods', 'contract_events', 'contract_items', 'service_contracts'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['pix_key', 'pix_receiver', 'pix_city']));
    }
};
