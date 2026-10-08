// Runs the real card-form.js in a simulated page and checks what it would send to the shop.
import assert from 'node:assert/strict';
import test from 'node:test';

import { JSDOM } from 'jsdom';

const PAGE = `<!doctype html><html><head><meta name="csrf-token" content="csrf-123"></head><body>
<form id="card-form" data-provider="fake" data-public-key="" data-endpoint="https://shop.test/pedido/HB-1/tarjeta?signature=abc">
  <input id="card-number-input"><span id="card-brand"></span>
  <input id="card-expiry-input"><input id="card-cvv-input">
  <input id="card-holder" value="Ana Quispe Rojas">
  <select id="card-doc-type"><option value="DNI">DNI</option><option value="CE">CE</option></select>
  <input id="card-doc-number" value="12345678">
  <input id="card-email" value="ana@example.com">
  <p id="card-error" hidden></p>
  <button id="card-submit" type="submit">Pagar S/ 50.00</button>
</form></body></html>`;

let counter = 0;

/** A fresh page, a fresh fetch, and the script loaded on top of them. */
async function load({ answer = { ok: true, body: { status: 'ok', redirect: '#paid' } } } = {}) {
    const dom = new JSDOM(PAGE, { url: 'https://shop.test/pedido/HB-1/tarjeta', pretendToBeVisual: true });
    const calls = [];

    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    globalThis.fetch = async (url, options) => {
        calls.push({ url, options });

        return { ok: answer.ok, json: async () => answer.body };
    };

    // A new query string makes node load the module again for each test.
    await import(`../../resources/js/card-form.js?run=${++counter}`);

    const $ = (id) => dom.window.document.getElementById(id);
    const type = (id, value) => {
        $(id).value = value;
        $(id).dispatchEvent(new dom.window.Event('input', { bubbles: true }));
    };
    const submit = async () => {
        $('card-form').dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true }));
        await new Promise((resolve) => setTimeout(resolve, 20));
    };

    return { dom, calls, $, type, submit };
}

test('the card is typed with spaces and the brand shows up', async () => {
    const { $, type } = await load();

    type('card-number-input', '4111111111111111');

    assert.equal($('card-number-input').value, '4111 1111 1111 1111');
    assert.equal($('card-brand').textContent, 'VISA');
    assert.equal($('card-cvv-input').maxLength, 3);
});

test('what is sent to the shop is a token and who pays, never the card number or the security code', async () => {
    const { calls, type, submit, dom } = await load();

    type('card-number-input', '4111 1111 1111 1111');
    type('card-expiry-input', '1230');
    type('card-cvv-input', '123');
    await submit();

    assert.equal(calls.length, 1);
    const { url, options } = calls[0];
    assert.equal(url, 'https://shop.test/pedido/HB-1/tarjeta?signature=abc');
    assert.equal(options.method, 'POST');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-123');

    const body = JSON.parse(options.body);
    assert.match(body.token, /^FAKE-TOK-OK-[A-Z0-9]+$/);
    assert.deepEqual(Object.keys(body).sort(), ['cardholder_name', 'email', 'identification_number', 'identification_type', 'installments', 'issuer_id', 'payment_method_id', 'token']);

    // No field, name or value of the request carries any part of the card.
    const everything = options.body + JSON.stringify(options.headers) + url;
    assert.ok(!/4111/.test(everything), 'the card number leaked');
    assert.ok(!everything.replace(/[\s-]/g, '').includes('41111111'), 'the card number leaked');
    assert.ok(!/"(card_number|number|cvv|cvc|security_code)"/i.test(options.body));
    assert.ok(!/"cvv"|:"123"/.test(options.body));
    assert.equal(body.payment_method_id, 'visa');
    assert.equal(body.identification_number, '12345678');

    // The approved answer sends the customer on.
    assert.equal(dom.window.location.hash, '#paid');
});

