<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use App\Models\ProcedurePackage;
use App\Models\Tenant;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    private function business(string $slug): Tenant
    {
        return Tenant::query()->select('id', 'name', 'slug', 'phone')->where('slug', $slug)->firstOrFail();
    }

    private function procedures(Tenant $business)
    {
        // Public access deliberately bypasses the authenticated scope only here.
        // Always constrain by the tenant resolved from the unique public slug.
        return Procedure::withoutGlobalScope('tenant')->where('tenant_id', $business->id)
            ->where('is_active', true)->where('show_in_catalog', true)
            ->select('id', 'name', 'description', 'duration_minutes', 'default_price');
    }

    public function show(string $slug)
    {
        $business = $this->business($slug);
        $procedures = $this->procedures($business)->orderBy('name')->orderBy('id')->paginate(12);
        $packages = $this->packages($business)->with([
            'items' => fn ($q) => $q->withoutGlobalScope('tenant')->where('tenant_id', $business->id)
                ->select('id', 'procedure_package_id', 'procedure_id', 'quantity'),
            'items.procedure' => fn ($q) => $q->withoutGlobalScope('tenant')->where('tenant_id', $business->id)->select('id', 'name'),
        ])->orderBy('name')->orderBy('id')->paginate(12, ['*'], 'packages_page');
        $procedures->appends(request()->only('packages_page'));
        $packages->appends(request()->only('page'));

        return view('catalog.show', compact('business', 'procedures', 'packages'));
    }

    private function packages(Tenant $business)
    {
        // Each relationship has its own tenant restriction: public visitors have no current tenant.
        return ProcedurePackage::withoutGlobalScope('tenant')->where('tenant_id', $business->id)
            ->where('is_active', true)->where('show_in_catalog', true)
            ->whereHas('items', fn ($q) => $q->withoutGlobalScope('tenant')->where('tenant_id', $business->id))
            ->whereDoesntHave('items', fn ($q) => $q->withoutGlobalScope('tenant')
                ->whereDoesntHave('procedure', fn ($p) => $p->withoutGlobalScope('tenant')
                    ->where('tenant_id', $business->id)->where('is_active', true)))
            ->select('id', 'name', 'description', 'total');
    }

    public function contact(Request $request, string $slug)
    {
        $business = $this->business($slug);
        abort_unless(preg_match('/^\+[1-9]\d{7,14}$/D', $business->phone ?? ''), 404);
        $isPackage = $request->has('package');
        abort_if($isPackage && $request->has('procedure'), 404);
        $id = $request->query($isPackage ? 'package' : 'procedure');
        abort_unless(is_string($id) && ctype_digit($id), 404);
        $selection = ($isPackage ? $this->packages($business) : $this->procedures($business))->findOrFail($id);
        $message = 'Olá! Gostaria de mais informações sobre o '.($isPackage ? 'pacote ' : 'serviço ').$selection->name.' de '.$business->name.'.';

        return redirect()->away('https://wa.me/'.substr($business->phone, 1).'?text='.rawurlencode($message));
    }
}
