// Builds the Nebo Stage website into website/dist: plain HTML, CSS and JS
// that any web host can serve. No dependencies: `node website/build.mjs`.
import { createHash } from 'node:crypto';
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { departments, equipment, eventsWeEquip, eventTypes, gallery, projects, promise, services, site, steps } from './content.mjs';
import { icon } from './icons.mjs';

const root = dirname(fileURLToPath(import.meta.url));
const src = join(root, 'src');
const dist = join(root, 'dist');
const repo = join(root, '..');

// ---------------------------------------------------------------- helpers

const esc = (s = '') => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const pad = (n) => String(n).padStart(2, '0');
const serviceBySlug = Object.fromEntries(services.map((s) => [s.slug, s]));
const projectBySlug = Object.fromEntries(projects.map((p) => [p.slug, p]));
const requestUrl = (slug) => `${site.app}/request${slug ? `?service=${slug}` : ''}`;
const telUrl = `tel:${site.phone.replace(/[^+0-9]/g, '')}`;
const waUrl = (text = 'Hello Nebo Stage, I would like to make an enquiry about an event.') => `https://wa.me/${site.whatsapp}?text=${encodeURIComponent(text)}`;
const hash = (file) => createHash('sha1').update(readFileSync(file)).digest('hex').slice(0, 10);

/** Responsive <img> for an image in src/images (name-800.webp / name-1600.webp). */
function img(name, alt, { sizes = '100vw', cls = '', eager = false, attrs = '' } = {}) {
    const has1600 = existsSync(join(src, 'images', `${name}-1600.webp`));
    const srcset = has1600 ? ` srcset="/images/${name}-800.webp 800w, /images/${name}-1600.webp 1600w" sizes="${sizes}"` : '';
    return `<img src="/images/${name}-800.webp"${srcset} alt="${esc(alt)}" class="${cls}" ${eager ? 'fetchpriority="high"' : 'loading="lazy"'} decoding="async" ${attrs}>`;
}
const largest = (name) => `/images/${name}-${existsSync(join(src, 'images', `${name}-1600.webp`)) ? 1600 : 800}.webp`;

const button = (href, label, { variant = 'primary', ico = 'arrow-right', external = false, cls = '' } = {}) =>
    `<a href="${href}" class="btn btn--${variant} ${cls}" data-magnetic${external ? ' target="_blank" rel="noopener"' : ''}><span>${label}</span>${ico ? icon(ico) : ''}</a>`;

const eyebrow = (text) => `<p class="eyebrow" data-reveal>${text}</p>`;

// ---------------------------------------------------------------- layout

const nav = [
    { href: '/', label: 'Home' },
    { href: '/about/', label: 'About' },
    { href: '/services/', label: 'Services', mega: true },
    { href: '/equipment/', label: 'Equipment' },
    { href: '/gallery/', label: 'Gallery' },
    { href: '/contact/', label: 'Contact' },
];

function header(path) {
    const active = (href) => (path === href || (href !== '/' && path.startsWith(href)) ? ' aria-current="page"' : '');
    const mega = `<div class="mega" role="group" aria-label="Services">
        <div class="mega__grid">${services.map((s, i) => `<a href="/services/${s.slug}/" class="mega__item"><span class="mega__num">${pad(i + 1)}</span><span><strong>${esc(s.name)}</strong><small>${esc(s.short)}</small></span></a>`).join('')}</div>
        <a href="/services/" class="mega__all">All services ${icon('arrow-right')}</a>
    </div>`;
    return `<header class="site-header" data-header>
    <div class="site-header__inner">
        <a href="/" class="brand" aria-label="${site.name} home"><img src="/brand/nebo-stage-white.svg" alt="${site.name}" width="148" height="40"></a>
        <nav class="main-nav" aria-label="Main">
            <ul>${nav.map((n) => `<li${n.mega ? ' class="has-mega"' : ''}><a href="${n.href}"${active(n.href)}>${n.label}${n.mega ? icon('chevron-down') : ''}</a>${n.mega ? mega : ''}</li>`).join('')}</ul>
        </nav>
        <div class="site-header__actions">
            <a href="${telUrl}" class="header-phone">${icon('phone')}<span>${site.phone}</span></a>
            ${button(requestUrl(), 'Book now', { cls: 'btn--sm hide-mobile' })}
            <button type="button" class="menu-toggle" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu"><span></span><span></span><span class="sr-only">Menu</span></button>
        </div>
    </div>
    <div class="scroll-progress" data-progress></div>
</header>
<div class="mobile-menu" id="mobile-menu" data-menu hidden>
    <nav aria-label="Mobile">
        ${nav.map((n, i) => `<a href="${n.href}" style="--i:${i}"${active(n.href)}>${n.label}</a>`).join('')}
    </nav>
    <div class="mobile-menu__services">${services.map((s) => `<a href="/services/${s.slug}/">${esc(s.name)}</a>`).join('')}</div>
    <div class="mobile-menu__cta">
        ${button(requestUrl(), 'Book now')}
        <a href="${telUrl}" class="mobile-menu__phone">${icon('phone')} ${site.phone}</a>
    </div>
</div>`;
}

