import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, h, ref, nextTick } from 'vue';

// Exercise the actual compiled Vue event handlers without a browser or account mutations.
const modules = new Map();
function componentUrl(url) {
  if (modules.has(url.href)) return modules.get(url.href);
  const filename = fileURLToPath(url);
  const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename });
  const compiled = compileScript(descriptor, { id: filename, inlineTemplate: true });
  const code = compiled.content.replace(/from (['"])([^'"]+)\1/g, (_, quote, specifier) => {
    let target;
    if (specifier.startsWith('@/')) target = new URL('../../resources/js/' + specifier.slice(2), import.meta.url);
    else if (specifier.startsWith('.')) target = new URL(specifier, url);
    else return `from ${JSON.stringify(import.meta.resolve(specifier))}`;
    return `from ${JSON.stringify(target.pathname.endsWith('.vue') ? componentUrl(target) : target.href)}`;
  });
  const result = 'data:text/javascript;base64,' + Buffer.from(code).toString('base64');
  modules.set(url.href, result);
  return result;
}
const load = async name => (await import(componentUrl(new URL('../../resources/js/App/Components/Learning/Tasks/' + name + '.vue', import.meta.url)))).default;
const all = root => [root, ...root.children.flatMap(all)];
function node(type, text = '') {
  return {
    type, text, children: [], props: {}, parent: null, captured: new Set(), offsetHeight: 100,
    rect: { left: 0, top: 0, right: 100, bottom: 50, width: 100 },
    get dataset() { return { token: this.props['data-token'] }; },
    getBoundingClientRect() { return this.rect; },
    querySelectorAll() { return all(this).filter(item => item.props['data-token']); },
    setPointerCapture(id) { this.captured.add(id); },
    hasPointerCapture(id) { return this.captured.has(id); },
    releasePointerCapture(id) { this.captured.delete(id); },
  };
}
function mount(component, initial, answer) {
  const root = node('root');
  const body = node('body');
  const renderer = createRenderer({
    createElement: tag => node(tag), createText: text => node('#text', text), createComment: text => node('#comment', text),
    setText: (el, text) => { el.text = text; }, setElementText: (el, text) => { el.text = text; el.children = []; },
    patchProp: (el, key, old, value) => { el.props[key] = value; },
    insert(el, parent, anchor) {
      if (el.parent) el.parent.children.splice(el.parent.children.indexOf(el), 1);
      el.parent = parent;
      const index = parent.children.indexOf(anchor);
      parent.children.splice(index < 0 ? parent.children.length : index, 0, el);
    },
    remove(el) { el.parent?.children.splice(el.parent.children.indexOf(el), 1); el.parent = null; },
    parentNode: el => el.parent,
    nextSibling: el => el.parent?.children[el.parent.children.indexOf(el) + 1] ?? null,
    querySelector: () => body,
    setScopeId() {},
  });
  const model = ref(answer);
  const disabled = ref(false);
  const app = renderer.createApp({ render: () => h(component, { ...initial, uid: 'interaction-test', modelValue: model.value, disabled: disabled.value, 'onUpdate:modelValue': value => { model.value = value; } }) });
  app.mount(root);
  return { root, body, model, disabled, unmount: () => app.unmount() };
}
const byClass = (root, name) => all(root).filter(el => String(el.props.class ?? '').split(' ').includes(name));
const click = async el => { el.props.onClick(); await nextTick(); };
const pointer = (el, x, y, type = 'mouse') => ({ currentTarget: el, pointerId: 1, isPrimary: true, button: 0, clientX: x, clientY: y, pointerType: type, preventDefault() {} });

const pairTask = { left_items: [{id:'l1',text:'Bonjour',text_lang:'fr'}, {id:'l2',text:'Merci',text_lang:'fr'}], right_items: [{id:'r1',text:'Здравствуйте'}, {id:'r2',text:'Спасибо'}] };
const wordTask = { tokens: [{id:'a',text:'Je'}, {id:'b',text:'suis'}, {id:'c',text:'ici'}] };

test('matching pairs starts from either column, reassigns uniquely and respects disabled state', async () => {
  const fixture = mount(await load('MatchingPairsTask'), {task:pairTask,showTranscription:false}, {pairs:[]});
  try {
    const tile = text => byClass(fixture.root, 'pair-tile').find(el => el.props['aria-label'].startsWith(text));
    await click(tile('Здравствуйте'));
    assert.equal(tile('Здравствуйте').props['aria-pressed'], true);
    await click(tile('Bonjour'));
    assert.deepEqual(fixture.model.value.pairs, [{left_id:'l1',right_id:'r1'}]);
    await click(tile('Merci'));
    await click(tile('Спасибо'));
    assert.equal(fixture.model.value.pairs.length, 2);
    await click(tile('Bonjour'));
    await click(tile('Спасибо'));
    assert.deepEqual(fixture.model.value.pairs, [{left_id:'l1',right_id:'r2'}]);
    fixture.disabled.value = true;
    await nextTick();
    await click(tile('Merci'));
    assert.equal(tile('Merci').props['aria-pressed'], false);
    assert.equal(all(fixture.root).some(el => el.type === 'select'), false);
  } finally { fixture.unmount(); }
});

