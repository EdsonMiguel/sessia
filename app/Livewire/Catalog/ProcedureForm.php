<?php

namespace App\Livewire\Catalog;

use App\Models\Procedure;
use App\Modules\Catalog\DecimalAmount;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProcedureForm extends Component
{
    #[Locked]
    public ?int $procedureId = null;

    public string $name = '';

    public string $description = '';

    public string $duration_minutes = '';

    public string $default_price = '';

    public function mount(?int $procedureId = null): void
    {
        $this->procedureId = $procedureId;
        if ($procedureId !== null) {
            $procedure = Procedure::findOrFail($procedureId);
            Gate::authorize('update', $procedure);
            $this->name = $procedure->name;
            $this->description = $procedure->description ?? '';
            $this->duration_minutes = (string) $procedure->duration_minutes;
            $this->default_price = str_replace('.', ',', $procedure->default_price);
        } else {
            Gate::authorize('create', Procedure::class);
        }
    }

    public function save(): void
    {
        $procedure = $this->procedureId === null ? new Procedure : Procedure::findOrFail($this->procedureId);
        Gate::authorize($procedure->exists ? 'update' : 'create', $procedure->exists ? $procedure : Procedure::class);
        $this->name = trim($this->name);
        $this->default_price = trim($this->default_price);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:65535'],
            'default_price' => DecimalAmount::rules(),
        ]);
        $data['default_price'] = DecimalAmount::normalize($data['default_price']);
        $data['description'] = $data['description'] ?: null;
        $procedure->fill($data)->save();
        session()->flash('status', 'Procedimento salvo com sucesso.');
        $this->redirectRoute('procedures.index');
    }

    public function render()
    {
        Gate::authorize('viewAny', Procedure::class);

        return view('livewire.catalog.procedure-form');
    }
}
