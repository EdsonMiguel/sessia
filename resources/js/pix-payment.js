import { createStaticPix, hasError } from 'pix-utils';

const ascii = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^\x20-\x7E]/g, ' ').replace(/\s+/g, ' ').trim();

export function whatsappPhone(value) {
    const raw = String(value ?? '').trim();
    const digits = raw.replace(/\D/g, '');
    if (raw.startsWith('+')) return /^[1-9]\d{7,14}$/.test(digits) ? digits : '';
    if (/^[1-9]\d{9,10}$/.test(digits)) return `55${digits}`;
    return /^55[1-9]\d{9,10}$/.test(digits) ? digits : '';
}

function normalizeKey(value) {
    let key = String(value ?? '').trim();
    if (/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(key) || /^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/.test(key)) key = key.replace(/\D/g, '');
    if (key.startsWith('+')) key = '+' + key.slice(1).replace(/[\s().-]/g, '');
    if (key.length > 77 || !/^(?:\d{11}|\d{14}|\+[1-9]\d{7,14}|[a-fA-F0-9]{8}(?:-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}|[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})$/.test(key)) {
        throw new Error('Confira a chave Pix nas configurações. Para telefone, informe +55 e DDD.');
    }
    return key;
}

export function buildPix(data, installment) {
    if (!installment || !/^(?:0|[1-9]\d{0,9})\.\d{2}$/.test(installment.amount) || installment.amount === '0.00') {
        throw new Error('Selecione uma parcela com saldo pendente.');
    }
    const key = normalizeKey(data.key);
    const merchantName = ascii(data.receiver).toUpperCase().slice(0, 25);
    const merchantCity = ascii(data.city).toUpperCase().slice(0, 15);
    if (!merchantName || !merchantCity) throw new Error('Preencha o nome e a cidade do recebedor nas configurações.');
    // The MAI field has 99 characters: GUI (18), key header (4), description header (4).
    const description = ascii(`${installment.reference} - ${data.description}`).slice(0, Math.max(0, 73 - key.length));
    // All balances are calculated in integer cents in PHP. Number is only the library's serialization boundary.
    const amount = Number(installment.amount);
    if (amount.toFixed(2) !== installment.amount) throw new Error('Não foi possível representar o valor do Pix.');
    const pix = createStaticPix({
        pixKey: key, merchantName, merchantCity, transactionAmount: amount,
        infoAdicional: description || undefined, txid: installment.txid,
    });
    if (hasError(pix)) throw new Error('Não foi possível gerar o Pix. Confira os dados do recebedor.');
    const code = pix.toBRCode();
    const message = code;
    const phone = whatsappPhone(data.phone);
    return { code, description, message, phone, url: `https://wa.me/${phone}?text=${encodeURIComponent(message)}` };
}

export function pixPayment(data) {
    return {
        selected: data.installments[0]?.id ?? '', code: '', description: '', whatsappUrl: '', phone: '', error: '', feedback: '',
        init() { this.generate(); },
        generate() {
            this.code = ''; this.description = ''; this.whatsappUrl = ''; this.error = ''; this.feedback = '';
            try {
                const result = buildPix(data, data.installments.find((i) => i.id === this.selected));
                this.code = result.code; this.description = result.description; this.whatsappUrl = result.url; this.phone = result.phone;
            } catch (error) { this.error = error.message; }
        },
        async copy() {
            if (!this.code) return;
            try {
                await navigator.clipboard.writeText(this.code);
                this.feedback = 'Pix Copia e Cola copiado.';
            } catch {
                this.$refs.code.focus(); this.$refs.code.select();
                // Laragon may use HTTP, where the modern Clipboard API is unavailable.
                try {
                    if (typeof document !== 'undefined' && document.execCommand?.('copy')) {
                        this.feedback = 'Pix Copia e Cola copiado.';
                        return;
                    }
                } catch { /* Keep the code selected for manual copying. */ }
                this.feedback = 'Selecione e copie o código destacado. No celular, toque e segure para copiar.';
            }
        },
    };
}
