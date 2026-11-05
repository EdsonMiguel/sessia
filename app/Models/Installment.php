<?php

namespace App\Models;

use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\CurrentTenant;

class Installment extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public const STATUSES = ['pending' => 'Pendente', 'partial' => 'Parcial', 'paid' => 'Pago', 'overdue' => 'Atrasado'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_on' => 'immutable_date'];
    }

    public function receivable()
    {
        return $this->belongsTo(Receivable::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paidCents(): int
    {
        $this->loadMissing('allocations.payment');

        return $this->allocations->filter(fn ($allocation) => $allocation->payment->reversed_at === null)->sum(fn ($allocation) => DecimalAmount::cents($allocation->amount));
    }

    public function remainingCents(): int
    {
        return DecimalAmount::cents($this->amount) - $this->paidCents();
    }

    public function overdue(): bool
    {
        return $this->remainingCents() > 0 && $this->due_on->toDateString() < now(app(CurrentTenant::class)->get()->timezone)->toDateString();
    }

    public function status(): string
    {
        if ($this->remainingCents() === 0) {
            return 'paid';
        }
        if ($this->paidCents() > 0) {
            return 'partial';
        }

        return $this->overdue() ? 'overdue' : 'pending';
    }
}