function footer() {
    return `<footer class="site-footer">
    <div class="footer-cta">
        <div class="beams" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="container footer-cta__inner">
            <p class="eyebrow eyebrow--light" data-reveal>Are you ready?</p>
            <h2 class="display" data-split>Let’s build your stage.</h2>
            <p class="lead" data-reveal>We don’t just provide event equipment — we create powerful visual experiences that command attention and leave lasting impressions.</p>
            <div class="btn-row" data-reveal>
                ${button(requestUrl(), 'Request a quotation')}
                ${button(waUrl(), 'Chat on WhatsApp', { variant: 'ghost', ico: 'whatsapp', external: true })}
            </div>
        </div>
    </div>
    <div class="container footer-grid">
        <div class="footer-brand">
            <img src="/brand/nebo-stage-white.svg" alt="${site.name}" width="170" height="46" loading="lazy">
            <p>${esc(site.tagline)}. Stages, truss and roof systems, LED screens, lighting, sound, video and livestreaming — delivered, installed and run by one team.</p>
            <div class="socials">${site.socials.map((s) => `<a href="${s.url}" target="_blank" rel="noopener" aria-label="${s.name} ${s.handle}">${icon(s.name.toLowerCase())}</a>`).join('')}</div>
        </div>
        <div>
            <h3>Services</h3>
            <ul>${services.map((s) => `<li><a href="/services/${s.slug}/">${esc(s.name)}</a></li>`).join('')}</ul>
        </div>
        <div>
            <h3>Company</h3>
            <ul>
                <li><a href="/about/">About us</a></li>
                <li><a href="/gallery/">Gallery</a></li>
                <li><a href="/equipment/">Equipment</a></li>
                <li><a href="/contact/">Contact</a></li>
                <li><a href="${requestUrl()}">Book now</a></li>
                <li><a href="${site.app}/track">Track your request</a></li>
            </ul>
        </div>
        <div>
            <h3>Talk to us</h3>
            <ul class="footer-contact">
                <li><a href="${telUrl}">${icon('phone')}${site.phone}</a></li>
                <li><a href="mailto:${site.email}">${icon('mail')}${site.email}</a></li>
                <li><a href="${waUrl()}" target="_blank" rel="noopener">${icon('whatsapp')}WhatsApp</a></li>
                <li><span>${icon('map')}Available nationwide</span></li>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>© ${new Date().getFullYear()} ${site.name}. All rights reserved.</p>
        <p>Reliable. Professional. Stunning.</p>
        <p>Website by <a href="https://techatronagency.com" target="_blank" rel="noopener">Techatron Consulting Limited</a></p>
    </div>
</footer>
<a href="${waUrl()}" class="wa-float" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">${icon('whatsapp')}</a>
<div class="lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="Media viewer">
    <button type="button" class="lightbox__close" data-lb-close aria-label="Close">${icon('close')}</button>
    <button type="button" class="lightbox__nav lightbox__nav--prev" data-lb-prev aria-label="Previous">${icon('arrow-left')}</button>
    <figure class="lightbox__stage" data-lb-stage></figure>
    <button type="button" class="lightbox__nav lightbox__nav--next" data-lb-next aria-label="Next">${icon('arrow-right')}</button>
    <p class="lightbox__caption" data-lb-caption></p>
</div>`;
}

let assets = {};

function layout({ path, title, description = site.description, image = 'mobile-stage-roof', body, schema = [] }) {
    const fullTitle = path === '/' ? `${site.name} — Stages, LED Screens, Lighting & Sound, Nationwide` : `${title} | ${site.name}`;
    const org = {
        '@context': 'https://schema.org', '@type': 'LocalBusiness', name: site.name, url: site.url, email: site.email, telephone: site.phone,
        image: site.url + largest('mobile-stage-roof'), logo: `${site.url}/brand/nebo-stage.png`, description: site.description,
        areaServed: { '@type': 'Country', name: 'Nigeria' }, sameAs: site.socials.map((s) => s.url),
    };
    return `<!DOCTYPE html>
<html lang="en-NG" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${esc(fullTitle)}</title>
<meta name="description" content="${esc(description)}">
<link rel="canonical" href="${site.url}${path}">
<meta name="theme-color" content="#0b0b0b">
<meta property="og:type" content="website">
<meta property="og:site_name" content="${site.name}">
<meta property="og:title" content="${esc(fullTitle)}">
<meta property="og:description" content="${esc(description)}">
<meta property="og:url" content="${site.url}${path}">
<meta property="og:image" content="${site.url}${largest(image)}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/brand/nebo-stage-mark.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preload" href="/fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/site.css?v=${assets.css}">
<script src="/js/boot.js"></script>
<script src="/js/site.js?v=${assets.js}" defer></script>
${[org, ...schema].map((s) => `<script type="application/ld+json">${JSON.stringify(s)}</script>`).join('\n')}
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
${header(path)}
<main id="main">
${body}
</main>
${footer()}
</body>
</html>
`;
}

// ---------------------------------------------------------------- shared sections

function pageHero({ eyebrowText, title, lead, image, crumbs = [], actions = '' }) {
    return `<section class="page-hero">
    <div class="page-hero__media" data-parallax>${img(image, '', { eager: true, cls: 'kenburns' })}</div>
    <div class="page-hero__shade"></div>
    <div class="beams beams--soft" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="container page-hero__inner">
        ${crumbs.length ? `<nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a>${crumbs.map(([href, label]) => (href ? `<span>/</span><a href="${href}">${esc(label)}</a>` : `<span>/</span><span aria-current="page">${esc(label)}</span>`)).join('')}</nav>` : ''}
        <p class="eyebrow eyebrow--light hero-in" style="--d:.1s">${eyebrowText}</p>
        <h1 class="display display--xl" data-split>${esc(title)}</h1>
        ${lead ? `<p class="lead hero-in" style="--d:.5s">${esc(lead)}</p>` : ''}
        ${actions ? `<div class="btn-row hero-in" style="--d:.65s">${actions}</div>` : ''}
    </div>
</section>`;
}

function serviceCard(s, i, { big = false } = {}) {
    return `<a href="/services/${s.slug}/" class="service-card${big ? ' service-card--big' : ''}" data-reveal data-spotlight style="--stagger:${i % 3}">
    <div class="service-card__media">${img(s.hero, '', { sizes: '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw' })}</div>
    <div class="service-card__body">
        <span class="service-card__num">${pad(i + 1)}</span>
        <span class="service-card__icon">${icon(`svc-${s.slug}`)}</span>
        <h3>${esc(s.name)}</h3>
        <p>${esc(s.short)}</p>
        <span class="service-card__more">Explore ${icon('arrow-right')}</span>
    </div>
</a>`;
}

