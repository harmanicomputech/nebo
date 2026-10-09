// Inline SVG icons (stroke icons in the style of Lucide, plus brand marks).
const stroke = (paths) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${paths}</svg>`;
const solid = (paths) => `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">${paths}</svg>`;

const icons = {
    'arrow-right': stroke('<path d="M5 12h14M13 6l6 6-6 6"/>'),
    'arrow-left': stroke('<path d="M19 12H5M11 18l-6-6 6-6"/>'),
    'arrow-down': stroke('<path d="M12 5v14M6 13l6 6 6-6"/>'),
    'arrow-up-right': stroke('<path d="M7 17 17 7M8 7h9v9"/>'),
    'arrow-down-right': stroke('<path d="M7 7l10 10M17 8v9H8"/>'),
    'chevron-down': stroke('<path d="m6 9 6 6 6-6"/>'),
    phone: stroke('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>'),
    mail: stroke('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'),
    map: stroke('<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'),
    search: stroke('<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>'),
    check: stroke('<path d="M20 6 9 17l-5-5"/>'),
    plus: stroke('<path d="M12 5v14M5 12h14"/>'),
    close: stroke('<path d="M18 6 6 18M6 6l12 12"/>'),
    expand: stroke('<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>'),
    play: solid('<path d="M8 5.1v13.8a1 1 0 0 0 1.5.9l10.8-6.9a1 1 0 0 0 0-1.7L9.5 4.2A1 1 0 0 0 8 5.1Z"/>'),
    quality: stroke('<circle cx="12" cy="8" r="6"/><path d="M15.5 13.2 17 22l-5-3-5 3 1.5-8.8"/>'),
    safety: stroke('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>'),
    team: stroke('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>'),
    whatsapp: solid('<path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5.3-.5c.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3ZM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8Zm8.4-18.2A11.8 11.8 0 0 0 1.9 17.9L.2 24l6.3-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6Z"/>'),
    instagram: stroke('<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6" fill="currentColor"/>'),
    facebook: solid('<path d="M14 8V6.3c0-.8.2-1.3 1.4-1.3H17V2.1C16.7 2 15.8 2 14.7 2 12.4 2 11 3.4 11 5.9V8H8.5v3H11v11h3V11h2.6l.4-3h-3Z"/>'),
    tiktok: solid('<path d="M16.6 2h-3.4v13.4a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .9.1V9.1a6.3 6.3 0 1 0 5.4 6.3V8.6a7.9 7.9 0 0 0 4.6 1.5V6.7a4.6 4.6 0 0 1-4.6-4.7Z"/>'),
    x: solid('<path d="M17.8 3h3.1l-6.8 7.7 8 10.3h-6.2l-4.9-6.3L5.4 21H2.3l7.3-8.3L2 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z"/>'),

    // Services
    'svc-stage-rigging': stroke('<path d="M2 14h20M4 14v6M20 14v6M12 14v6M3 10l9-6 9 6"/>'),
    'svc-trussing-rigging': stroke('<path d="M3 6h18M3 12h18M3 6l3 6 3-6 3 6 3-6 3 6 3-6M6 12v9M18 12v9"/>'),
    'svc-barricades': stroke('<rect x="3" y="7" width="18" height="8" rx="1"/><path d="M7 7v8M11 7v8M15 7v8M19 7v8M5 15v5M19 15v5"/>'),
    'svc-event-lighting': stroke('<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V18h6v-1.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2Z"/>'),
    'svc-led-screens-displays': stroke('<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4M7 8h.01M11 8h.01M15 8h.01M7 12h.01M11 12h.01M15 12h.01"/>'),
    'svc-sound-audio-production': stroke('<rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="14" r="4"/><circle cx="12" cy="6" r="1"/>'),
    'svc-photography-videography': stroke('<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>'),
    'svc-livestreaming': stroke('<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19.1 4.9a10 10 0 0 1 0 14.2M4.9 19.1a10 10 0 0 1 0-14.2"/>'),
    'svc-full-event-production': stroke('<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4l-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3Z"/>'),
};

export function icon(name) {
    if (!icons[name]) throw new Error(`Unknown icon: ${name}`);
    return icons[name];
}
