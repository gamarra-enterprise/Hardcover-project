import assert from 'node:assert/strict';
import test from 'node:test';

import {
    cvvLength,
    detectBrand,
    documentValid,
    expiryValid,
    fakeToken,
    formatCardNumber,
    formatExpiry,
    luhnValid,
    parseExpiry,
} from '../../resources/js/card-utils.js';

test('luhn accepts the well known test numbers and rejects typos', () => {
    for (const ok of ['4111111111111111', '4111 1111 1111 1111', '5555555555554444', '378282246310005', '30569309025904', '4000000000000002']) {
        assert.equal(luhnValid(ok), true, ok);
    }
    for (const bad of ['4111111111111112', '1234567890123456', '411111111111', '', 'abcd', '41111111111111111111']) {
        assert.equal(luhnValid(bad), false, bad);
    }
});

test('the brand comes from the first digits', () => {
    assert.equal(detectBrand('4111'), 'visa');
    assert.equal(detectBrand('5555'), 'mastercard');
    assert.equal(detectBrand('2221'), 'mastercard');
    assert.equal(detectBrand('2720'), 'mastercard');
    assert.equal(detectBrand('3782'), 'amex');
    assert.equal(detectBrand('3414'), 'amex');
    assert.equal(detectBrand('3056'), 'diners');
    assert.equal(detectBrand('6011'), 'unknown');
    assert.equal(detectBrand(''), 'unknown');
});

test('the number is grouped as printed on the card', () => {
    assert.equal(formatCardNumber('4111111111111111'), '4111 1111 1111 1111');
    assert.equal(formatCardNumber('4111-1111 1111abcd1111'), '4111 1111 1111 1111');
    assert.equal(formatCardNumber('411111'), '4111 11');
    assert.equal(formatCardNumber('378282246310005'), '3782 822463 10005');
    assert.equal(formatCardNumber('41111111111111111111111'), '4111 1111 1111 1111 111');
    assert.equal(formatCardNumber(''), '');
});

test('american express has a four digit code and the others three', () => {
    assert.equal(cvvLength('amex'), 4);
    assert.equal(cvvLength('visa'), 3);
    assert.equal(cvvLength('unknown'), 3);
});

test('the expiry is read and formatted while typing', () => {
    assert.deepEqual(parseExpiry('1230'), { month: 12, year: 2030 });
    assert.deepEqual(parseExpiry('12/30'), { month: 12, year: 2030 });
    assert.equal(parseExpiry('1330'), null);
    assert.equal(parseExpiry('0030'), null);
    assert.equal(parseExpiry('123'), null);
    assert.equal(formatExpiry('1'), '1');
    assert.equal(formatExpiry('12'), '12');
    assert.equal(formatExpiry('123'), '12/3');
    assert.equal(formatExpiry('12/30'), '12/30');
    assert.equal(formatExpiry('123099'), '12/30');
});

test('a card is good until the end of its expiry month', () => {
    const now = new Date(2026, 9, 8); // 8 October 2026
    assert.equal(expiryValid('10/26', now), true);
    assert.equal(expiryValid('11/26', now), true);
    assert.equal(expiryValid('09/26', now), false);
    assert.equal(expiryValid('12/25', now), false);
    assert.equal(expiryValid('01/27', now), true);
    assert.equal(expiryValid('13/27', now), false);
    assert.equal(expiryValid('', now), false);
});

test('identity documents', () => {
    assert.equal(documentValid('DNI', '12345678'), true);
    assert.equal(documentValid('DNI', '1234567'), false);
    assert.equal(documentValid('DNI', '1234567a'), false);
    assert.equal(documentValid('CE', '001234567'), true);
    assert.equal(documentValid('CE', '12345'), false);
    assert.equal(documentValid('RUC', '20123456789'), false);
});

test('the stand-in token says nothing about the card but its outcome', () => {
    const approved = fakeToken('4111 1111 1111 1111');
    const rejected = fakeToken('4000 0000 0000 0002');

    assert.match(approved, /^FAKE-TOK-OK-[A-Z0-9]+$/);
    assert.match(rejected, /^FAKE-TOK-NO-[A-Z0-9]+$/);
    assert.ok(!approved.includes('4111'));
    assert.notEqual(fakeToken('4111111111111111'), fakeToken('4111111111111111'));
});