function projectCard(p) {
    const tags = p.services.map((slug) => `<a href="/services/${slug}/" class="tag">${esc(serviceBySlug[slug].name)}</a>`).join('');
    return `<article class="project" data-reveal>
    <button type="button" class="project__video" data-lb-item data-lb-group="work" data-lb-type="video" data-lb-src="/media/${p.video}.mp4" data-lb-poster="/images/${p.poster}.webp" data-lb-caption="${esc(`${p.name} — ${p.kind}`)}" aria-label="Play the ${esc(p.name)} video">
        <video src="/media/${p.video}.mp4#t=0.1" poster="/images/${p.poster}.webp" muted loop playsinline preload="none" data-autoplay></video>
        <span class="project__play">${icon('play')}</span>
        <span class="project__live"><i></i>Live event footage</span>
    </button>
    <div class="project__body">
        <p class="eyebrow">${esc(p.kind)}</p>
        <h3 class="display display--md">${esc(p.name)}</h3>
        <p>${esc(p.summary)}</p>
        <div class="tags">${tags}</div>
    </div>
</article>`;
}

function galleryGrid(items, group) {
    return `<div class="masonry">${items.map((g) => `<button type="button" class="masonry__item" data-reveal data-lb-item data-lb-group="${group}" data-lb-src="${largest(g.image)}" data-lb-caption="${esc(g.alt)}">
        ${img(g.image, g.alt, { sizes: '(min-width: 1024px) 33vw, 50vw' })}
        ${g.project ? `<span class="badge-real">${icon('play')}${esc(projectBySlug[g.project].name)}</span>` : ''}
        <span class="masonry__zoom">${icon('expand')}</span>
    </button>`).join('')}</div>`;
}

const marquee = (items, { reverse = false, cls = '' } = {}) => `<div class="marquee ${cls}${reverse ? ' marquee--reverse' : ''}" aria-hidden="true"><div class="marquee__track">${[...items, ...items].map((t) => `<span>${esc(t)}</span><i>✦</i>`).join('')}</div></div>`;

// ---------------------------------------------------------------- pages

