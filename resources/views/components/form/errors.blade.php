@if($errors->any())
    <div role="alert" class="my-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Confira os dados para continuar.</p><ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
