// The card form of the payment page.
//
// The customer sees one ordinary card form, but the card number, expiry and security code are typed
// into fields that the payment gateway serves inside the page. The browser turns them into a
// one-time token and sends only that token to the shop. In development, with the stand-in gateway,
// plain fields are used instead and the card still stays in the browser: only a fake token leaves.

import { cvvLength, detectBrand, documentValid, expiryValid, fakeToken, formatCardNumber, formatExpiry, luhnValid } from './card-utils.js';

const form = document.getElementById('card-form');

if (form) {
    init(form);
}

function init(form) {
    const config = { provider: form.dataset.provider, publicKey: form.dataset.publicKey, endpoint: form.dataset.endpoint };
    const field = (id) => document.getElementById(id);
    const error = field('card-error');
    const button = field('card-submit');
    const buttonLabel = button.textContent;

    const show = (message) => {
        error.textContent = message;
        error.hidden = !message;
        if (message) error.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
    };

    let card;
    try {
        card = config.provider === 'mercadopago' ? gatewayFields(config) : standInFields(form);
    } catch (e) {
        show(e.message);
        button.disabled = true;

        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        show('');

        const holder = field('card-holder').value.trim();
        const type = field('card-doc-type').value;
        const number = field('card-doc-number').value.trim();
        const email = field('card-email').value.trim();

        if (holder.length < 3) return show('Escribe el nombre y los apellidos del titular, como figuran en la tarjeta.');
        if (!documentValid(type, number)) return show(type === 'DNI' ? 'El DNI debe tener 8 dígitos.' : 'Revisa el número de tu carné de extranjería.');
        if (!/^\S+@\S+\.\S+$/.test(email)) return show('Escribe un correo electrónico válido.');

        button.disabled = true;
        button.textContent = 'Procesando…';

        try {
            const token = await card.tokenize({ cardholderName: holder, identificationType: type, identificationNumber: number });

            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    token: token.token,
                    payment_method_id: token.paymentMethodId,
                    issuer_id: token.issuerId ?? null,
                    installments: 1,
                    email,
                    identification_type: type,
                    identification_number: number,
                    cardholder_name: holder,
                }),
            });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.status === 'ok') {
                window.location.assign(result.redirect);

                return;
            }

            const firstValidation = result.errors ? Object.values(result.errors)[0]?.[0] : null;
            show(result.message ?? firstValidation ?? 'No pudimos procesar el pago. Inténtalo de nuevo.');
        } catch (e) {
            show(Array.isArray(e) ? 'Revisa los datos de tu tarjeta: número, vencimiento y código de seguridad.' : (e.message ?? 'No pudimos procesar el pago. Inténtalo de nuevo.'));
        }

        button.disabled = false;
        button.textContent = buttonLabel;
    });
}

/** Real gateway: its secure fields are mounted inside our containers, and it makes the token. */
function gatewayFields(config) {
    if (typeof window.MercadoPago !== 'function' || !config.publicKey) {
        throw new Error('No pudimos cargar el formulario de pago seguro. Recarga la página o prueba con otro medio de pago.');
    }

    const mp = new window.MercadoPago(config.publicKey, { locale: 'es-PE' });
    const style = { color: '#0d100c', fontSize: '16px' };

    const number = mp.fields.create('cardNumber', { placeholder: '1234 1234 1234 1234', style }).mount('card-number');
    mp.fields.create('expirationDate', { placeholder: 'MM/AA', style }).mount('card-expiry');
    mp.fields.create('securityCode', { placeholder: 'CVV', style }).mount('card-cvv');

    let method = null;
    let issuer = null;

    // When the first digits are known the gateway tells which method and bank the card is.
    number.on('binChange', async ({ bin }) => {
        method = null;
        issuer = null;

        if (!bin) return;

        try {
            const { results } = await mp.getPaymentMethods({ bin });
            method = results?.[0] ?? null;

            if (method) {
                const issuers = await mp.getIssuers({ paymentMethodId: method.id, bin });
                issuer = issuers?.[0]?.id ?? method.issuer?.id ?? null;
            }
        } catch {
            // Without a method the token is not made and the customer is asked to check the number.
        }
    });

    return {
        async tokenize(identity) {
            if (!method) throw new Error('Revisa el número de tu tarjeta.');

            const token = await mp.fields.createCardToken(identity);

            return { token: token.id, paymentMethodId: method.id, issuerId: issuer ? String(issuer) : null };
        },
    };
}

/** Development only: plain fields whose content never leaves the browser; a fake token does. */
function standInFields(form) {
    const number = document.getElementById('card-number-input');
    const expiry = document.getElementById('card-expiry-input');
    const cvv = document.getElementById('card-cvv-input');
    const brand = document.getElementById('card-brand');

    number.addEventListener('input', () => {
        number.value = formatCardNumber(number.value);
        const kind = detectBrand(number.value);
        brand.textContent = kind === 'unknown' ? '' : kind.toUpperCase();
        cvv.maxLength = cvvLength(kind);
    });
    expiry.addEventListener('input', () => {
        expiry.value = formatExpiry(expiry.value);
    });
    cvv.addEventListener('input', () => {
        cvv.value = cvv.value.replace(/\D/g, '');
    });

    return {
        async tokenize() {
            if (!luhnValid(number.value)) throw new Error('Revisa el número de tu tarjeta.');
            if (!expiryValid(expiry.value)) throw new Error('Revisa la fecha de vencimiento.');
            if (cvv.value.length !== cvvLength(detectBrand(number.value))) throw new Error('Revisa el código de seguridad (CVV).');

            const token = fakeToken(number.value);
            const kind = detectBrand(number.value);

            // The typed card is wiped as soon as the token exists.
            number.value = expiry.value = cvv.value = '';

            return { token, paymentMethodId: kind === 'unknown' ? 'visa' : kind, issuerId: null };
        },
    };
}
