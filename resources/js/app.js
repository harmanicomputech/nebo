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
 | Prevent double submission: forms with data-once disable their submit
 | buttons after the first submit.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-once')) return;
    if (form.dataset.submitted === '1') {
        e.preventDefault();
        return;
    }
    form.dataset.submitted = '1';
    form.querySelectorAll('button[type=submit]').forEach((b) => {
        b.disabled = true;
        b.classList.add('opacity-70', 'cursor-wait');
    });
});

Alpine.start();

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