function home() {
    const heroSlides = ['production-concert-dancers', 'truss-red-beams', 'mobile-stage-roof', 'lighting-green-tubes', 'sound-dj-led-wall'];
    const kit = [
        { value: 24, suffix: ' m²', label: 'P3.91 outdoor LED screen', text: '72 panels and 3 NovaStar VX600 Pro processors', href: '/services/led-screens-displays/' },
        { value: 178, suffix: ' m', label: 'Aluminium spigot truss', text: '400 × 600 and 400 × 400, with 350 connectors and 900 pins', href: '/services/trussing-rigging/' },
        { value: 50, suffix: '', label: 'Stage deck panels', text: 'With staircases and a 6-tower roof system for outdoor stages', href: '/services/stage-rigging/' },
        { value: 6, suffix: '', label: 'Galvanised chain hoists', text: 'With 2-tonne lifting straps for roofs, screens and flown truss', href: '/services/trussing-rigging/' },
    ];
    const statement = 'At Nebo Stage, we don’t just provide event equipment — we create powerful visual experiences that command attention and leave lasting impressions.';
    const realStills = gallery.filter((g) => g.project);
    const strip = [...realStills, ...gallery.filter((g) => !g.project).slice(0, 8)];

    const body = `
<section class="stage-hero">
    <div class="stage-hero__haze" aria-hidden="true"></div>
    <div class="rig" aria-hidden="true">
        <div class="rig__truss"></div>
        ${[8, 22, 36, 50, 64, 78, 92].map((x, i) => `<span class="fixture fixture--${['red', 'white', 'amber', 'red', 'amber', 'white', 'red'][i]}" style="--x:${x}%;--i:${i}"><i></i></span>`).join('')}
    </div>
    <div class="stage-hero__wall">
        <div class="screens">
            ${projects.map((p, i) => {
                const side = `<button type="button" class="screen screen--side" style="--d:${i ? '.55s' : '.35s'}" data-lb-item data-lb-group="hero" data-lb-type="video" data-lb-src="/media/${p.video}.mp4" data-lb-poster="/images/${p.poster}.webp" data-lb-caption="${esc(`${p.name} — ${p.kind}`)}" aria-label="Play the ${esc(p.name)} video with sound">
                <video src="/media/${p.video}.mp4" poster="/images/${p.poster}.webp" muted loop playsinline preload="metadata" data-autoplay></video>
                <span class="screen__tag"><i></i>${esc(p.name)}</span>
                <span class="screen__play">${icon('play')}</span>
            </button>`;
                const main = `<div class="screen screen--main" style="--d:.15s" data-slides>
                ${heroSlides.map((name, j) => `<div class="hero__slide${j === 0 ? ' is-active' : ''}">${img(name, '', { eager: j === 0, cls: 'kenburns', sizes: '(min-width: 860px) 60vw, 100vw' })}</div>`).join('')}
                <span class="screen__tag screen__tag--live"><i></i>Live · P3.91 LED wall</span>
            </div>`;
                return i === 0 ? side + main : side;
            }).join('\n            ')}
        </div>
    </div>
    <div class="container stage-hero__copy">
        <h1 class="hero-title"><span class="line"><span style="--d:.9s">We build</span></span> <span class="line"><span style="--d:1.02s">the <em>stage.</em></span></span></h1>
        <div class="stage-hero__foot">
            <p class="hero-in" style="--d:1.25s"><strong>You own the moment.</strong> Stages, truss and roof systems, LED screens, lighting and sound for concerts, rallies, churches, weddings and corporate events — installed and run by one team, anywhere in Nigeria.</p>
            <div class="btn-row hero-in" style="--d:1.4s">
                ${button(requestUrl(), 'Book now')}
                ${button('/services/', 'Explore services', { variant: 'ghost', ico: 'arrow-down-right' })}
            </div>
        </div>
    </div>
    ${marquee(['Reliable', 'Professional', 'Stunning', 'Nationwide', 'Show-ready'], { cls: 'marquee--hero' })}
</section>

<section class="statement container" id="statement">
    ${eyebrow('Who we are')}
    <p class="statement__text" data-words>${esc(statement)}</p>
    <div class="statement__foot" data-reveal>
        <p>Stages, trusses, roof systems and LED screens for events of every size — corporate functions, concerts, church programmes, weddings and political campaigns — with flawless execution from start to finish.</p>
        ${button('/about/', 'About Nebo Stage', { variant: 'link' })}
    </div>
</section>

<section class="section events-equip" id="events">
    <div class="events-equip__glow" aria-hidden="true"></div>
    <div class="container">
        <div class="events-equip__head">
            <p class="eyebrow eyebrow--light" data-reveal>Whatever you're planning</p>
            <h2 class="display display--xl" data-split>Equipment for All Events.</h2>
            <p class="lead" data-reveal>From a hall to an open field, we bring the stage, screens, lighting and sound your event needs, with the crew to run it.</p>
        </div>
        <ul class="events-equip__grid">${eventsWeEquip.map((e, i) => `<li class="ev-tile" data-reveal style="--stagger:${i % 4}">
            <span class="ev-tile__icon">${icon(e.icon)}</span>
            <h3>${esc(e.name)}</h3>
            <p>${esc(e.text)}</p>
        </li>`).join('')}</ul>
        <div class="events-equip__foot" data-reveal>
            <p>Don't see your event? We equip it too.</p>
            ${button(requestUrl(), 'Tell us about your event', { variant: 'light' })}
        </div>
    </div>
</section>

<section class="section section--dark" id="services">
    <div class="container">
        <div class="section-head">
            <div>${eyebrow('What we do')}<h2 class="display" data-split>Every technical layer of your event.</h2></div>
            <p class="section-head__aside" data-reveal>Pick one service or hand us the whole production. Every service is delivered, installed and operated by our own crew.</p>
        </div>
        <div class="service-grid">${services.map((s, i) => serviceCard(s, i)).join('')}</div>
    </div>
</section>

<section class="section" id="work">
    <div class="container">
        <div class="section-head">
            <div>${eyebrow('Recent work')}<h2 class="display" data-split>See our work in action.</h2></div>
            <p class="section-head__aside" data-reveal>No stock footage: these are our own shows, shot from the floor while the lights were running.</p>
        </div>
        <div class="projects">${projects.map(projectCard).join('')}</div>
    </div>
</section>

<section class="stats-band">
    <div class="container stats">${site.stats.map((s) => `<div class="stat" data-reveal><span class="stat__value" data-count="${s.value}" data-suffix="${esc(s.suffix)}">0${esc(s.suffix)}</span><span class="stat__label">${esc(s.label)}</span></div>`).join('')}</div>
</section>

<section class="section section--tight events">
    <div class="container">${eyebrow('Who we work for')}</div>
    ${marquee(eventTypes.slice(0, 6), { cls: 'marquee--big' })}
    ${marquee(eventTypes.slice(5), { reverse: true, cls: 'marquee--big marquee--outline' })}
</section>

<section class="section section--dark">
    <div class="container">
        <div class="section-head">
            <div>${eyebrow('Our own kit')}<h2 class="display" data-split>Owned, maintained and on the road.</h2></div>
            <p class="section-head__aside" data-reveal>We rent and run our own equipment, so it arrives checked, complete and ready — with crew who know it inside out.</p>
        </div>
        <div class="kit-grid">${kit.map((k, i) => `<a href="${k.href}" class="kit" data-reveal data-spotlight style="--stagger:${i}">
            <span class="kit__value" data-count="${k.value}" data-suffix="${esc(k.suffix)}">0${esc(k.suffix)}</span>
            <strong>${esc(k.label)}</strong><span>${esc(k.text)}</span>${icon('arrow-up-right')}</a>`).join('')}</div>
        <div class="center" data-reveal>${button('/equipment/', 'See the full equipment list', { variant: 'ghost' })}</div>
    </div>
</section>

<section class="section how">
    <div class="container">
        <div class="section-head">
            <div>${eyebrow('How it works')}<h2 class="display" data-split>Three steps to show time.</h2></div>
        </div>
        <ol class="steps" data-steps>${steps.map((s, i) => `<li class="step" data-reveal style="--stagger:${i}"><span class="step__num">${pad(i + 1)}</span><h3>${esc(s.title)}</h3><p>${esc(s.text)}</p></li>`).join('')}</ol>
        <div class="center" data-reveal>${button(requestUrl(), 'Start your request')}</div>
    </div>
</section>

<section class="section section--dark section--flush">
    <div class="container section-head">
        <div>${eyebrow('Gallery')}<h2 class="display" data-split>Light, steel and screens.</h2></div>
        ${button('/gallery/', 'Open the gallery', { variant: 'ghost' })}
    </div>
    <div class="strip" data-drag>${strip.map((g) => `<a href="/gallery/#${g.service}" class="strip__item">${img(g.image, g.alt, { sizes: '360px' })}<span>${esc(g.project ? projectBySlug[g.project].name : serviceBySlug[g.service].name)}</span></a>`).join('')}</div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>${eyebrow('Our promise')}<h2 class="display" data-split>Every event deserves to stand out.</h2></div>
            <p class="section-head__aside" data-reveal>We are committed to quality setups, seamless execution and unforgettable visual impact — every single time.</p>
        </div>
        <div class="values">${promise.map((v, i) => `<div class="value" data-reveal style="--stagger:${i}"><span class="value__icon">${icon(v.icon)}</span><h3>${esc(v.title)}</h3><p>${esc(v.text)}</p></div>`).join('')}</div>
    </div>
</section>`;
    return layout({ path: '/', body });
}

