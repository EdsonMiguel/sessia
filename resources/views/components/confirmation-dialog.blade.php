<dialog id="confirmation-dialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-message" class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-2xl border border-stone-200 bg-white p-5 text-stone-800 shadow-xl backdrop:bg-stone-900/50 sm:p-6">
    <h2 id="confirmation-title" class="text-lg font-semibold">Confirmar ação</h2>
    <p id="confirmation-message" class="mt-3 text-sm leading-relaxed text-stone-600"></p>
    <form method="dialog" class="mt-6 grid gap-3 sm:flex sm:justify-end">
        <button value="cancel" autofocus class="min-h-12 rounded-xl border border-stone-300 px-5 text-sm font-semibold hover:bg-stone-50">Cancelar</button>
        <button value="confirm" class="primary-button">Confirmar</button>
    </form>
</dialog>
