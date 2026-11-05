@if(session('status'))
    <p role="status" class="my-5 rounded-xl border border-teal-100 bg-teal-50 p-4 text-sm text-teal-900">{{ session('status') }}</p>
@endif
