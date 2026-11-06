<?php

namespace App\Livewire\Platform;

use App\Modules\Platform\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BusinessSettings extends Component
{
    public string $name = '';

    public string $timezone = '';

    public string $slug = '';

    public string $phone = '';

    public string $pix_key = '';

    public string $pix_receiver = '';

    public string $pix_city = '';

    public function mount(CurrentTenant $current): void
    {
        $tenant = $current->get();
        Gate::authorize('update', $tenant);

        $this->name = $tenant->name;
        $this->timezone = $tenant->timezone;
        $this->slug = $tenant->slug ?? '';
        $this->phone = $tenant->phone ?? '';
        $this->pix_key = $tenant->pix_key ?? '';
        $this->pix_receiver = $tenant->pix_receiver ?? '';
        $this->pix_city = $tenant->pix_city ?? '';
    }

    public function save(CurrentTenant $current): void
    {
        $tenant = $current->get();
        Gate::authorize('update', $tenant);
        $this->slug = strtolower(trim($this->slug));
        $this->phone = preg_replace('/[\s().-]/', '', trim($this->phone));
        if (preg_match('/^[1-9]\d{9,10}$/D', $this->phone)) {
            $this->phone = '+55'.$this->phone;
        } elseif (preg_match('/^55\d{10,11}$/D', $this->phone)) {
            $this->phone = '+'.$this->phone;
        }
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all', 'max:64'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'phone' => ['nullable', 'required_with:slug', 'string', 'regex:/^\+[1-9]\d{7,14}$/D'],
            'pix_key' => ['nullable', 'string', 'max:140'],
            'pix_receiver' => ['nullable', 'required_with:pix_key', 'string', 'max:120'],
            'pix_city' => ['nullable', 'required_with:pix_key', 'string', 'max:120'],
        ]);
        $data['slug'] = $data['slug'] ?: null;
        $data['phone'] = $data['phone'] ?: null;
        $tenant->update($data);
        session()->flash('status', 'Dados do negócio atualizados.');
    }

    public function render()
    {
        $tenant = app(CurrentTenant::class)->get();

        Gate::authorize('update', $tenant);

        return view('livewire.platform.business-settings', [
            'catalogUrl' => $tenant->slug ? route('catalog.show', $tenant->slug) : null,
            'businessName' => $tenant->name,
        ]);
    }
}
