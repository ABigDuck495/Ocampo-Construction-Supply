/* ============================================================
   POS PRODUCT GROUPS + VARIANT MODAL
   resources/js/pages/posGroups.js
   Groups existing products on the frontend (no DB changes).
   ============================================================ */
import { toast } from './toast.js';

let deps = null;   // { fmt, escapeHtml, inventoryOn, cartQty(id), addToCart(id, qty, price) }

export function initGroups(d){ deps = d; }

/* ---------- text helpers ---------- */
export function normalizeText(s){
    return String(s || '').toLowerCase()
        .replace(/(\d)\s*(?:inches|inch|in)\b/g, '$1"')
        .replace(/(\d)\s*(?:''|”|“)/g, '$1"')
        .replace(/\s*"\s*/g, '" ')
        .replace(/\s+/g, ' ')
        .trim();
}
function esc(s){ return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

/** every token in `query` (already normalized) must appear in `hay` (already normalized) */
export function matchesQuery(hay, query){
    if(!query) return true;
    return query.split(' ').every(tok => {
        if(!tok) return true;
        // numeric tokens must not match inside bigger numbers (2" must not hit 12")
        if(/^\d/.test(tok)) return new RegExp('(^|[^\\d./])' + esc(tok)).test(hay);
        return hay.includes(tok);
    });
}

/* ---------- name -> { type, size } ---------- */
export function parseVariant(name){
    const n = String(name || '').trim();
    const m = /(?:^|[\s(,\-])(\d)/.exec(n);
    if(!m) return { type: n, size: '' };
    const digitIdx = m.index + m[0].length - 1;
    const type = n.slice(0, digitIdx).replace(/[\s,\-(]+$/, '').trim();
    const size = n.slice(digitIdx).replace(/\)\s*$/, '').trim();
    if(!type) return { type: n, size: '' };        // name starts with a number
    return { type, size };
}

/* ---------- build groups once ---------- */
export function buildGroups(products){
    const map = new Map();
    for(const p of products){
        const { type, size } = parseVariant(p.name);
        const label = (p.subCategory || '').trim() || type;
        const key = (p.cat || '') + '||' + label.toLowerCase();
        let g = map.get(key);
        if(!g){
            g = { key, label, cat: p.cat, variants: [], types: new Map(), min: Infinity, max: -Infinity, hasVariable: false, search: '' };
            map.set(key, g);
        }
        const v = { ...p, type, size, hay: normalizeText(`${p.name} ${p.sku || ''}`) };
        g.variants.push(v);
        if(!g.types.has(type)) g.types.set(type, []);
        g.types.get(type).push(v);
        if(p.price === null || p.pricingType === 'Variable') g.hasVariable = true;
        else { g.min = Math.min(g.min, p.price); g.max = Math.max(g.max, p.price); }
    }
    const groups = Array.from(map.values());
    for(const g of groups){
        g.search = normalizeText(g.label) + ' ' + g.variants.map(v => v.hay).join(' | ');
        g.variants.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true }));
        for(const arr of g.types.values()) arr.sort((a, b) => a.size.localeCompare(b.size, undefined, { numeric: true }));
    }
    groups.sort((a, b) => a.label.localeCompare(b.label));
    return groups;
}

export function priceRange(g){
    const { fmt } = deps;
    if(g.min === Infinity) return 'Enter price';
    const r = g.min === g.max ? fmt(g.min) : `${fmt(g.min)} – ${fmt(g.max)}`;
    return g.hasVariable ? r + '+' : r;
}

/* ---------- modal ---------- */
let el = null;
let S = null;   // state: { group, type, variant, qty, query }
const RESULT_CAP = 80;