test('word tiles add and remove on click and preserve source slots', async () => {
  const original = globalThis.ResizeObserver;
  globalThis.ResizeObserver = class { observe() {} disconnect() {} };
  const fixture = mount(await load('WordOrderTask'), {task:wordTask,showTranscription:false}, {token_ids:[]});
  try {
    const available = text => all(fixture.root).find(el => el.props['aria-label'] === text + '. Добавить в ответ');
    await click(available('Je'));
    await click(available('suis'));
    assert.deepEqual(fixture.model.value.token_ids, ['a','b']);
    assert.equal(byClass(fixture.root,'bank-slot').length, 3);
    assert.equal(available('Je').props.disabled, true);
    await click(all(fixture.root).find(el => el.props['aria-label'] === 'Je. Убрать из ответа'));
    assert.deepEqual(fixture.model.value.token_ids, ['b']);
    assert.equal(available('Je').props.disabled, false);
    fixture.disabled.value = true;
    await nextTick();
    await click(available('ici'));
    assert.deepEqual(fixture.model.value.token_ids, ['b']);
  } finally { fixture.unmount(); globalThis.ResizeObserver = original; }
});

for (const pointerType of ['mouse', 'touch']) test(`word tiles support ${pointerType} drag, reorder, return and cancellation`, async () => {
  const original = globalThis.ResizeObserver;
  const originalFrame = globalThis.requestAnimationFrame;
  const originalCancel = globalThis.cancelAnimationFrame;
  const frames = new Map();
  let frameId = 0;
  globalThis.requestAnimationFrame = callback => { frames.set(++frameId, callback); return frameId; };
  globalThis.cancelAnimationFrame = id => frames.delete(id);
  globalThis.ResizeObserver = class { observe() {} disconnect() {} };
  const fixture = mount(await load('WordOrderTask'), {task:wordTask,showTranscription:false}, {token_ids:[]});
  try {
    const area = byClass(fixture.root,'assembled')[0];
    const bank = byClass(fixture.root,'token-bank')[0];
    area.rect = {left:0,top:0,right:400,bottom:180,width:400};
    bank.rect = {left:0,top:200,right:400,bottom:300,width:400};
    const find = (text, source) => all(fixture.root).find(el => el.props['aria-label'] === text + (source ? '. Добавить в ответ' : '. Убрать из ответа'));
    const dragTo = async (el, x, y, cancel = false) => {
      el.props.onPointerdown(pointer(el, 50, 250, pointerType));
      el.props.onPointermove(pointer(el, x, y, pointerType));
      await nextTick();
      assert.ok(fixture.body.children.some(el => el.props.class === 'word-drag-ghost'));
      if (cancel) el.props.onPointercancel();
      else el.props.onPointerup(pointer(el, x, y, pointerType));
      // Browsers dispatch a click after pointerup; dragging must not toggle the tile again.
      if (!cancel) el.props.onClick();
      await nextTick();
    };
    await dragTo(find('Je', true), 50, 80);
    assert.deepEqual(fixture.model.value.token_ids, ['a']);
    await dragTo(find('suis', true), 350, 80);
    assert.deepEqual(fixture.model.value.token_ids, ['a','b']);
    area.children.filter(el => el.props['data-token']).forEach((el,index) => { el.rect = {left:index*110,top:10,right:index*110+100,bottom:70,width:100}; });
    await dragTo(find('suis', false), 5, 30);
    assert.deepEqual(fixture.model.value.token_ids, ['b','a']);
    await dragTo(find('Je', false), 200, 250);
    assert.deepEqual(fixture.model.value.token_ids, ['b']);
    await dragTo(find('ici', true), 100, 80, true);
    assert.deepEqual(fixture.model.value.token_ids, ['b']);
    await dragTo(find('ici', true), 900, 900);
    assert.deepEqual(fixture.model.value.token_ids, ['b']);
    assert.equal(byClass(fixture.body,'word-drag-ghost').length, 0);
    assert.equal(frames.size, 0);
  } finally {
    fixture.unmount();
    globalThis.ResizeObserver = original;
    globalThis.requestAnimationFrame = originalFrame;
    globalThis.cancelAnimationFrame = originalCancel;
  }
});
