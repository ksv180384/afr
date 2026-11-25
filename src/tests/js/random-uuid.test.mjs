import assert from 'node:assert/strict';
import test from 'node:test';
import { webcrypto } from 'node:crypto';
import { randomUuid } from '../../resources/js/App/Learning/randomUuid.js';

test('generates distinct v4 UUIDs when HTTP exposes getRandomValues only', (context) => {
  const originalCrypto = Object.getOwnPropertyDescriptor(globalThis, 'crypto');
  Object.defineProperty(globalThis, 'crypto', {
    configurable: true,
    value: { getRandomValues: webcrypto.getRandomValues.bind(webcrypto) },
  });
  context.after(() => {
    if (originalCrypto) Object.defineProperty(globalThis, 'crypto', originalCrypto);
    else delete globalThis.crypto;
  });

  const first = randomUuid();
  const second = randomUuid();
  const v4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

  assert.match(first, v4);
  assert.match(second, v4);
  assert.notEqual(first, second);
});
