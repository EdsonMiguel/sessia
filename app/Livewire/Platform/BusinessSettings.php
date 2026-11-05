<?php

namespace App\Livewire\Platform;

use App\Modules\Platform\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BusinessSettings extends Component
{
    public string $name = '';

    public string $timezone = '';

    public string $pix_key = '';

    public string $pix_receiver = '';

    public string $pix_city = '';

    public function mount(CurrentTenant $current): void
    {
        $tenant = $current->get();
        Gate::authorize('update', $tenant);
        $this->name = $tenant->name;
        $this->timezone = $tenant->timezone;
        $this->pix_key = $tenant->pix_key ?? '';
        $this->pix_receiver = $tenant->pix_receiver ?? '';
        $this->pix_city = $tenant->pix_city ?? '';
    }

    public function save(CurrentTenant $current): void
    {
        $tenant = $current->get();
        Gate::authorize('update', $tenant);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all', 'max:64'],
            'pix_key' => ['nullable', 'string', 'max:140'],
            'pix_receiver' => ['nullable', 'required_with:pix_key', 'string', 'max:120'],
            'pix_city' => ['nullable', 'required_with:pix_key', 'string', 'max:120'],
        ]);
        $tenant->update($data);
        session()->flash('status', 'Dados do negócio atualizados.');
    }

    public function render()
    {
        return view('livewire.platform.business-settings');
    }
}
