const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function previewPoll(status) {
    const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/weekly-meals/show.blade.php'), 'utf8');
    const script = [...blade.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(source => source.includes("getElementById('weekly-progress')"));
    assert.ok(script, 'The rendered preview must include status polling.');
    const source = script.replace(/@json\(route\('weekly-meals.status', \$draft->id\)\)/g, JSON.stringify('/weekly-meals/1/status')).replaceAll('{{ $typeCount }}', '3');
    const heading = { textContent: 'Waiting to start' };
    const progress = { textContent: '0 of 3 meal types completed.' };
    let poll;
    let reloaded = false;
    vm.runInNewContext(source, {
        document: { getElementById: id => id === 'weekly-progress' ? progress : heading },
        fetch: async () => ({ ok: true, json: async () => ({ status, completed_types: 1 }) }),
        setTimeout: callback => { poll = callback; },
        window: { location: { reload: () => { reloaded = true; } } },
    });
    return { poll: () => poll(), heading, progress, reloaded: () => reloaded };
}

test('A claimed draft replaces the Waiting to start heading while generation is running', async () => {
    const preview = previewPoll('generating');
    await preview.poll();
    assert.equal(preview.heading.textContent, 'Creating meal options');
    assert.equal(preview.progress.textContent, '1 of 3 meal types completed.');
    assert.equal(preview.reloaded(), false);
});

test('An unconsumed draft retains the queued heading and a terminal draft reloads the preview', async () => {
    const queued = previewPoll('queued');
    await queued.poll();
    assert.equal(queued.heading.textContent, 'Waiting to start');
    for (const status of ['ready', 'failed', 'cancelled']) {
        const preview = previewPoll(status);
        await preview.poll();
        assert.equal(preview.reloaded(), true);
    }
});
