import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

window.Alpine = Alpine;
Alpine.plugin(focus);

/*
 | Toasts: window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }))
 | Server flashes are pushed by the layout on page load.
 */
Alpine.data('toasts', (initial = []) => ({
    items: [],
    init() {
        initial.forEach((t) => this.push(t));
        window.addEventListener('toast', (e) => this.push(e.detail));
    },
    push({ type = 'info', message = '' }) {
        if (!message) return;
        const id = Date.now() + Math.random();
        this.items.push({ id, type, message });
        setTimeout(() => this.dismiss(id), type === 'error' ? 8000 : 5000);
    },
    dismiss(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
}));

/*
 | Global search with grouped results. Falls back to the full results page
 | on Enter (works without JavaScript too: the form submits normally).
 */
Alpine.data('globalSearch', (endpoint) => ({
    q: '',
    open: false,
    loading: false,
    groups: [],
    controller: null,
    timer: null,
    onInput() {
        clearTimeout(this.timer);
        if (this.q.trim().length < 2) {
            this.groups = [];
            this.open = false;
            return;
        }
        this.timer = setTimeout(() => this.fetch(), 200);
    },
    async fetch() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        this.open = true;
        try {
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(this.q.trim())}`, {
                headers: { Accept: 'application/json' },
                signal: this.controller.signal,
            });
            if (res.ok) this.groups = (await res.json()).groups;
        } catch (e) {
            if (e.name !== 'AbortError') this.groups = [];
        } finally {
            this.loading = false;
        }
    },
}));

/*
 | PWA install prompt (Chrome / Edge / Android). Hidden where unsupported.
 */
Alpine.data('installApp', () => ({
    deferred: null,
    init() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferred = e;
        });
        window.addEventListener('appinstalled', () => (this.deferred = null));
    },
    async install() {
        if (!this.deferred) return;
        this.deferred.prompt();
        await this.deferred.userChoice;
        this.deferred = null;
    },
}));

/*
 | Quotation and package line editor. Totals here are a preview only; the
 | server recomputes every figure from the lines (D60).
 */
Alpine.data('lineItems', (initial = [], catalogue = { equipment: [], services: [] }, opts = {}) => ({
    rows: initial.map((r) => ({ ...r, key: Math.random() })),
    discount: opts.discount ?? '0',
    tax: opts.tax ?? '0',
    add(section = 'services') {
        this.rows.push({ key: Math.random(), section, description: '', quantity: 1, days: 1, unit_price: '', service_id: null, equipment_id: null });
        this.$nextTick(() => this.$root.querySelector('[data-row]:last-of-type input[data-description]')?.focus());
    },
    remove(i) {
        this.rows.splice(i, 1);
    },
    pick(row, value) {
        const [kind, id] = String(value).split(':');
        if (kind === 'e') {
            const e = catalogue.equipment.find((x) => String(x.id) === id);
            if (!e) return;
            Object.assign(row, { section: 'equipment', equipment_id: e.id, service_id: null, description: e.name });
            if (e.rate !== '' && !this.num(row.unit_price)) row.unit_price = e.rate;
        } else if (kind === 's') {
            const s = catalogue.services.find((x) => String(x.id) === id);
            if (!s) return;
            Object.assign(row, { section: 'services', service_id: s.id, equipment_id: null, description: s.name });
        }
    },
    num(v) {
        return parseFloat(String(v ?? '').replace(/[,\u20a6\s]/g, '')) || 0;
    },
    line(r) {
        return Math.max(1, this.num(r.quantity)) * Math.max(1, this.num(r.days)) * this.num(r.unit_price);
    },
    get subtotal() {
        return this.rows.reduce((t, r) => t + this.line(r), 0);
    },
    get discountValue() {
        return Math.min(this.num(this.discount), this.subtotal);
    },
    get taxValue() {
        return Math.round((this.subtotal - this.discountValue) * this.num(this.tax)) / 100;
    },
    get total() {
        return this.subtotal - this.discountValue + this.taxValue;
    },
    money(v) {
        return '\u20a6' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    },
}));

/*
 | Small page actions without inline handlers (the CSP forbids them, D66):
 | <button data-action="print|reload|back">.
 */
document.addEventListener('click', (e) => {
    const el = e.target instanceof Element ? e.target.closest('[data-action]') : null;
    if (!el) return;
    const action = el.dataset.action;
    if (action === 'print') window.print();
    if (action === 'reload') location.reload();
    if (action === 'back') (history.length > 1 ? history.back() : window.close());
});

/*
 | Chart tooltips (D64): any element with data-tip shows it on hover or
 | keyboard focus. Text only (textContent), never HTML.
 */
(() => {
    let tip;
    const show = (el, x, y) => {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'chart-tip';
            tip.setAttribute('role', 'tooltip');
            document.body.appendChild(tip);
        }
        tip.textContent = el.dataset.tip;
        tip.hidden = false;
        const r = tip.getBoundingClientRect();
        const left = Math.min(Math.max(8, x - r.width / 2), window.innerWidth - r.width - 8);
        tip.style.left = `${left + window.scrollX}px`;
        tip.style.top = `${Math.max(8, y - r.height - 10) + window.scrollY}px`;
    };
    const hide = () => tip && (tip.hidden = true);
    document.addEventListener('pointerover', (e) => {
        const el = e.target instanceof Element ? e.target.closest('[data-tip]') : null;
        if (el) show(el, e.clientX, e.clientY); else hide();
    });
    document.addEventListener('pointermove', (e) => {
        const el = e.target instanceof Element ? e.target.closest('[data-tip]') : null;
        if (el) show(el, e.clientX, e.clientY);
    });
    document.addEventListener('focusin', (e) => {
        const el = e.target instanceof Element ? e.target.closest('[data-tip]') : null;
        if (!el) return hide();
        const r = el.getBoundingClientRect();
        show(el, r.left + r.width / 2, r.top);
    });
    document.addEventListener('focusout', hide);
    window.addEventListener('scroll', hide, { passive: true });
})();

/*
 | Phone app shell (D70).
 |
 | - The top bar's back arrow returns to the previous screen when there is
 |   one in this app; otherwise it follows its link (the section's list).
 | - Table rows ([data-stack] tables) become labelled cards on phones: each
 |   cell gets its column heading as data-label for the CSS to show.
 | - Filter forms ([data-filters]) collapse to the search box and a
 |   "Filters" button on phones; the button shows how many are applied.
 */
document.addEventListener('click', (e) => {
    const back = e.target instanceof Element ? e.target.closest('a[data-back]') : null;
    if (!back || e.defaultPrevented) return;
    let fromHere = false;
    try {
        fromHere = document.referrer !== '' && new URL(document.referrer).origin === location.origin && new URL(document.referrer).pathname !== location.pathname;
    } catch { /* no usable referrer */ }
    if (fromHere && history.length > 1) {
        e.preventDefault();
        history.back();
    }
});

document.querySelectorAll('[data-stack] table').forEach((table) => {
    const labels = [...table.querySelectorAll('thead th')].map((th) => {
        const text = th.textContent.trim();
        const hidden = th.querySelector('.sr-only');
        return hidden && hidden.textContent.trim() === text ? '' : text;
    });
    table.querySelectorAll('tbody tr').forEach((tr) => {
        let col = 0;
        [...tr.children].forEach((td) => {
            const span = Number(td.getAttribute('colspan') || 1);
            if (span === 1 && labels[col] && !td.hasAttribute('data-label')) td.dataset.label = labels[col];
            col += span;
        });
    });
});

document.querySelectorAll('form[data-filters]').forEach((form) => {
    const fields = [...form.children].filter((el) => !(el instanceof HTMLInputElement && el.type === 'hidden'));
    const keep = fields.find((el) => el.querySelector?.('input[type=search]') || (el instanceof HTMLInputElement && el.type === 'search'));
    keep?.classList.add('filters-keep');
    const applied = [...form.querySelectorAll('select, input:not([type=hidden]):not([type=search]):not([type=submit])')]
        .filter((el) => {
            if (el instanceof HTMLSelectElement) return el.selectedIndex > 0; // the first option is "any" or the default sort
            return el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value !== '';
        }).length;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'filters-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4"><line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/></svg>';
    const label = document.createElement('span');
    label.textContent = keep ? '' : 'Filters';
    toggle.append(label);
    toggle.setAttribute('aria-label', 'Filters');
    if (applied) {
        const badge = document.createElement('span');
        badge.className = 'filters-count';
        badge.textContent = String(applied);
        toggle.append(badge);
    }
    toggle.addEventListener('click', () => {
        const open = form.classList.toggle('filters-open');
        toggle.setAttribute('aria-expanded', String(open));
    });
    form.classList.add('filters-collapsible');
    if (keep) keep.after(toggle); else form.prepend(toggle);
});

/*
 | Tap feedback and double-submit protection (D59).
 |
 | - Any form submit marks the tapped button busy (spinner, no more taps)
 |   and blocks a second submission until the next page loads. Buttons are
 |   disabled a tick later so the tapped button's name=value is still sent.
 | - Same-site link taps mark the link busy and ignore repeat taps.
 | - A progress bar runs while the next page loads.
 | Opt out with data-no-busy (e.g. forms or links that download a file).
 */
const progress = (() => {
    let bar;
    let timer;
    const el = () => {
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'nav-progress';
            bar.setAttribute('aria-hidden', 'true');
            document.body.appendChild(bar);
        }
        return bar;
    };
    return {
        start() {
            const b = el();
            b.dataset.state = '';
            void b.offsetWidth; // restart the transition
            b.dataset.state = 'running';
        },
        done() {
            if (bar) bar.dataset.state = 'done';
        },
        safety(ms = 15000) {
            clearTimeout(timer);
            timer = setTimeout(resetBusy, ms);
        },
    };
})();

function markBusy(el) {
    if (!el || el.dataset.busy === '1') return;
    el.dataset.busy = '1';
    el.setAttribute('aria-busy', 'true');
    el.classList.add('is-busy');
    if (el.hasAttribute('data-btn')) {
        const spinner = document.createElement('span');
        spinner.className = 'busy-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        el.prepend(spinner);
    }
}

function resetBusy() {
    document.querySelectorAll('[data-busy="1"]').forEach((el) => {
        delete el.dataset.busy;
        el.removeAttribute('aria-busy');
        el.classList.remove('is-busy');
        el.querySelectorAll(':scope > .busy-spinner').forEach((s) => s.remove());
        if (el.dataset.busyDisabled === '1') {
            el.disabled = false;
            delete el.dataset.busyDisabled;
        }
    });
    document.querySelectorAll('form[data-submitted="1"]').forEach((f) => delete f.dataset.submitted);
    progress.done();
}

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-busy') || form.target === '_blank') return;
    if (form.dataset.submitted === '1') {
        e.preventDefault();
        return;
    }
    if (e.defaultPrevented) return;

    form.dataset.submitted = '1';
    const submitter = e.submitter ?? form.querySelector('button[type=submit], button:not([type])');
    markBusy(submitter);
    progress.start();
    progress.safety();

    setTimeout(() => {
        form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => {
            if (b.disabled) return;
            b.disabled = true;
            b.dataset.busyDisabled = '1';
            b.dataset.busy = '1';
        });
    }, 0);
});

document.addEventListener('click', (e) => {
    const link = e.target instanceof Element ? e.target.closest('a[href]') : null;
    if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-busy')) return;

    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return;

    if (link.dataset.busy === '1') {
        e.preventDefault();
        return;
    }
    markBusy(link);
    progress.start();
    progress.safety();
});

// Back/forward cache restores the page as it was: clear any busy state.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) resetBusy();
});

Alpine.start();

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
