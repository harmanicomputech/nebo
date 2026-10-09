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
    let pill;
    let timer;
    const el = () => {
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'nav-progress';
            bar.setAttribute('aria-hidden', 'true');
            // Phones: a "Loading…" pill under the top bar, so a tap visibly did something (D72).
            pill = document.createElement('div');
            pill.className = 'nav-loading';
            pill.setAttribute('role', 'status');
            pill.innerHTML = '<span class="busy-spinner" aria-hidden="true"></span>';
            pill.append(document.createTextNode('Loading…'));
            document.body.append(bar, pill);
        }
        return bar;
    };
    return {
        start() {
            const b = el();
            b.dataset.state = '';
            void b.offsetWidth; // restart the transition
            b.dataset.state = 'running';
            pill.dataset.state = 'running';
        },
        done() {
            if (bar) bar.dataset.state = 'done';
            if (pill) pill.dataset.state = 'done';
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

/*
 | Instant press feedback on touch screens (D72). The pressed look is set on
 | pointerdown, before the browser even decides it is a tap, and an empty
 | touchstart listener makes iOS Safari apply :active styles at all.
 */
document.addEventListener('touchstart', () => {}, { passive: true });
const pressable = 'a[href], button, summary, label, [role="button"], [data-tab]';
let pressed = null;
const release = () => {
    pressed?.classList.remove('is-pressed');
    pressed = null;
};
document.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse' || !(e.target instanceof Element)) return;
    const el = e.target.closest(pressable);
    if (!el || el.matches(':disabled')) return;
    release();
    pressed = el;
    el.classList.add('is-pressed');
}, { passive: true });
['pointerup', 'pointercancel'].forEach((type) => document.addEventListener(type, () => setTimeout(release, 120), { passive: true }));
document.addEventListener('scroll', release, { passive: true, capture: true });

// Bottom tabs: the tapped tab lights up at once, before the next page arrives.
const currentTab = document.querySelector('[data-tab][aria-current="page"]');
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return; // back/forward cache: show this page's own tab again
    document.querySelectorAll('[data-tab]').forEach((t) => t.toggleAttribute('aria-current', false));
    currentTab?.setAttribute('aria-current', 'page');
});
document.addEventListener('click', (e) => {
    const tab = e.target instanceof Element ? e.target.closest('a[data-tab]') : null;
    if (!tab || e.defaultPrevented) return;
    tab.closest('nav')?.querySelectorAll('[data-tab]').forEach((t) => t.removeAttribute('aria-current'));
    tab.setAttribute('aria-current', 'page');
});

// Back/forward cache restores the page as it was: clear any busy state.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) resetBusy();
});

/*
 | Real-time notifications (D75).
 |
 | - Open pages ask the server what's new every 15 seconds (and straight
 |   away when the tab comes back into view): the bells update and each new
 |   notification pops up as a card.
 | - With notifications turned on for the device, the service worker shows
 |   a system notification even when the app is closed; if a page is open
 |   and in front, it shows the card instead.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const isIos = () => /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;

const keyBytes = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
};

const popups = (() => {
    const shown = new Set();
    let stack;
    const container = () => {
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'notify-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        return stack;
    };
    return {
        show(item) {
            if (!item?.id || shown.has(item.id)) return;
            shown.add(item.id);
            const card = document.createElement('a');
            card.className = `notify-card notify-${item.level || 'info'}`;
            card.href = item.url || '#';
            const icon = document.createElement('span');
            icon.className = 'notify-icon';
            icon.innerHTML = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>';
            const text = document.createElement('span');
            text.className = 'notify-text';
            const title = document.createElement('strong');
            title.textContent = item.title || 'Notification';
            const body = document.createElement('span');
            body.textContent = item.body || '';
            text.append(title, body);
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'notify-close';
            close.setAttribute('aria-label', 'Dismiss');
            close.textContent = '×';
            close.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); remove(); });
            card.append(icon, text, close);
            const remove = () => { card.classList.add('notify-out'); setTimeout(() => card.remove(), 200); };
            const box = container();
            box.prepend(card);
            [...box.children].slice(3).forEach((old) => old.remove());
            setTimeout(remove, 9000);
            if (navigator.vibrate && document.visibilityState === 'visible') navigator.vibrate(40);
        },
    };
})();

const notifier = (() => {
    const body = document.body;
    if (!body.dataset.pollUrl) return null;
    let since = body.dataset.pollSince;
    let stopped = false;

    const setBadges = (count) => {
        document.querySelectorAll('[data-unread-badge]').forEach((b) => {
            b.textContent = b.hasAttribute('data-full') || count < 10 ? String(count) : '9+';
            b.hidden = count === 0;
        });
    };

    const poll = async () => {
        if (stopped || document.visibilityState !== 'visible') return;
        try {
            const res = await fetch(`${body.dataset.pollUrl}?since=${encodeURIComponent(since)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (res.status === 401 || res.status === 419) { stopped = true; return; }
            if (!res.ok) return;
            const data = await res.json();
            since = data.now;
            setBadges(data.unread);
            data.items.forEach((item) => popups.show(item));
        } catch { /* offline: try again next time */ }
    };

    setInterval(poll, 15000);
    document.addEventListener('visibilitychange', poll);
    navigator.serviceWorker?.addEventListener('message', (e) => {
        if (e.data?.type !== 'nebo:notification') return;
        const item = e.data.item || {};
        popups.show({ id: item.tag, title: item.title, body: item.body, url: item.url });
        poll();
    });
    return { poll };
})();

Alpine.data('pushToggle', () => ({
    state: 'checking', // checking | unsupported | ios-install | denied | off | on
    busy: false,
    dismissed: false,
    get prompt() {
        return !this.dismissed && (this.state === 'off' || this.state === 'ios-install');
    },
    async init() {
        try { this.dismissed = localStorage.getItem('nebo.push.prompt') === 'dismissed'; } catch { /* storage blocked */ }
        if (!pushSupported()) {
            this.state = isIos() && !isStandalone() ? 'ios-install' : 'unsupported';
            return;
        }
        if (Notification.permission === 'denied') { this.state = 'denied'; return; }
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        this.state = sub && Notification.permission === 'granted' ? 'on' : 'off';
        if (this.state === 'on') this.save(sub); // keeps the server's copy current for whoever is signed in
    },
    async save(sub) {
        await fetch(document.body.dataset.pushUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body: JSON.stringify(sub.toJSON()) });
    },
    async enable() {
        this.busy = true;
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') { this.state = permission === 'denied' ? 'denied' : 'off'; return; }
            const reg = await navigator.serviceWorker.ready;
            const sub = (await reg.pushManager.getSubscription())
                || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(document.body.dataset.pushKey) });
            await this.save(sub);
            this.state = 'on';
            popups.show({ id: `enabled-${Date.now()}`, title: 'Notifications are on', body: 'You will get a pop-up on this device for every new notification.', level: 'success', url: '#' });
        } catch {
            this.state = 'off';
        } finally {
            this.busy = false;
        }
    },
    async disable() {
        this.busy = true;
        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                await fetch(document.body.dataset.pushUrl, { method: 'DELETE', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body: JSON.stringify({ endpoint: sub.endpoint }) });
                await sub.unsubscribe();
            }
            this.state = 'off';
        } finally {
            this.busy = false;
        }
    },
    dismiss() {
        this.dismissed = true;
        try { localStorage.setItem('nebo.push.prompt', 'dismissed'); } catch { /* storage blocked */ }
    },
}));

Alpine.start();

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