function ensureModal(){
    if(el) return;
    el = document.createElement('div');
    el.className = 'grp-overlay';
    el.innerHTML = `
      <div class="grp-modal" role="dialog" aria-modal="true">
        <div class="grp-head">
          <div><div class="grp-title" id="grpTitle"></div><div class="grp-sub" id="grpSub"></div></div>
          <button type="button" class="grp-close" id="grpClose" aria-label="Close">&times;</button>
        </div>
        <div class="grp-search">
          <input type="text" id="grpSearch" placeholder="Search type or size... (e.g. 2 inch)" autocomplete="off" spellcheck="false">
        </div>
        <div class="grp-body">
          <div class="grp-pick" id="grpPick"></div>
          <div class="grp-detail" id="grpDetail"></div>
        </div>
      </div>`;
    document.body.appendChild(el);

    el.addEventListener('mousedown', e => { if(e.target === el) closeModal(); });
    el.querySelector('#grpClose').addEventListener('click', closeModal);
    document.addEventListener('keydown', e => { if(e.key === 'Escape' && el.classList.contains('open')) closeModal(); });

    el.querySelector('#grpSearch').addEventListener('input', e => {
        S.query = normalizeText(e.target.value);
        renderPick();
    });

    el.querySelector('#grpPick').addEventListener('click', e => {
        const t = e.target.closest('[data-type]');
        if(t){ S.type = t.dataset.type; S.variant = null; renderPick(); renderDetail(); return; }
        const v = e.target.closest('[data-vid]');
        if(v){
            S.variant = S.group.variants.find(x => String(x.id) === v.dataset.vid);
            if(S.variant) S.type = S.variant.type;
            S.qty = 1;
            renderPick(); renderDetail();
        }
    });
}

export function openGroup(group, initialQuery = ''){
    ensureModal();
    const types = Array.from(group.types.keys());
    S = { group, type: types.length === 1 ? types[0] : null, variant: null, qty: 1, query: normalizeText(initialQuery) };
    el.querySelector('#grpTitle').textContent = group.label.toUpperCase();
    el.querySelector('#grpSub').textContent = `${group.variants.length} variant${group.variants.length === 1 ? '' : 's'} · ${group.cat || ''}`;
    const input = el.querySelector('#grpSearch');
    input.value = initialQuery;
    el.classList.add('open');
    renderPick(); renderDetail();
    setTimeout(() => input.focus(), 30);
}

function closeModal(){ if(el) el.classList.remove('open'); S = null; }

function chipHtml(v, label){
    const out = deps.inventoryOn && v.stock <= 0;
    const sel = S.variant && S.variant.id === v.id;
    return `<button type="button" class="grp-chip${sel ? ' selected' : ''}${out ? ' out' : ''}" data-vid="${deps.escapeHtml(String(v.id))}">${deps.escapeHtml(label)}</button>`;
}

function renderPick(){
    const box = el.querySelector('#grpPick');
    const g = S.group;

    // Searching: flat list of matching variants across all types
    if(S.query){
        const hits = g.variants.filter(v => matchesQuery(v.hay, S.query));
        box.innerHTML = `<div class="grp-label">RESULTS (${hits.length})</div>` +
            (hits.length
                ? `<div class="grp-chips">${hits.slice(0, RESULT_CAP).map(v => chipHtml(v, v.name)).join('')}</div>` +
                  (hits.length > RESULT_CAP ? `<div class="grp-note">Showing first ${RESULT_CAP}. Refine your search.</div>` : '')
                : `<div class="grp-note">No variants match.</div>`);
        return;
    }

        // No sizes could be parsed: show the variants directly as a list
    if(g.variants.every(v => !v.size)){
        box.innerHTML = `<div class="grp-label">CHOOSE ITEM (${g.variants.length})</div>` +
            `<div class="grp-chips">${g.variants.map(v => chipHtml(v, v.name)).join('')}</div>`;
        return;
    }

    const types = Array.from(g.types.keys());
    let html = '';
    if(types.length > 1){
        html += `<div class="grp-label">CHOOSE TYPE</div><div class="grp-chips">` +
            types.map(t => `<button type="button" class="grp-chip${S.type === t ? ' selected' : ''}" data-type="${deps.escapeHtml(t)}">${deps.escapeHtml(t)} <small>${g.types.get(t).length}</small></button>`).join('') +
            `</div>`;
    }
    if(S.type){
        html += `<div class="grp-label">${types.length > 1 ? deps.escapeHtml(S.type.toUpperCase()) + ' — ' : ''}SIZE</div><div class="grp-chips">` +
            g.types.get(S.type).map(v => chipHtml(v, v.size || 'Standard')).join('') + `</div>`;
    } else {
        html += `<div class="grp-note">Pick a type to see sizes.</div>`;
    }
    box.innerHTML = html;
}

