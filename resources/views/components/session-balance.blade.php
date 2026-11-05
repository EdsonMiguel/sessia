@props(['item'])
@php($balance = \App\Modules\Contract\SessionBalance::for($item))
<p class="mt-2 text-sm text-stone-600">{{ $balance['contracted'] }} contratadas · {{ $balance['reserved'] }} reservadas · {{ $balance['performed'] }} realizadas · <strong class="text-teal-900">{{ $balance['available'] }} disponíveis</strong></p>
