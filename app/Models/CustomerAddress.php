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
        return ['is_primary' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'geocoded_at' => 'datetime'];
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

    public function getGeocodingWarningAttribute(): ?string
    {
        return match ($this->geocoding_status) {
            'not_configured' => 'Coordenadas pendentes: a integração com o Google ainda não foi configurada.',
            'configuration_error' => 'Coordenadas pendentes: verifique a configuração da chave e da Geocoding API do Google.',
            'not_found' => 'O Google não identificou um local único para este endereço. Revise os dados e salve novamente.',
            'unavailable' => 'Não foi possível consultar as coordenadas no Google. Salve o endereço novamente para tentar outra vez.',
            default => null,
        };
    }
}
