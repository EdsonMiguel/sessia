<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['customer_id', 'postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'reference', 'is_primary'])]
class CustomerAddress extends TenantRecord
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getFormattedAttribute(): string
    {
        return implode(', ', array_filter([$this->street, $this->number, $this->complement, $this->neighborhood, $this->city.' - '.$this->state, $this->postal_code]));
    }

    public function getMapsUrlAttribute(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->formatted);
    }
}
