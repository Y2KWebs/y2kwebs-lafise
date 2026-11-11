import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const script = await readFile(new URL('../assets/verify.js', import.meta.url), 'utf8');
const messages = Object.fromEntries(['authenticationIncomplete', 'declined', 'pending', 'retry', 'challenge', 'checking', 'unavailable'].map(key => [key, `Traducción: ${key} — ñ`]));

async function render(result, fail = false) {
  const message = {};
  const frame = { hidden: true, name: 'bank' };
  const children = [];
  const root = { dataset: { statusUrl: '/status', messages: JSON.stringify(messages) }, querySelector: selector => selector === 'iframe' ? frame : message, append: child => children.push(child) };
  const context = { document: { querySelector: () => root, createElement: () => ({}) },
    fetch: async () => { if (fail) throw new Error('synthetic transport error'); return { ok: true, json: async () => result }; },
    location: { href: 'https://store.example.invalid/verify', origin: 'https://store.example.invalid', replace: () => {} },
    URL, Date, JSON, clearTimeout: () => {}, setTimeout: () => 1, window: { addEventListener: () => {} } };
  vm.runInNewContext(script, context);
  await new Promise(resolve => setImmediate(resolve));
  return { message: message.textContent, children };
}

test('verification renders every server-translated status and safe retry label', async () => {
  for (const [state, reason, key] of [['declined', 'authentication_expired', 'authenticationIncomplete'], ['declined', 'authentication_failed', 'authenticationIncomplete'], ['declined', 'bank_declined', 'declined'], ['unknown', '', 'pending'], ['challenge', '', 'challenge'], ['sending', '', 'checking']]) {
    const actual = await render({ state, reason, retry: state === 'declined' ? '/retry' : null });
    assert.equal(actual.message, messages[key]);
    if (state === 'declined') assert.equal(actual.children[0].textContent, messages.retry);
  }
  assert.equal((await render({}, true)).message, messages.unavailable);
  assert.equal((await render({ state: 'declined', retry: 'https://other.example.invalid/retry' })).children.length, 0);
});