function servicesIndex() {
    const body = `${pageHero({ eyebrowText: 'Services', title: 'Production, end to end.', lead: 'Nine services, one crew. Book a single service or let us run the whole technical production of your event.', image: 'truss-blue-beams', crumbs: [[null, 'Services']], actions: button(requestUrl(), 'Book now') })}
<section class="section">
    <div class="container">
        <div class="service-grid service-grid--index">${services.map((s, i) => serviceCard(s, i)).join('')}</div>
    </div>
</section>
<section class="section section--dark section--tight">
    <div class="container">${eyebrow('Who we work for')}</div>
    ${marquee(eventTypes, { cls: 'marquee--big' })}
</section>`;
    return layout({ path: '/services/', title: 'Services', description: `Event production services from ${site.name}: ${services.map((s) => s.name).join(', ')}. Nationwide in Nigeria.`, image: 'truss-blue-beams', body });
}

function servicePage(s, i) {
    const items = gallery.filter((g) => g.service === s.slug);
    const work = projects.filter((p) => p.services.includes(s.slug));
    const others = services.filter((o) => o.slug !== s.slug);
    const faq = s.faqs.length ? [{
        '@context': 'https://schema.org', '@type': 'FAQPage',
        mainEntity: s.faqs.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })),
    }] : [];
    const serviceSchema = { '@context': 'https://schema.org', '@type': 'Service', name: s.name, description: s.short, provider: { '@type': 'LocalBusiness', name: site.name, url: site.url }, areaServed: { '@type': 'Country', name: 'Nigeria' } };

    const body = `${pageHero({
        eyebrowText: `Service ${pad(i + 1)} / ${pad(services.length)}`, title: s.name, lead: s.tagline, image: s.hero,
        crumbs: [['/services/', 'Services'], [null, s.name]],
        actions: button(requestUrl(s.bookingSlug ?? s.slug), 'Book this service') + (items.length || work.length ? button('#work', 'See the work', { variant: 'ghost', ico: 'arrow-down' }) : button(waUrl(`Hello Nebo Stage, I would like to ask about ${s.name}.`), 'Ask on WhatsApp', { variant: 'ghost', ico: 'whatsapp', external: true })),
    })}

<section class="section">
    <div class="container split">
        <div class="split__main">
            ${eyebrow('Overview')}
            <h2 class="display display--md" data-split>${esc(s.short)}</h2>
            ${s.intro.map((p) => `<p class="prose" data-reveal>${esc(p)}</p>`).join('')}
        </div>
        <aside class="split__aside" data-reveal>
            <div class="aside-card">
                <h3>Perfect for</h3>
                <ul class="checks">${s.perfectFor.map((t) => `<li>${icon('check')}${esc(t)}</li>`).join('')}</ul>
                ${button(requestUrl(s.bookingSlug ?? s.slug), 'Request a quotation', { cls: 'btn--block' })}
                <a href="${telUrl}" class="aside-card__phone">${icon('phone')} ${site.phone}</a>
            </div>
        </aside>
    </div>
</section>

<section class="section section--dark">
    <div class="container">
        <div class="section-head"><div>${eyebrow('What we provide')}<h2 class="display" data-split>${esc(s.name)}, done properly.</h2></div></div>
        <div class="includes">${s.includes.map(([t, d], j) => `<div class="include" data-reveal data-spotlight style="--stagger:${j % 4}"><span class="include__num">${pad(j + 1)}</span><h3>${esc(t)}</h3><p>${esc(d)}</p></div>`).join('')}</div>
    </div>
</section>

${s.kit.length ? `<section class="section">
    <div class="container split split--reverse">
        <div class="split__main">
            ${eyebrow('From our inventory')}
            <h2 class="display display--md" data-split>Our own equipment, ready to go.</h2>
            <p class="prose" data-reveal>These are counted items from our 2026 inventory, maintained by our team and sent out with our crew.</p>
            <div data-reveal>${button('/equipment/', 'Full equipment list', { variant: 'link' })}</div>
        </div>
        <div class="split__aside" data-reveal>
            <dl class="kit-list">${s.kit.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl>
        </div>
    </div>
</section>` : ''}

${work.length || items.length ? `<section class="section ${s.kit.length ? 'section--dark' : ''}" id="work">
    <div class="container">
        <div class="section-head"><div>${eyebrow('The work')}<h2 class="display" data-split>${esc(s.name)} in action.</h2></div>
        ${button(`/gallery/#${s.slug}`, 'Full gallery', { variant: 'ghost' })}</div>
        ${work.length ? `<div class="projects projects--compact">${work.map(projectCard).join('')}</div>` : ''}
        ${items.length ? galleryGrid(items, s.slug) : ''}
    </div>
</section>` : ''}

${s.faqs.length ? `<section class="section">
    <div class="container split">
        <div class="split__main split__main--narrow">${eyebrow('Questions')}<h2 class="display display--md" data-split>Good to know.</h2></div>
        <div class="faqs split__aside--wide">${s.faqs.map(([q, a]) => `<details class="faq" data-reveal><summary>${esc(q)}<span>${icon('plus')}</span></summary><p>${esc(a)}</p></details>`).join('')}</div>
    </div>
</section>` : ''}

<section class="section section--dark section--flush">
    <div class="container section-head"><div>${eyebrow('More services')}<h2 class="display display--md" data-split>Complete the production.</h2></div></div>
    <div class="strip strip--cards" data-drag>${others.map((o) => `<a href="/services/${o.slug}/" class="strip__card">${img(o.hero, '', { sizes: '320px' })}<span><small>${pad(services.indexOf(o) + 1)}</small>${esc(o.name)}</span></a>`).join('')}</div>
</section>`;
    return layout({ path: `/services/${s.slug}/`, title: s.name, description: `${s.name} from ${site.name}: ${s.short} ${s.tagline} Available nationwide in Nigeria.`, image: s.hero, body, schema: [serviceSchema, ...faq] });
}