test('the typed card is wiped from the page as soon as the token exists', async () => {
    const { $, type, submit } = await load({ answer: { ok: false, body: { status: 'rejected', message: 'Tu tarjeta no tiene fondos suficientes.' } } });

    type('card-number-input', '4000 0000 0000 0002');
    type('card-expiry-input', '1230');
    type('card-cvv-input', '123');
    await submit();

    assert.equal($('card-number-input').value, '');
    assert.equal($('card-expiry-input').value, '');
    assert.equal($('card-cvv-input').value, '');
});

test('a rejected card shows the reason and lets the customer try again', async () => {
    const { $, calls, type, submit } = await load({ answer: { ok: true, body: { status: 'rejected', message: 'Tu tarjeta no tiene fondos suficientes.' } } });

    type('card-number-input', '4000000000000002');
    type('card-expiry-input', '1230');
    type('card-cvv-input', '123');
    await submit();

    assert.equal($('card-error').hidden, false);
    assert.equal($('card-error').textContent, 'Tu tarjeta no tiene fondos suficientes.');
    assert.equal($('card-submit').disabled, false);
    assert.equal($('card-submit').textContent, 'Pagar S/ 50.00');
    assert.match(JSON.parse(calls[0].options.body).token, /^FAKE-TOK-NO-/);
});

test('mistakes are caught in the page before anything is sent', async () => {
    const cases = [
        ['a number that fails the check', { number: '4111 1111 1111 1112', expiry: '1230', cvv: '123' }, /número de tu tarjeta/],
        ['an expired card', { number: '4111 1111 1111 1111', expiry: '0120', cvv: '123' }, /vencimiento/],
        ['a short security code', { number: '4111 1111 1111 1111', expiry: '1230', cvv: '12' }, /seguridad/],
    ];

    for (const [name, card, message] of cases) {
        const { $, calls, type, submit } = await load();
        type('card-number-input', card.number);
        type('card-expiry-input', card.expiry);
        type('card-cvv-input', card.cvv);
        await submit();

        assert.equal(calls.length, 0, name);
        assert.match($('card-error').textContent, message, name);
    }
});

test('the holder, the document and the email are checked too', async () => {
    const cases = [
        ['holder', 'card-holder', 'A', /titular/],
        ['dni', 'card-doc-number', '1234', /DNI/],
        ['email', 'card-email', 'no-es-un-correo', /correo/],
    ];

    for (const [name, id, value, message] of cases) {
        const { $, calls, type, submit } = await load();
        type('card-number-input', '4111111111111111');
        type('card-expiry-input', '1230');
        type('card-cvv-input', '123');
        $(id).value = value;
        await submit();

        assert.equal(calls.length, 0, name);
        assert.match($('card-error').textContent, message, name);
    }
});

test('an error of the server is shown, and so is a failed connection', async () => {
    const withMessage = await load({ answer: { ok: false, body: { message: 'Hiciste demasiados intentos.' } } });
    withMessage.type('card-number-input', '4111111111111111');
    withMessage.type('card-expiry-input', '1230');
    withMessage.type('card-cvv-input', '123');
    await withMessage.submit();
    assert.equal(withMessage.$('card-error').textContent, 'Hiciste demasiados intentos.');

    const validation = await load({ answer: { ok: false, body: { errors: { email: ['El campo correo electrónico debe ser un correo electrónico válido.'] } } } });
    validation.type('card-number-input', '4111111111111111');
    validation.type('card-expiry-input', '1230');
    validation.type('card-cvv-input', '123');
    await validation.submit();
    assert.match(validation.$('card-error').textContent, /correo electrónico válido/);
});

test('the real gateway is refused politely when its script did not load', async () => {
    const dom = new JSDOM(PAGE.replace('data-provider="fake"', 'data-provider="mercadopago"').replace('data-public-key=""', 'data-public-key="APP_USR-key"'), { url: 'https://shop.test/' });
    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    delete dom.window.MercadoPago;

    await import(`../../resources/js/card-form.js?run=${++counter}`);

    const error = dom.window.document.getElementById('card-error');
    assert.equal(error.hidden, false);
    assert.match(error.textContent, /No pudimos cargar el formulario de pago seguro/);
    assert.equal(dom.window.document.getElementById('card-submit').disabled, true);
});
