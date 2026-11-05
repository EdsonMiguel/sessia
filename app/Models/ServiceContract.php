<?php

namespace App\Models;

class ServiceContract extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public const TYPES = ['single' => 'Avulso', 'plan' => 'Plano mensal', 'package' => 'Pacote'];

    public const STATUSES = ['active' => 'Ativo', 'paused' => 'Pausado', 'ended' => 'Encerrado', 'completed' => 'Execução concluída'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(ContractItem::class);
    }

    public function periods()
    {
        return $this->hasMany(PlanPeriod::class);
    }

    public function receivables()
    {
        return $this->hasMany(Receivable::class);
    }

    public function events()
    {
        return $this->hasMany(ContractEvent::class);
    }
}
