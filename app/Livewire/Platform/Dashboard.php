<?php

namespace App\Livewire\Platform;

use App\Models\Appointment;
use App\Models\Installment;
use App\Models\Payment;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        Gate::authorize('viewAny', Appointment::class);
        $now = now(app(CurrentTenant::class)->get()->timezone)->toImmutable();
        $base = Appointment::whereIn('status', ['scheduled', 'confirmed', 'completed']);
        $todayCount = (clone $base)->where('starts_at', '>=', $now->startOfDay()->utc())->where('starts_at', '<', $now->addDay()->startOfDay()->utc())->count();
        $weekCount = (clone $base)->where('starts_at', '>=', $now->startOfWeek()->utc())->where('starts_at', '<', $now->startOfWeek()->addWeek()->utc())->count();
        $received = Payment::whereNull('reversed_at')->where('paid_at', '>=', $now->startOfMonth()->utc())->where('paid_at', '<', $now->startOfMonth()->addMonth()->utc())->get()->sum(fn ($p) => DecimalAmount::cents($p->amount));
        $installments = Installment::with('allocations.payment')->get();
        $pending = $installments->sum(fn ($i) => $i->remainingCents());
        $overdueCount = $installments->filter(fn ($i) => $i->overdue())->count();
        $next = Appointment::with('customer')->whereIn('status', ['scheduled', 'confirmed'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get();

        return view('livewire.platform.dashboard', compact('todayCount', 'weekCount', 'received', 'pending', 'overdueCount', 'next'));
    }
}
