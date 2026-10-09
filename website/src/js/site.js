// Nebo Stage website interactions. No dependencies.
(() => {
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ---------- header: solid after the hero, hidden while scrolling down */
    const header = $('[data-header]');
    const progress = $('[data-progress]');
    const wa = $('.wa-float');
    let lastY = scrollY;
    let ticking = false;
    const onScroll = () => {
        const y = scrollY;
        header.classList.toggle('is-solid', y > 40);
        header.classList.toggle('is-hidden', y > lastY && y > 400 && !document.body.classList.contains('menu-open'));
        wa?.classList.toggle('is-visible', y > 500);
        const max = document.documentElement.scrollHeight - innerHeight;
        progress?.style.setProperty('--p', max > 0 ? (y / max).toFixed(4) : 0);
        lastY = y;
        parallax();
        litWords();
        ticking = false;
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });

    /* ---------- mobile menu */
    const toggle = $('[data-menu-toggle]');
    const menu = $('[data-menu]');
    const setMenu = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('menu-open', open);
        if (open) { menu.hidden = false; requestAnimationFrame(() => menu.classList.add('is-open')); header.classList.add('is-solid'); }
        else { menu.classList.remove('is-open'); setTimeout(() => { if (!menu.classList.contains('is-open')) menu.hidden = true; }, 700); }
    };
    toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
    menu?.addEventListener('click', (e) => { if (e.target.closest('a')) setMenu(false); });
    addEventListener('keydown', (e) => { if (e.key === 'Escape' && document.body.classList.contains('menu-open')) setMenu(false); });

    /* ---------- split headings into words that rise in */
    $$('[data-split]').forEach((el) => {
        let i = 0;
        const walk = (node) => {
            [...node.childNodes].forEach((child) => {
                if (child.nodeType === 3) {
                    const frag = document.createDocumentFragment();
                    child.textContent.split(/(\s+)/).forEach((part) => {
                        if (!part) return;
                        if (/^\s+$/.test(part)) { frag.append(' '); return; }
                        const w = document.createElement('span');
                        w.className = 'w';
                        w.innerHTML = '<span></span>';
                        w.firstChild.textContent = part;
                        w.firstChild.style.setProperty('--i', i++);
                        frag.append(w);
                    });
                    child.replaceWith(frag);
                } else if (child.nodeType === 1) walk(child);
            });
        };
        walk(el);
    });

    /* ---------- statement: words light up as you scroll */
    const statements = $$('[data-words]').map((el) => {
        const words = el.textContent.trim().split(/\s+/);
        el.innerHTML = words.map((w) => `<span class="word${/experiences|impressions|powerful/i.test(w) ? ' is-red' : ''}">${w.replace(/</g, '&lt;')}</span>`).join(' ');
        return { el, words: $$('.word', el) };
    });
    function litWords() {
        statements.forEach(({ el, words }) => {
            const r = el.getBoundingClientRect();
            const p = Math.min(1, Math.max(0, (innerHeight * 0.85 - r.top) / (r.height + innerHeight * 0.35)));
            const n = reduced ? words.length : Math.round(p * words.length);
            words.forEach((w, i) => w.classList.toggle('is-lit', i < n));
        });
    }

    /* ---------- reveal on scroll, counters, step line */
    const count = (el) => {
        const end = +el.dataset.count;
        const suffix = el.dataset.suffix || '';
        if (reduced) { el.textContent = end + suffix; return; }
        const t0 = performance.now();
        const dur = 1600;
        const tick = (t) => {
            const k = Math.min(1, (t - t0) / dur);
            el.textContent = Math.round(end * (1 - Math.pow(1 - k, 4))) + suffix;
            if (k < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (!e.isIntersecting) return;
            const el = e.target;
            el.classList.add('is-in');
            if (el.dataset.count) count(el);
            $$('[data-count]', el).forEach(count);
            io.unobserve(el);
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    $$('[data-reveal], [data-split], .steps').forEach((el) => io.observe(el));
    $$('[data-count]').forEach((el) => { if (!el.closest('[data-reveal]')) io.observe(el); });

    /* ---------- parallax hero images */
    const parallaxEls = $$('[data-parallax]');
    function parallax() {
        if (reduced) return;
        parallaxEls.forEach((el) => {
            const r = el.parentElement.getBoundingClientRect();
            if (r.bottom < 0) return;
            el.style.transform = `translate3d(0, ${(-r.top * 0.25).toFixed(1)}px, 0)`;
        });
    }

    /* ---------- home hero slideshow */
    const slides = $$('[data-slides] .hero__slide');
    if (slides.length > 1 && !reduced) {
        let s = 0;
        setInterval(() => {
            slides[s].classList.remove('is-active');
            s = (s + 1) % slides.length;
            const img = $('img', slides[s]);
            img.style.animation = 'none';
            void img.offsetWidth;
            img.style.animation = '';
            slides[s].classList.add('is-active');
        }, 6500);
    }

    /* ---------- hero phone: our videos play one after another */
    const reel = $$('[data-reel-video]');
    const reelName = $('[data-reel-name]');
    if (reel.length) {
        let r = 0;
        const play = (i) => {
            reel.forEach((v, j) => { v.classList.toggle('is-active', j === i); if (j !== i) v.pause(); });
            reel[i].preload = 'auto';
            reel[i].currentTime = 0;
            reel[i].play().catch(() => {});
            if (reelName) reelName.textContent = reel[i].dataset.name;
            reel[(i + 1) % reel.length].preload = 'auto';
        };
        reel.forEach((v) => v.addEventListener('ended', () => { r = (r + 1) % reel.length; play(r); }));
        if (!reduced) play(0);
    }

    /* ---------- project previews play (muted) while on screen */
    const vio = new IntersectionObserver((entries) => entries.forEach((e) => {
        const v = e.target;
        if (e.isIntersecting && !reduced) { v.preload = 'auto'; v.play().catch(() => {}); } else v.pause();
    }), { threshold: 0.4 });
    $$('video[data-autoplay]').forEach((v) => vio.observe(v));

    /* ---------- pointer effects */
    if (finePointer && !reduced) {
        $$('[data-spotlight]').forEach((el) => el.addEventListener('pointermove', (e) => {
            const r = el.getBoundingClientRect();
            el.style.setProperty('--mx', `${e.clientX - r.left}px`);
            el.style.setProperty('--my', `${e.clientY - r.top}px`);
        }));
        $$('[data-magnetic]').forEach((el) => {
            el.addEventListener('pointermove', (e) => {
                const r = el.getBoundingClientRect();
                el.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * 0.18}px, ${(e.clientY - r.top - r.height / 2) * 0.28}px)`;
            });
            el.addEventListener('pointerleave', () => { el.style.transform = ''; });
        });
    }

    /* ---------- drag-to-scroll strips */
    $$('[data-drag]').forEach((el) => {
        let down = false, startX = 0, startLeft = 0, moved = false;
        el.addEventListener('pointerdown', (e) => { if (e.pointerType !== 'mouse') return; down = true; moved = false; startX = e.clientX; startLeft = el.scrollLeft; });
        addEventListener('pointermove', (e) => {
            if (!down) return;
            const dx = e.clientX - startX;
            if (Math.abs(dx) > 5) { moved = true; el.classList.add('is-dragging'); }
            el.scrollLeft = startLeft - dx;
        });
        addEventListener('pointerup', () => { down = false; setTimeout(() => el.classList.remove('is-dragging'), 0); });
        el.addEventListener('click', (e) => { if (moved) { e.preventDefault(); moved = false; } }, true);
        el.addEventListener('dragstart', (e) => e.preventDefault());
    });

    /* ---------- gallery filters (by service, linkable with #slug) */
    const filterBtns = $$('[data-filter]');
    const groups = $$('[data-group]');
    const applyFilter = (slug, scroll) => {
        if (!groups.length) return;
        if (slug !== 'all' && !groups.some((g) => g.dataset.group === slug)) slug = 'all';
        filterBtns.forEach((b) => {
            const on = b.dataset.filter === slug;
            b.setAttribute('aria-selected', String(on));
            if (on) b.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduced ? 'auto' : 'smooth' });
        });
        groups.forEach((g) => {
            g.classList.toggle('is-hidden', slug !== 'all' && g.dataset.group !== slug);
            $$('[data-reveal]', g).forEach((el) => el.classList.add('is-in'));
        });
        if (scroll) $('[data-filters]').scrollIntoView({ behavior: reduced ? 'auto' : 'smooth' });
    };
    filterBtns.forEach((b) => b.addEventListener('click', () => {
        const slug = b.dataset.filter;
        history.replaceState(null, '', slug === 'all' ? location.pathname : `#${slug}`);
        applyFilter(slug, false);
    }));
    if (groups.length && location.hash) applyFilter(location.hash.slice(1), true);

    /* ---------- equipment catalogue tabs */
    $$('[data-tabs]').forEach((root) => {
        const tabs = $$('[role="tab"]', root);
        const panels = $$('[role="tabpanel"]', root);
        const select = (i, focus, scroll) => {
            tabs.forEach((t, j) => { t.setAttribute('aria-selected', String(i === j)); t.tabIndex = i === j ? 0 : -1; });
            panels.forEach((p, j) => p.classList.toggle('is-active', i === j));
            if (focus) tabs[i].focus();
            tabs[i].scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduced ? 'auto' : 'smooth' });
            if (scroll && panels[i].getBoundingClientRect().top < 0) panels[i].scrollIntoView({ behavior: reduced ? 'auto' : 'smooth' });
            history.replaceState(null, '', `#${panels[i].id}`);
        };
        tabs.forEach((t, i) => {
            t.addEventListener('click', () => select(i, false, true));
            t.addEventListener('keydown', (e) => {
                const k = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
                if (k) { e.preventDefault(); select((i + k + tabs.length) % tabs.length, true, false); }
                if (e.key === 'Home') { e.preventDefault(); select(0, true, false); }
                if (e.key === 'End') { e.preventDefault(); select(tabs.length - 1, true, false); }
            });
        });
        const fromHash = panels.findIndex((p) => `#${p.id}` === location.hash);
        if (fromHash > 0) select(fromHash, false, false);
    });

    /* ---------- lightbox for photos and videos */
    const lb = $('[data-lightbox]');
    const stage = $('[data-lb-stage]', lb);
    const caption = $('[data-lb-caption]', lb);
    const prev = $('[data-lb-prev]', lb);
    const next = $('[data-lb-next]', lb);
    let list = [];
    let index = 0;
    let opener = null;
    const visibleItems = (group) => $$(`[data-lb-item][data-lb-group="${group}"]`).filter((el) => el.offsetParent !== null || el.closest('.hero'));
    const show = (i) => {
        index = (i + list.length) % list.length;
        const item = list[index];
        stage.innerHTML = '';
        if (item.dataset.lbType === 'video') {
            const v = document.createElement('video');
            v.src = item.dataset.lbSrc;
            v.poster = item.dataset.lbPoster || '';
            v.controls = true;
            v.autoplay = true;
            v.playsInline = true;
            stage.append(v);
        } else {
            const img = new Image();
            img.src = item.dataset.lbSrc;
            img.alt = item.dataset.lbCaption || '';
            stage.append(img);
        }
        caption.textContent = item.dataset.lbCaption || '';
        prev.hidden = next.hidden = list.length < 2;
    };
    const open = (items, i, from) => {
        if (!items.length) return;
        list = items;
        opener = from;
        lb.hidden = false;
        requestAnimationFrame(() => lb.classList.add('is-open'));
        document.body.style.overflow = 'hidden';
        $$('video[data-autoplay], [data-reel-video]').forEach((v) => v.pause());
        show(i);
        $('[data-lb-close]', lb).focus();
    };
    const close = () => {
        lb.classList.remove('is-open');
        stage.innerHTML = '';
        document.body.style.overflow = '';
        setTimeout(() => { lb.hidden = true; }, 300);
        const active = $('[data-reel-video].is-active');
        if (active && !reduced) active.play().catch(() => {});
        opener?.focus();
    };
    document.addEventListener('click', (e) => {
        const item = e.target.closest('[data-lb-item]');
        if (item) {
            e.preventDefault();
            const items = visibleItems(item.dataset.lbGroup);
            open(items, Math.max(0, items.indexOf(item)), item);
            return;
        }
        const opener = e.target.closest('[data-lb-open]');
        if (opener) {
            const all = $$(`[data-lb-item][data-lb-group="${opener.dataset.lbOpen}"]`);
            const seen = new Set();
            const unique = all.filter((el) => !seen.has(el.dataset.lbSrc) && seen.add(el.dataset.lbSrc));
            const activeName = $('[data-reel-video].is-active')?.dataset.name;
            const start = Math.max(0, unique.findIndex((el) => (el.dataset.lbCaption || '').startsWith(activeName)));
            open(unique, start, opener);
        }
    });
    $('[data-lb-close]', lb).addEventListener('click', close);
    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    lb.addEventListener('click', (e) => { if (e.target === lb || e.target === stage) close(); });
    addEventListener('keydown', (e) => {
        if (lb.hidden) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
    let touchX = null;
    lb.addEventListener('touchstart', (e) => { touchX = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', (e) => {
        if (touchX === null || list.length < 2) return;
        const dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
        touchX = null;
    });

    onScroll();
})();
