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