function renderDetail(){
    const box = el.querySelector('#grpDetail');
    const v = S.variant;
    if(!v){ box.innerHTML = `<div class="grp-note">Select a size to see price and stock.</div>`; return; }

    const variable = v.price === null || v.pricingType === 'Variable';
    const inCart = deps.cartQty(v.id);
    const left = deps.inventoryOn ? Math.max(0, v.stock - inCart) : Infinity;
    const E = deps.escapeHtml;

    box.innerHTML = `
      <div class="grp-sel-name">${E(v.name)}</div>
      ${v.sku ? `<div class="grp-sel-meta">SKU: ${E(v.sku)}</div>` : ''}
      ${variable
        ? `<div class="grp-label">UNIT PRICE (₱)</div><input type="number" min="0" step="0.01" class="cf-input grp-price-input" id="grpPrice" placeholder="Enter price">`
        : `<div class="grp-price">${deps.fmt(v.price)}</div>`}
      ${deps.inventoryOn ? `<div class="grp-stock${v.stock <= 0 ? ' out' : ''}">Stock: ${v.stock}${inCart ? ` (${inCart} already in order)` : ''}</div>` : ''}
      <div class="grp-label">QUANTITY</div>
      <div class="grp-qty">
        <button type="button" class="qty-btn" id="grpDec">-</button>
        <input type="number" min="1" id="grpQty" class="cf-input" value="${S.qty}">
        <button type="button" class="qty-btn" id="grpInc">+</button>
      </div>
      <div class="grp-total">TOTAL <b id="grpTotal"></b></div>
      <button type="button" class="btn-checkout" id="grpAdd">ADD TO ORDER</button>`;

    const qtyEl = box.querySelector('#grpQty');
    const priceEl = box.querySelector('#grpPrice');
    const totalEl = box.querySelector('#grpTotal');
    const unit = () => variable ? (priceEl.value === '' ? null : Number(priceEl.value)) : v.price;

    const refresh = () => {
        let q = parseInt(qtyEl.value, 10);
        if(!(q >= 1)) q = 1;
        if(q > left) q = Math.max(1, left);
        S.qty = q;
        const u = unit();
        totalEl.textContent = u === null || Number.isNaN(u) ? '—' : deps.fmt(u * q);
    };
    box.querySelector('#grpDec').addEventListener('click', () => { qtyEl.value = Math.max(1, (parseInt(qtyEl.value, 10) || 1) - 1); refresh(); });
    box.querySelector('#grpInc').addEventListener('click', () => { qtyEl.value = (parseInt(qtyEl.value, 10) || 0) + 1; refresh(); qtyEl.value = S.qty; });
    qtyEl.addEventListener('input', refresh);
    qtyEl.addEventListener('change', () => { refresh(); qtyEl.value = S.qty; });
    if(priceEl) priceEl.addEventListener('input', refresh);

    const add = () => {
        refresh();
        const u = unit();
        if(variable && (u === null || Number.isNaN(u) || u < 0)){ toast('Enter a valid unit price.', 'error'); return; }
        if(deps.inventoryOn && left <= 0){ toast(`No stock left for ${v.name}.`, 'error'); return; }
        const qty = S.qty;
        if(deps.addToCart(v.id, qty, variable ? u : null)){
            toast(`Added ${qty} × ${v.name}`, 'success');
            closeModal();
        }
    };
    box.querySelector('#grpAdd').addEventListener('click', add);
    qtyEl.addEventListener('keydown', e => { if(e.key === 'Enter') add(); });

    refresh();
}