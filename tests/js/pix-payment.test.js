import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildPix, whatsappPhone, pixPayment } from '../../resources/js/pix-payment.js';

const data = { key: 'pix@example.test', receiver: 'Estética de Teste', city: 'São Paulo', phone: '(11) 99999-1234', description: 'Avulso #1 · Drenagem & Massagem' };
const installment = { id: '1', number: 1, due: '10/12/2026', amount: '99.99', formattedAmount: 'R$ 99,99', reference: 'Cobranca 1 Parcela 1', txid: 'S1P1' };

function tags(code) {
    const result = {};
    for (let i = 0; i < code.length;) {
        const size = Number(code.slice(i + 2, i + 4));
        assert.ok(size > 0 && i + 4 + size <= code.length);
        result[code.slice(i, i + 2)] = code.slice(i + 4, i + 4 + size);
        i += 4 + size;
    }
    return result;
}

test('produces an EMV Pix with exact cents, bounded fields and a valid CRC', () => {
    for (const amount of ['0.01', '99.99', '100.10', '9999999999.99']) {
        const { code } = buildPix(data, { ...installment, amount });
        const fields = tags(code);
        assert.equal(fields['54'], amount);
        assert.equal(fields['53'], '986');
        assert.equal(fields['59'], 'ESTETICA DE TESTE');
        assert.equal(tags(fields['26'])['01'], data.key);
        assert.equal(tags(fields['62'])['05'], 'S1P1');
        let crc = 0xffff;
        for (const byte of Buffer.from(code.slice(0, -4))) {
            crc ^= byte << 8;
            for (let bit = 0; bit < 8; bit++) crc = ((crc << 1) ^ ((crc & 0x8000) ? 0x1021 : 0)) & 0xffff;
        }
        assert.equal(code.slice(-4), crc.toString(16).toUpperCase().padStart(4, '0'));
    }
});

test('limits description by key size and shares only the Pix code through WhatsApp', () => {
    const input = { ...data, key: 'a'.repeat(60) + '@example.test', description: 'Descrição longa çã '.repeat(30) };
    const result = buildPix(input, installment);
    assert.ok(tags(result.code)['26'].length <= 99);
    const url = new URL(result.url);
    assert.equal(url.origin, 'https://wa.me');
    assert.equal(url.pathname, '/5511999991234');
    assert.equal(url.searchParams.get('text'), result.code);
    assert.equal(result.message, result.code);
    assert.equal(tags(result.code)['54'], '99.99');
});

test('rejects absent or invalid payment data instead of generating an amountless Pix', () => {
    for (const amount of ['0.00', '-1.00', '1.001', '1e2', 'NaN', '10000000000.00']) {
        assert.throws(() => buildPix(data, { ...installment, amount }));
    }
    assert.throws(() => buildPix(data, null));
    assert.throws(() => buildPix({ ...data, key: 'invalid' }, installment));
    assert.throws(() => buildPix({ ...data, receiver: '' }, installment));
    assert.throws(() => buildPix({ ...data, city: '' }, installment));
});

test('normalizes phones without duplicating DDI and allows choosing a contact for incomplete numbers', () => {
    assert.equal(whatsappPhone('+55 (11) 99999-1234'), '5511999991234');
    assert.equal(whatsappPhone('5511999991234'), '5511999991234');
    assert.equal(whatsappPhone('55999991234'), '5555999991234');
    assert.equal(whatsappPhone('+1 202 555 0123'), '12025550123');
    assert.equal(whatsappPhone('9999-1234'), '');
    assert.ok(buildPix({ ...data, phone: '' }, installment).url.startsWith('https://wa.me/?text='));
});

test('switching installments rebuilds the code and clears stale links on error', () => {
    const state = pixPayment({ ...data, installments: [installment, { ...installment, id: '2', amount: '20.00', txid: 'S1P2' }] });
    state.init();
    state.selected = '2';
    state.generate();
    assert.equal(tags(state.code)['54'], '20.00');
    state.selected = 'unknown';
    state.generate();
    assert.equal(state.code, '');
    assert.equal(state.whatsappUrl, '');
    assert.ok(state.error);
});

test('copy failure selects the payload for manual copying', async () => {
    const state = pixPayment({ ...data, installments: [installment] });
    state.init();
    let selected = false;
    state.$refs = { code: { focus() {}, select() { selected = true; } } };
    await state.copy();
    assert.equal(selected, true);
    assert.ok(state.feedback.includes('copie'));
});
