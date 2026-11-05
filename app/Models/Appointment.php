<?php

namespace App\Models;

class Appointment extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public const STATUSES = ['scheduled' => 'Agendado', 'confirmed' => 'Confirmado', 'completed' => 'Realizado', 'cancelled' => 'Cancelado', 'no_show' => 'Falta'];

    protected function casts(): array
    {
        return ['address_snapshot' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'occupied_from' => 'immutable_datetime', 'occupied_until' => 'immutable_datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(AppointmentItem::class);
    }

    public function events()
    {
        return $this->hasMany(AppointmentEvent::class);
    }
}
