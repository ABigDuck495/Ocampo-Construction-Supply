// resources/js/pages/payment-status.js
import { toast } from './toast.js';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const METHODS = ['COD', 'GCash', 'Card', 'Bank Transfer'];
const STATUSES = ['Paid', 'Payable', 'Unpaid'];

let rows = [];
let filter = 'all';
let search = '';
let bound = false;

function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function money(n) { return n === null || n === undefined ? '—' : '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function dt(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return isNaN(d) ? iso : d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
const panel = () => document.getElementById('paymentStatusPanel');

function injectStyles() {
    if (document.getElementById('ps-styles')) return;
    const s = document.createElement('style');
    s.id = 'ps-styles';
    s.textContent = `
    .ps-toolbar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
    .ps-search{flex:1;min-width:220px;background:var(--panel,#171b24);border:1px solid var(--border,#2a3040);color:var(--text,#e6e8ee);font-family:inherit;font-size:12px;padding:11px 14px;outline:none;}
    .ps-search:focus{border-color:var(--orange,#f5a623);}
    .ps-filters{display:flex;gap:6px;flex-wrap:wrap;}
    .ps-filter{background:var(--panel,#171b24);border:2px solid var(--border,#2a3040);color:var(--text-dim,#8b93a6);font-family:inherit;font-size:11px;font-weight:700;padding:8px 12px;cursor:pointer;}
    .ps-filter.active{background:var(--orange,#f5a623);color:#14161c;border-color:#000;}
    .ps-table-wrap{overflow-x:auto;}
    .ps-table{width:100%;border-collapse:collapse;background:var(--panel,#171b24);border:1px solid var(--border,#2a3040);}
    .ps-table th{text-align:left;font-size:10.5px;letter-spacing:.5px;color:var(--text-dim,#8b93a6);padding:12px 14px;border-bottom:1px solid var(--border,#2a3040);white-space:nowrap;}
    .ps-table td{padding:13px 14px;border-bottom:1px solid var(--border,#2a3040);font-size:12px;color:var(--text,#e6e8ee);vertical-align:middle;}
    .ps-table tbody tr:hover{background:var(--panel-alt,#1b202c);}
    .ps-empty{text-align:center;color:var(--text-dim,#8b93a6);padding:36px 0 !important;}
    .ps-badge{display:inline-block;border:1px solid;border-radius:4px;padding:3px 9px;font-size:10.5px;font-weight:800;letter-spacing:.04em;}
    .ps-badge.Paid{color:var(--green,#3ecf6e);border-color:var(--green,#3ecf6e);}
    .ps-badge.Payable{color:var(--orange,#f5a623);border-color:var(--orange,#f5a623);}
    .ps-badge.Unpaid{color:var(--red,#ff5c5c);border-color:var(--red,#ff5c5c);}
    .ps-sub{display:block;font-size:10px;color:var(--text-dim,#8b93a6);margin-top:2px;}
    .ps-btn{background:var(--panel-alt,#1b202c);border:1px solid var(--border,#2a3040);color:var(--text,#e6e8ee);font-family:inherit;font-size:11px;font-weight:700;padding:7px 12px;cursor:pointer;}
    .ps-btn:hover{border-color:var(--orange,#f5a623);}
    .ps-overlay{position:fixed;inset:0;background:rgba(10,12,18,.72);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px;}
    .ps-modal{background:var(--panel,#171b24);border:1px solid var(--border,#2a3040);width:100%;max-width:440px;max-height:calc(100vh - 40px);overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,.5);}
    .ps-modal-head{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border,#2a3040);font-size:14px;font-weight:800;letter-spacing:.04em;color:var(--text,#e6e8ee);}
    .ps-x{background:transparent;border:none;color:var(--text-dim,#8b93a6);font-size:20px;cursor:pointer;line-height:1;}
    .ps-x:hover{color:var(--red,#ff5c5c);}
    .ps-modal-body{padding:18px 20px;display:grid;gap:12px;}
    .ps-order-line{font-size:11.5px;color:var(--text-dim,#8b93a6);line-height:1.5;}
    .ps-field label{display:block;font-size:10.5px;letter-spacing:.05em;color:var(--text-dim,#8b93a6);margin-bottom:6px;}
    .ps-field input,.ps-field select{width:100%;box-sizing:border-box;background:var(--panel-alt,#1b202c);border:1px solid var(--border,#2a3040);color:var(--text,#e6e8ee);font-family:inherit;font-size:13px;padding:10px 12px;outline:none;}
    .ps-field input:focus,.ps-field select:focus{border-color:var(--orange,#f5a623);}
    .ps-hint{font-size:10px;color:var(--text-dim,#8b93a6);}
    .ps-error{color:var(--red,#ff5c5c);font-size:11px;display:none;}
    .ps-error.show{display:block;}
    .ps-modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid var(--border,#2a3040);}
    .ps-save{background:var(--orange-dark,#d98c12);border:none;color:#fff;font-family:inherit;font-size:12px;font-weight:700;padding:10px 18px;cursor:pointer;}
    .ps-save:hover{background:var(--orange,#f5a623);color:#14161c;}
    .ps-save:disabled{opacity:.6;cursor:default;}
    `;
    document.head.appendChild(s);
}

function filtered() {
    const q = search.trim().toLowerCase();
    return rows.filter(r => {
        if (filter !== 'all' && r.PaymentStatus !== filter) return false;
        if (!q) return true;
        return [`ord-${r.OrderID}`, r.CustomerName, r.ReceivedBy, r.HandedOverBy, r.PaymentMethod]
            .some(v => String(v ?? '').toLowerCase().includes(q));
    });
}

function render() {
    const body = document.getElementById('psBody');
    const list = filtered();
    if (!list.length) {
        body.innerHTML = `<tr><td colspan="9" class="ps-empty">No orders found.</td></tr>`;
        return;
    }
    body.innerHTML = list.map(r => `
        <tr>
            <td>ORD-${esc(r.OrderID)}</td>
            <td>${esc(r.CustomerName)}</td>
            <td>${esc(dt(r.OrderDate))}</td>
            <td>${money(r.Amount)}</td>
            <td>${esc(r.PaymentMethod || '—')}</td>
            <td><span class="ps-badge ${esc(r.PaymentStatus)}">${esc((r.PaymentStatus || '—').toUpperCase())}</span>
                ${r.PaymentUpdatedAt ? `<span class="ps-sub">${esc(dt(r.PaymentUpdatedAt))}</span>` : ''}</td>
            <td>${esc(r.ReceivedBy || '—')}</td>
            <td>${esc(r.HandedOverBy || '—')}</td>
            <td><button type="button" class="ps-btn" data-order="${esc(r.OrderID)}">UPDATE</button></td>
        </tr>`).join('');
}

function openModal(row) {
    const overlay = document.createElement('div');
    overlay.className = 'ps-overlay';
    overlay.innerHTML = `
        <div class="ps-modal" role="dialog" aria-modal="true">
            <div class="ps-modal-head"><span>UPDATE PAYMENT</span><button type="button" class="ps-x" aria-label="Close">&times;</button></div>
            <div class="ps-modal-body">
                <div class="ps-order-line">ORD-${esc(row.OrderID)} &middot; ${esc(row.CustomerName)} &middot; ${money(row.Amount)}</div>
                <div class="ps-field"><label>PAYMENT METHOD</label>
                    <select id="psMethod">${METHODS.map(m => `<option value="${m}" ${m === row.PaymentMethod ? 'selected' : ''}>${m}</option>`).join('')}</select></div>
                <div class="ps-field"><label>PAYMENT STATUS</label>
                    <select id="psStatus">${STATUSES.map(s => `<option value="${s}" ${s === row.PaymentStatus ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
                <div class="ps-field"><label>PAYMENT RECEIVED BY</label>
                    <input type="text" id="psReceived" value="${esc(row.ReceivedBy || '')}" placeholder="Name of receiver"></div>
                <div class="ps-field"><label>PAYMENT HANDED OVER BY</label>
                    <input type="text" id="psHanded" value="${esc(row.HandedOverBy || '')}" placeholder="Name of person who handed over the payment"></div>
                <div class="ps-hint">Both names are required when the status is Paid.</div>
                <div class="ps-error" id="psError"></div>
            </div>
            <div class="ps-modal-foot">
                <button type="button" class="ps-btn" id="psCancel">CANCEL</button>
                <button type="button" class="ps-save" id="psSave">SAVE</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    const close = () => overlay.remove();
    overlay.querySelector('.ps-x').addEventListener('click', close);
    overlay.querySelector('#psCancel').addEventListener('click', close);
    overlay.addEventListener('mousedown', e => { if (e.target === overlay) close(); });
    overlay.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

    const err = overlay.querySelector('#psError');
    const showErr = msg => { err.textContent = msg; err.classList.add('show'); };

    overlay.querySelector('#psSave').addEventListener('click', async e => {
        const btn = e.currentTarget;
        const payload = {
            PaymentMethod: overlay.querySelector('#psMethod').value,
            PaymentStatus: overlay.querySelector('#psStatus').value,
            ReceivedBy: overlay.querySelector('#psReceived').value.trim() || null,
            HandedOverBy: overlay.querySelector('#psHanded').value.trim() || null,
        };
        if (payload.PaymentStatus === 'Paid' && (!payload.ReceivedBy || !payload.HandedOverBy)) {
            showErr('Enter both the receiver and the person who handed over the payment.');
            return;
        }
        err.classList.remove('show');
        btn.disabled = true;
        try {
            const url = panel().dataset.updateUrlTemplate.replace('__ID__', row.OrderID);
            const res = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Could not update payment.');
                throw new Error(msg);
            }
            Object.assign(row, payload, { PaymentUpdatedAt: new Date().toISOString() });
            render();
            close();
            toast('Payment updated.', 'success');
        } catch (ex) {
            console.error(ex);
            showErr(ex.message || 'Could not update payment.');
            btn.disabled = false;
        }
    });

    overlay.querySelector('#psMethod').focus();
}

function bind() {
    if (bound) return;
    bound = true;
    document.getElementById('psSearch').addEventListener('input', e => { search = e.target.value; render(); });
    document.getElementById('psFilters').addEventListener('click', e => {
        const b = e.target.closest('.ps-filter');
        if (!b) return;
        filter = b.dataset.filter;
        document.querySelectorAll('#psFilters .ps-filter').forEach(x => x.classList.toggle('active', x === b));
        render();
    });
    document.getElementById('psBody').addEventListener('click', e => {
        const b = e.target.closest('[data-order]');
        if (!b) return;
        const row = rows.find(r => String(r.OrderID) === b.dataset.order);
        if (row) openModal(row);
    });
}

/** Call this when the Payment Status tab is opened. Safe to call repeatedly (reloads the data). */
export async function initPaymentStatus() {
    if (!panel()) return;
    injectStyles();
    bind();
    try {
        const res = await fetch(panel().dataset.listUrl, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Request failed');
        rows = await res.json();
        render();
    } catch (err) {
        console.error('Load payments failed:', err);
        toast('Could not load payment records.', 'error');
    }
}