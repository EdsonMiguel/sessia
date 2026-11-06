let pending = false;
let confirmedTarget = null;

function askForConfirmation(event, target, run) {
    if (target === confirmedTarget) return;

    event.preventDefault();
    event.stopImmediatePropagation();

    const dialog = document.getElementById('confirmation-dialog');
    if (!dialog || pending) return;

    dialog.querySelector('#confirmation-message').textContent = target.dataset.confirm;
    dialog.returnValue = '';
    dialog.showModal();
    pending = true;

    dialog.addEventListener('close', () => {
        pending = false;
        if (dialog.returnValue !== 'confirm' || !target.isConnected) return;

        confirmedTarget = target;
        try {
            run();
        } finally {
            confirmedTarget = null;
        }
    }, { once: true });
}

window.addEventListener('click', (event) => {
    const button = event.target.closest?.('button[data-confirm]');
    if (button) askForConfirmation(event, button, () => button.click());
}, true);

window.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('form[data-confirm]')) {
        askForConfirmation(event, form, () => form.requestSubmit(event.submitter ?? undefined));
    }
}, true);
