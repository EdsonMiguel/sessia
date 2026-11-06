<?php

namespace App\Livewire\Platform;

use App\Modules\Platform\ClearTenantData;
use App\Modules\Platform\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ClearData extends Component
{
    #[Locked]
    public int $settingsTenantId;

    public string $clearPassword = '';

    public string $clearConfirmation = '';

    public function mount(): void
    {
        $tenant = app(CurrentTenant::class)->get();
        Gate::authorize('update', $tenant);
        $this->settingsTenantId = $tenant->id;
    }

    public function clearData(ClearTenantData $action): void
    {
        $key = 'clear-tenant-data:'.auth()->id();
        try {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages(['clearPassword' => 'Muitas tentativas. Aguarde um minuto e tente novamente.']);
            }
            RateLimiter::hit($key, 60);
            $action->handle($this->settingsTenantId, $this->clearPassword, $this->clearConfirmation);
            RateLimiter::clear($key);
        } finally {
            $this->clearPassword = '';
        }
        $this->clearConfirmation = '';
        session()->flash('status', 'Os dados cadastrados do negócio foram apagados. Sua conta e as configurações foram mantidas.');
        $this->redirectRoute('account.show');
    }

    public function render()
    {
        $tenant = app(CurrentTenant::class)->get();
        Gate::authorize('update', $tenant);

        return view('livewire.platform.clear-data', ['businessName' => $tenant->name]);
    }
}