function galleryPage() {
    const groups = services.map((s) => ({ s, items: gallery.filter((g) => g.service === s.slug) })).filter((g) => g.items.length);
    const body = `${pageHero({ eyebrowText: 'Gallery', title: 'The work, by service.', lead: 'Our own shows first, then the looks we build — grouped by service so you can find exactly what you need.', image: 'truss-beams-violet', crumbs: [[null, 'Gallery']] })}

<section class="section">
    <div class="container">
        <div class="section-head"><div>${eyebrow('Recent work')}<h2 class="display" data-split>See our work in action.</h2></div></div>
        <div class="projects">${projects.map(projectCard).join('')}</div>
    </div>
</section>

<div class="filters" data-filters>
    <div class="container filters__inner" role="tablist" aria-label="Filter the gallery by service">
        <button type="button" role="tab" aria-selected="true" data-filter="all">All <small>${gallery.length}</small></button>
        ${groups.map(({ s, items }) => `<button type="button" role="tab" aria-selected="false" data-filter="${s.slug}">${esc(s.name)} <small>${items.length}</small></button>`).join('')}
    </div>
</div>

<div class="gallery-groups">
${groups.map(({ s, items }) => `<section class="section gallery-group" id="${s.slug}" data-group="${s.slug}">
    <div class="container">
        <div class="section-head section-head--row">
            <div><p class="eyebrow">${pad(services.indexOf(s) + 1)} — ${items.length} photo${items.length === 1 ? '' : 's'}</p><h2 class="display display--md">${esc(s.name)}</h2></div>
            ${button(`/services/${s.slug}/`, 'About this service', { variant: 'link' })}
        </div>
        ${galleryGrid(items, 'gallery')}
    </div>
</section>`).join('\n')}
</div>`;
    return layout({ path: '/gallery/', title: 'Gallery', description: `Photos and videos of ${site.name} stages, truss, LED screens, lighting and sound, grouped by service.`, image: 'truss-beams-violet', body });
}

function equipmentPage() {
    const show = { slug: 'lighting-sound-show', icon: 'eq-moving', tagline: 'Lighting, sound & effects', name: 'Lighting, Sound & Show', service: 'event-lighting', image: 'truss-red-beams',
        text: 'Beyond our stages, truss and screens, we supply the lighting, sound and show equipment your event needs, with operators to run it.',
        specs: ['Supplied with operators', 'Designed for your venue'], items: departments.map((d) => ({ name: d.name, icon: d.icon, text: d.text })) };
    const groups = [...equipment.map((g) => ({ ...g, items: g.items.map((t) => ({ name: t, icon: g.icon })) })), show];
    const steps = [
        { icon: 'calendar', title: 'Share your dates', text: 'Tell us the event, venue and the equipment you have in mind, or send your stage plan.' },
        { icon: 'badge-check', title: 'We confirm the kit', text: 'We check availability for your dates and send a clear rental quotation.' },
        { icon: 'truck', title: 'Delivered & installed', text: 'Our crew delivers, builds and tests everything before your guests arrive.' },
        { icon: 'sliders', title: 'Run & cleared', text: 'Operators run the show, then we dismantle and clear the venue.' },
    ];
    const why = [
        { icon: 'wrench', title: 'Maintained in-house', text: 'Checked before it leaves and when it comes back.' },
        { icon: 'team', title: 'Crew included', text: 'Installed and operated by people who know the kit.' },
        { icon: 'truck', title: 'Delivered to you', text: 'Transport, setup and dismantling in one booking.' },
        { icon: 'map', title: 'Nationwide', text: 'Our equipment travels to events in every state.' },
    ];

    const body = `${pageHero({ eyebrowText: 'Equipment', title: 'Rent the kit. Get the crew.', lead: 'Our own stages, truss, roof systems, LED screens, rigging, lighting and sound — delivered, installed and operated by the people who maintain it.', image: 'stage-deck-warm', crumbs: [[null, 'Equipment']], actions: button('#catalogue', 'Browse the catalogue', { ico: 'arrow-down' }) + button(requestUrl(), 'Request a quote', { variant: 'ghost' }) })}

<section class="section catalogue" id="catalogue">
    <div class="container">
        <div class="section-head"><div>${eyebrow('The catalogue')}<h2 class="display" data-split>Choose a category.</h2></div>
        <p class="section-head__aside" data-reveal>Everything here is our own, maintained in-house and sent out with our crew. Tell us your dates and we will confirm what is available.</p></div>
        <div class="catalogue__layout" data-tabs>
            <div class="catalogue__tabs" role="tablist" aria-label="Equipment categories" aria-orientation="vertical">
                ${groups.map((g, i) => `<button type="button" role="tab" id="tab-${g.slug}" aria-controls="${g.slug}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}" class="cat-tab">
                    <span class="cat-tab__icon">${icon(g.icon)}</span>
                    <span class="cat-tab__text"><strong>${esc(g.name)}</strong><small>${esc(g.tagline)}</small></span>
                    <span class="cat-tab__arrow">${icon('arrow-right')}</span>
                </button>`).join('')}
            </div>
            <div class="catalogue__panels">
                ${groups.map((g, i) => `<article class="cat-panel${i === 0 ? ' is-active' : ''}" role="tabpanel" id="${g.slug}" aria-labelledby="tab-${g.slug}" tabindex="0">
                    <div class="cat-panel__hero">
                        ${img(g.image, `${g.name}`, { sizes: '(min-width: 1024px) 60vw, 100vw' })}
                        <div class="cat-panel__overlay">
                            <p class="eyebrow eyebrow--light">${pad(i + 1)} — ${esc(g.tagline)}</p>
                            <h3 class="display display--md">${esc(g.name)}</h3>
                            <ul class="cat-panel__specs">${g.specs.map((t) => `<li>${icon('check')}${esc(t)}</li>`).join('')}</ul>
                        </div>
                        <span class="cat-panel__badge">${icon(g.icon)}</span>
                    </div>
                    <p class="cat-panel__text">${esc(g.text)}</p>
                    <ul class="cat-items">${g.items.map((it, j) => `<li class="cat-item" style="--j:${j}">
                        <span class="cat-item__icon">${icon(it.icon)}</span>
                        <span class="cat-item__name">${esc(it.name)}</span>
                        ${it.text ? `<span class="cat-item__text">${esc(it.text)}</span>` : ''}
                    </li>`).join('')}</ul>
                    <div class="cat-panel__actions">
                        ${button(requestUrl(serviceBySlug[g.service].bookingSlug ?? g.service), 'Request a quote')}
                        ${button(`/services/${g.service}/`, `About ${serviceBySlug[g.service].name}`, { variant: 'link' })}
                    </div>
                </article>`).join('')}
            </div>
        </div>
    </div>
</section>

<section class="section section--dark rent-steps">
    <div class="beams beams--soft" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="container">
        <div class="section-head"><div>${eyebrow('How renting works')}<h2 class="display" data-split>From enquiry to encore.</h2></div>
        <p class="section-head__aside" data-reveal>One booking covers the equipment, transport, installation, operators and dismantling.</p></div>
        <ol class="rent-steps__list">${steps.map((st, i) => `<li class="rent-step" data-reveal style="--stagger:${i}">
            <span class="rent-step__icon">${icon(st.icon)}<b>${pad(i + 1)}</b></span>
            <h3>${esc(st.title)}</h3>
            <p>${esc(st.text)}</p>
        </li>`).join('')}</ol>
    </div>
</section>

<section class="section why-rent">
    <div class="container">
        <ul class="why-rent__grid">${why.map((w, i) => `<li data-reveal style="--stagger:${i}"><span class="why-rent__icon">${icon(w.icon)}</span><span><strong>${esc(w.title)}</strong><small>${esc(w.text)}</small></span></li>`).join('')}</ul>
        <div class="kit-cta" data-reveal>
            <div>
                <p class="eyebrow">Planning an event?</p>
                <h2 class="display display--md">Send us your stage plan or technical rider.</h2>
                <p>We will match it to our kit, confirm availability for your dates and send a clear rental quotation, with delivery, installation and crew included.</p>
            </div>
            <div class="btn-row">
                ${button(requestUrl(), 'Request a quotation')}
                ${button(waUrl('Hello Nebo Stage, I would like to rent equipment for an event.'), 'Ask on WhatsApp', { variant: 'ghost', ico: 'whatsapp', external: true })}
            </div>
        </div>
    </div>
</section>`;
    return layout({ path: '/equipment/', title: 'Equipment', description: `Equipment for rent from ${site.name}: P3.91 outdoor LED screens, NovaStar processors, 400 × 600 and 400 × 400 truss, roof systems, chain hoists, stage decks, lighting, sound and show equipment.`, image: 'stage-deck-warm', body });
}

