// Helpers of the card form. They are pure functions so they can be tested without a browser.
// With the real gateway the card number and code are typed into the gateway's own fields and never
// pass through these helpers; they only serve the stand-in used in development.

/** Luhn check: the checksum that every real card number satisfies. */
export function luhnValid(number) {
    const digits = String(number).replace(/[\s-]/g, '');

    if (!/^\d{13,19}$/.test(digits)) {
        return false;
    }

    let sum = 0;
    [...digits].reverse().forEach((digit, index) => {
        let n = Number(digit) * (index % 2 === 1 ? 2 : 1);
        sum += n > 9 ? n - 9 : n;
    });

    return sum % 10 === 0;
}

/** Guess the brand from the first digits. */
export function detectBrand(number) {
    const digits = String(number).replace(/\D/g, '');

    if (/^4/.test(digits)) return 'visa';
    if (/^(5[1-5]|2(2[2-9][1-9]|2[3-9]|[3-6]|7[01]|720))/.test(digits)) return 'mastercard';
    if (/^3[47]/.test(digits)) return 'amex';
    if (/^3(0[0-5]|[68])/.test(digits)) return 'diners';

    return 'unknown';
}

/** Group the digits the way they are printed: 4-4-4-4, or 4-6-5 for American Express. */
export function formatCardNumber(value) {
    const digits = String(value).replace(/\D/g, '');
    const amex = detectBrand(digits) === 'amex';
    const groups = amex ? [4, 6, 5] : [4, 4, 4, 4, 3];
    const out = [];
    let position = 0;

    for (const size of groups) {
        if (position >= digits.length) break;
        out.push(digits.slice(position, position + size));
        position += size;
    }

    return out.join(' ').slice(0, amex ? 17 : 23);
}

/** Digits the security code has: 4 for American Express, 3 for the rest. */
export function cvvLength(brand) {
    return brand === 'amex' ? 4 : 3;
}

/** Turn "1230" or "12/30" into { month: 12, year: 2030 }, or null if it is not a date. */
export function parseExpiry(value) {
    const digits = String(value).replace(/\D/g, '');

    if (digits.length !== 4) {
        return null;
    }

    const month = Number(digits.slice(0, 2));
    const year = 2000 + Number(digits.slice(2));

    return month >= 1 && month <= 12 ? { month, year } : null;
}

/** "12/30" while typing: the slash goes in by itself. */
export function formatExpiry(value) {
    const digits = String(value).replace(/\D/g, '').slice(0, 4);

    return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
}

/** A card is good until the end of its expiry month. */
export function expiryValid(value, now = new Date()) {
    const expiry = parseExpiry(value);

    if (!expiry) {
        return false;
    }

    const currentMonth = now.getFullYear() * 12 + now.getMonth() + 1;

    return expiry.year * 12 + expiry.month >= currentMonth;
}

/** Peruvian identity documents: DNI has 8 digits, carné de extranjería 9 to 12 letters or digits. */
export function documentValid(type, number) {
    const value = String(number).trim();

    if (type === 'DNI') return /^\d{8}$/.test(value);
    if (type === 'CE') return /^[A-Za-z0-9]{9,12}$/.test(value);

    return false;
}

/**
 * The stand-in gateway's token, made in the browser so the card never leaves it. In development the
 * test card 4000 0000 0000 0002 is rejected and every other valid card is approved.
 */
export function fakeToken(number) {
    const digits = String(number).replace(/\D/g, '');
    const outcome = digits === '4000000000000002' ? 'NO' : 'OK';
    const random = Math.random().toString(36).slice(2, 10).toUpperCase();

    return `FAKE-TOK-${outcome}-${random}`;
}
