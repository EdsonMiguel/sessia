<?php

namespace App\Livewire\Catalog;

use App\Models\Procedure;
use App\Models\ProcedurePackage;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\TenantTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PackageForm extends Component
{
    #[Locked]
    public ?int $packageId = null;

    public string $name = '';

    public string $description = '';

    public string $total = '';

    public bool $show_in_catalog = false;

    public array $items = [['procedure_id' => '', 'quantity' => '1']];

    public function mount(?int $packageId = null): void
    {
        $this->packageId = $packageId;
        if ($packageId === null) {
            Gate::authorize('create', ProcedurePackage::class);

            return;
        }
        $package = ProcedurePackage::with('items')->findOrFail($packageId);
        Gate::authorize('update', $package);
        $this->name = $package->name;
        $this->description = $package->description ?? '';
        $this->total = str_replace('.', ',', $package->total);
        $this->show_in_catalog = $package->show_in_catalog;
        $this->items = $package->items->map(fn ($item) => [
            'procedure_id' => (string) $item->procedure_id, 'quantity' => (string) $item->quantity,
        ])->all();
    }

    public function addItem(): void
    {
        if (count($this->items) < 30) {
            $this->items[] = ['procedure_id' => '', 'quantity' => '1'];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->total = trim($this->total);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'total' => DecimalAmount::rules(),
            'show_in_catalog' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.procedure_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        TenantTransaction::run(function () use ($data) {
            $package = $this->packageId === null ? new ProcedurePackage : ProcedurePackage::findOrFail($this->packageId);
            Gate::authorize($package->exists ? 'update' : 'create', $package->exists ? $package : ProcedurePackage::class);
            $available = Procedure::where('is_active', true)->whereIn('id', array_column($data['items'], 'procedure_id'))->pluck('id');
            foreach ($data['items'] as $index => $item) {
                if (! $available->contains($item['procedure_id'])) {
                    throw ValidationException::withMessages(["items.$index.procedure_id" => 'Selecione um procedimento ativo deste negócio.']);
                }
            }
            $package->fill([
                'name' => $data['name'], 'description' => $data['description'] ?: null,
                'total' => DecimalAmount::normalize($data['total']), 'show_in_catalog' => $data['show_in_catalog'],
            ])->save();
            $package->items()->delete();
            foreach ($data['items'] as $item) {
                $package->items()->create(['procedure_id' => $item['procedure_id'], 'quantity' => $item['quantity']]);
            }
        });
        session()->flash('status', 'Pacote salvo. Contratações anteriores permanecem com os dados acordados.');
        $this->redirectRoute('packages.index');
    }

    public function render()
    {
        Gate::authorize('viewAny', ProcedurePackage::class);

        return view('livewire.catalog.package-form', [
            'procedures' => Procedure::orderBy('name')->get(),
        ]);
    }
}