function aboutPage() {
    const body = `${pageHero({ eyebrowText: 'About us', title: 'Reliable. Professional. Stunning.', lead: 'A nationwide event production and equipment rental company that turns ordinary spaces into unforgettable experiences.', image: 'mobile-stage-roof', crumbs: [[null, 'About']] })}

<section class="statement container">
    ${eyebrow('Our story')}
    <p class="statement__text" data-words>At Nebo Stage, we don’t just provide event equipment — we create powerful visual experiences that command attention and leave lasting impressions.</p>
</section>

<section class="section section--tight">
    <div class="container split">
        <div class="split__main">
            <p class="prose" data-reveal>With services available nationwide, we specialise in the rental and professional installation of stages, trusses and LED screens for events of all sizes — and the lighting, sound, video and livestreaming that bring them to life.</p>
            <p class="prose" data-reveal>Whether it’s a corporate function, concert, church programme, wedding or political campaign, our team ensures flawless execution from start to finish. With a strong commitment to quality, safety and precision, Nebo Stage has become a trusted partner for event planners, organisations and brands looking to deliver excellence.</p>
        </div>
        <div class="split__aside about-media" data-reveal>
            ${img('production-concert-dancers', 'Performers on a lit concert stage', { sizes: '(min-width: 1024px) 40vw, 100vw' })}
        </div>
    </div>
</section>

<section class="stats-band">
    <div class="container stats">${site.stats.map((s) => `<div class="stat" data-reveal><span class="stat__value" data-count="${s.value}" data-suffix="${esc(s.suffix)}">0${esc(s.suffix)}</span><span class="stat__label">${esc(s.label)}</span></div>`).join('')}</div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><div>${eyebrow('Our promise')}<h2 class="display" data-split>Every event deserves to stand out.</h2></div>
        <p class="section-head__aside" data-reveal>We are committed to delivering quality setups, seamless execution and unforgettable visual impact — every single time.</p></div>
        <div class="values">${promise.map((v, i) => `<div class="value" data-reveal style="--stagger:${i}"><span class="value__icon">${icon(v.icon)}</span><h3>${esc(v.title)}</h3><p>${esc(v.text)}</p></div>`).join('')}</div>
    </div>
</section>

<section class="section section--dark">
    <div class="container">
        <div class="section-head"><div>${eyebrow('How we work')}<h2 class="display" data-split>Fast. Reliable. Professional.</h2></div></div>
        <ol class="steps">${steps.map((s, i) => `<li class="step" data-reveal style="--stagger:${i}"><span class="step__num">${pad(i + 1)}</span><h3>${esc(s.title)}</h3><p>${esc(s.text)}</p></li>`).join('')}</ol>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><div>${eyebrow('Recent work')}<h2 class="display" data-split>See our work in action.</h2></div></div>
        <div class="projects">${projects.map(projectCard).join('')}</div>
    </div>
</section>`;
    return layout({ path: '/about/', title: 'About Us', description: `About ${site.name}: nationwide rental and professional installation of stages, trusses and LED screens, plus lighting, sound, video and livestreaming.`, body });
}

