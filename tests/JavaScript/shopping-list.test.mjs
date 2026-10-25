import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/shopping-list/index.blade.php', import.meta.url), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];

function checklist() {
    let submit;
    let reply = { item_key: 'test', is_checked: true, checked_count: 1, item_count: 2 };
    let status = 200;
    let redirected = false;
    let requests = 0;
    const attributes = {};
    const symbol = { textContent: '+' };
    const checked = { value: '1' };
    const feedback = { textContent: '' };
    const count = { textContent: '0 of 2 items purchased' };
    const row = { checked: false, classList: { toggle(name, value) {
        assert.equal(name, 'is-checked');
        row.checked = value;
    } } };
    const button = {
        disabled: false,
        setAttribute(name, value) { attributes[name] = value; },
        querySelector() { return symbol; },
        focus(options) { assert.equal(options.preventScroll, true); this.focusRestored = true; },
    };
    const form = {
        action: '/shopping-list', dataset: { name: 'Rice' },
        addEventListener(name, handler) { assert.equal(name, 'submit'); submit = handler; },
        querySelector(selector) { return selector === 'button' ? button : checked; },
        closest(selector) { assert.equal(selector, '.sl-row'); return row; },
    };
    const document = {
        activeElement: button,
        querySelectorAll(selector) { assert.equal(selector, '[data-shopping-check]'); return [form]; },
        getElementById(id) { return id === 'shopping-feedback' ? feedback : count; },
    };
    vm.runInNewContext(script, {
        document, window: { fetch: true }, FormData: class { constructor(value) { assert.equal(value, form); } },
        fetch: async (url, options) => {
            requests++;
            assert.equal(url, '/shopping-list');
            assert.equal(options.method, 'POST');
            assert.equal(options.headers.Accept, 'application/json');
            return { ok: status === 200, redirected, headers: { get: () => 'application/json' }, json: async () => reply };
        },
    });
    return { row, button, attributes, symbol, checked, feedback, count,
        get requests() { return requests; },
        respond(data, code = 200, redirect = false) { reply = data; status = code; redirected = redirect; },
        submit() { return submit({ preventDefault() {} }); },
    };
}

test('checking and unchecking retain the row and keyboard position without a reload', async () => {
    const list = checklist();
    const originalRow = list.row;
    const first = list.submit();
    await list.submit(); // Duplicate click while the first request is pending.
    await first;
    assert.equal(list.requests, 1);
    assert.equal(list.row, originalRow);
    assert.equal(list.row.checked, true);
    assert.equal(list.attributes['aria-pressed'], 'true');
    assert.equal(list.checked.value, '0');
    assert.equal(list.symbol.textContent, '\u2713');
    assert.equal(list.count.textContent, '1 of 2 items purchased');
    assert.equal(list.button.focusRestored, true);
    list.respond({ is_checked: false, checked_count: 0, item_count: 2 });
    await list.submit();
    assert.equal(list.row, originalRow);
    assert.equal(list.row.checked, false);
    assert.equal(list.attributes['aria-label'], 'Mark purchased: Rice');
    assert.equal(list.checked.value, '1');
});

test('stale submissions display the server error without marking the item purchased', async () => {
    const list = checklist();
    list.respond({ errors: { shopping_list: ['The quantity changed. Refresh the list.'] } }, 422);
    await list.submit();
    assert.equal(list.row.checked, false);
    assert.equal(list.checked.value, '1');
    assert.equal(list.feedback.textContent, 'The quantity changed. Refresh the list.');
    assert.equal(list.count.textContent, '0 of 2 items purchased');
    assert.equal(list.button.disabled, false);
});

test('an expired session does not produce a false successful purchase', async () => {
    const list = checklist();
    list.respond({}, 200, true);
    await list.submit();
    assert.equal(list.row.checked, false);
    assert.match(list.feedback.textContent, /session may have expired/);
});
