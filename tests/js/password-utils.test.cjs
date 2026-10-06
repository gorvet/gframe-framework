const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');
const { webcrypto } = require('node:crypto');

const source = readFileSync(join(__dirname, '../../resources/modules/password-utils/public/passwordUtils.js'), 'utf8');

function load(crypto = webcrypto) {
  const context = { window: { crypto }, Uint32Array, TextEncoder };
  runInNewContext(source, context);
  return context;
}

test('la validación del cliente coincide con el límite en bytes del servidor', () => {
  const { passwordValidate } = load();
  assert.equal(passwordValidate('1234567'), false);
  assert.equal(passwordValidate('12345678'), true);
  assert.equal(passwordValidate('abcdefgh'), true);
  assert.equal(passwordValidate('a'.repeat(72)), true);
  assert.equal(passwordValidate('a'.repeat(73)), false);
  assert.equal(passwordValidate('á'.repeat(36)), true);
  assert.equal(passwordValidate('á'.repeat(37)), false);
});

test('el generador usa aleatoriedad criptográfica y respeta la longitud', () => {
  const { generatePassword } = load();
  const password = generatePassword(18);
  assert.equal(password.length, 18);
  assert.match(password, /[a-z]/);
  assert.match(password, /[A-Z]/);
  assert.match(password, /[0-9]/);
  assert.match(password, /[^a-zA-Z0-9]/);
  assert.equal(generatePassword(73).length, 18);
});

test('el generador no produce contraseña sin API criptográfica', () => {
  const { generatePassword } = load(null);
  assert.equal(generatePassword(), null);
});

test('el medidor orientativo no decide la aceptación obligatoria', () => {
  const {evaluatePassword, passwordValidate} = load();
  assert.ok(evaluatePassword('abcdefgh', '') <= 2);
  assert.equal(passwordValidate('abcdefgh'), true);
  assert.equal(passwordValidate('áááá'), true);
  assert.ok(evaluatePassword('MiClaveSegura2026!', '') > 6);
});