function contactPage() {
    const cards = [
        { ico: 'phone', label: 'Call us', value: site.phone, href: telUrl },
        { ico: 'whatsapp', label: 'WhatsApp', value: 'Chat with our team', href: waUrl(), external: true },
        { ico: 'mail', label: 'Email', value: site.email, href: `mailto:${site.email}` },
        { ico: 'map', label: 'Where we work', value: 'Nationwide — every state in Nigeria' },
    ];
    const body = `${pageHero({ eyebrowText: 'Contact', title: 'Let’s talk about your event.', lead: 'Tell us the date, the venue and what you need. We will come back with a tailored production and equipment rental quotation.', image: 'production-crowd', crumbs: [[null, 'Contact']] })}

<section class="section">
    <div class="container contact-grid">
        <div class="contact-cards">${cards.map((c, i) => `${c.href ? `<a href="${c.href}"${c.external ? ' target="_blank" rel="noopener"' : ''}` : '<div'} class="contact-card" data-reveal data-spotlight style="--stagger:${i}">
            <span class="contact-card__icon">${icon(c.ico)}</span><span><small>${c.label}</small><strong>${esc(c.value)}</strong></span>${c.href ? icon('arrow-up-right') : ''}
        ${c.href ? '</a>' : '</div>'}`).join('')}
            <div class="socials socials--row" data-reveal>${site.socials.map((s) => `<a href="${s.url}" target="_blank" rel="noopener">${icon(s.name.toLowerCase())}<span>${s.handle}</span></a>`).join('')}</div>
        </div>
        <div class="request-card" data-reveal>
            <div class="beams beams--soft" aria-hidden="true"><i></i><i></i></div>
            <p class="eyebrow eyebrow--light">Request a quotation</p>
            <h2 class="display display--md">Book online in a few minutes.</h2>
            <p>Our request form takes your event details, the services you need and any stage layout or technical plan you already have. You get a reference to track your request.</p>
            <div class="btn-row">
                ${button(requestUrl(), 'Start your request')}
                ${button(`${site.app}/track`, 'Track a request', { variant: 'ghost', ico: 'search' })}
            </div>
            <ul class="checks checks--light">
                <li>${icon('check')}A clear, tailored quotation</li>
                <li>${icon('check')}Delivery, installation and dismantling included</li>
                <li>${icon('check')}Crew and operators for the whole event</li>
            </ul>
        </div>
    </div>
</section>`;
    return layout({ path: '/contact/', title: 'Contact', description: `Contact ${site.name}: call ${site.phone}, email ${site.email} or request a quotation online. Available nationwide.`, image: 'production-crowd', body });
}

function notFound() {
    const body = `<section class="hero hero--small">
    <div class="beams" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="container hero__inner hero__inner--center">
        <p class="eyebrow eyebrow--light">Error 404</p>
        <h1 class="display display--hero">Lights out.</h1>
        <p class="lead">This page isn’t on the running order. Let’s get you back to the show.</p>
        <div class="btn-row btn-row--center">${button('/', 'Back to home')}${button('/services/', 'Our services', { variant: 'ghost' })}</div>
    </div>
</section>`;
    return layout({ path: '/404.html', title: 'Page not found', body });
}

// ---------------------------------------------------------------- build

rmSync(dist, { recursive: true, force: true });
mkdirSync(dist, { recursive: true });

cpSync(join(src, 'images'), join(dist, 'images'), { recursive: true });
cpSync(join(src, 'media'), join(dist, 'media'), { recursive: true });
cpSync(join(src, 'css'), join(dist, 'css'), { recursive: true });
cpSync(join(src, 'js'), join(dist, 'js'), { recursive: true });
cpSync(join(src, 'static'), dist, { recursive: true });

// Brand files come from the app so there is one copy of each; fonts are in src/static/fonts.
mkdirSync(join(dist, 'brand'), { recursive: true });
for (const f of ['nebo-stage-white.svg', 'nebo-stage.svg', 'nebo-stage-mark.svg', 'nebo-stage.png']) cpSync(join(repo, 'public/images/brand', f), join(dist, 'brand', f));
cpSync(join(repo, 'public/favicon.ico'), join(dist, 'favicon.ico'));
cpSync(join(repo, 'public/app-icons/apple-touch-icon.png'), join(dist, 'apple-touch-icon.png'));

assets = { css: hash(join(src, 'css/site.css')), js: hash(join(src, 'js/site.js')) };

const pages = {
    '/': home(),
    '/services/': servicesIndex(),
    ...Object.fromEntries(services.map((s, i) => [`/services/${s.slug}/`, servicePage(s, i)])),
    '/gallery/': galleryPage(),
    '/equipment/': equipmentPage(),
    '/about/': aboutPage(),
    '/contact/': contactPage(),
};

for (const [path, html] of Object.entries(pages)) {
    const dir = join(dist, path);
    mkdirSync(dir, { recursive: true });
    writeFileSync(join(dir, 'index.html'), html);
}
writeFileSync(join(dist, '404.html'), notFound());

const today = new Date().toISOString().slice(0, 10);
writeFileSync(join(dist, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${Object.keys(pages).map((p) => `  <url><loc>${site.url}${p}</loc><lastmod>${today}</lastmod></url>`).join('\n')}
</urlset>
`);
writeFileSync(join(dist, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${site.url}/sitemap.xml\n`);

console.log(`Built ${Object.keys(pages).length + 1} pages into ${dist}`);
