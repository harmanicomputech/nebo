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

    // Equipment page
    'eq-roof': stroke('<path d="M2 9 12 4l10 5"/><path d="M4 8v13M20 8v13M4 13h16M2 21h20"/><path d="M9 21v-5h6v5"/>'),
    'eq-hoist': stroke('<path d="M12 2v3"/><rect x="7" y="5" width="10" height="6" rx="2"/><path d="M10 11v3M14 11v3M12 14v4"/><path d="M9.5 18a2.5 2.5 0 1 0 5 0"/>'),
    calendar: stroke('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8 14h2M12 14h2M8 17h2"/>'),
    'badge-check': stroke('<path d="M12 2l2.4 1.8 3 .2.9 2.8 2.3 2-1 2.8.4 3-2.6 1.5-1.3 2.7-3-.4L12 22l-2.4-1.6-3 .4-1.3-2.7-2.6-1.5.4-3-1-2.8 2.3-2 .9-2.8 3-.2L12 2Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>'),
    truck: stroke('<path d="M2 6h11v10H2zM13 9h5l3 3v4h-8"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/>'),
    wrench: stroke('<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z"/>'),
    sliders: stroke('<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>'),

    // Event types
    'ev-concert': stroke('<rect x="9" y="2" width="6" height="11" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/>'),
    'ev-rally': stroke('<path d="M3 11v3a1 1 0 0 0 1 1h2l4 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M14 8.5a5 5 0 0 1 0 7M17 6a8.5 8.5 0 0 1 0 12"/>'),
    'ev-church': stroke('<path d="M12 2v4M10 4h4M6 22V11l6-4 6 4v11"/><path d="M3 22h18M10 22v-4a2 2 0 0 1 4 0v4M9.5 12.5h5"/>'),
    'ev-corporate': stroke('<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M2 13h20M12 12v2"/>'),
    'ev-theatre': stroke('<path d="M3 4h8v6a4 4 0 0 1-8 0V4Z"/><path d="M5.5 7.5h.01M8.5 7.5h.01M5.5 11a2 2 0 0 0 3 0"/><path d="M13 9h8v6a4 4 0 0 1-8 0V9Z"/><path d="M15.5 12.5h.01M18.5 12.5h.01M15.5 16.5a2 2 0 0 1 3 0"/>'),
    'ev-film': stroke('<rect x="2" y="8" width="20" height="13" rx="2"/><path d="M2 8l3-5h4l-3 5M9 8l3-5h4l-3 5M16 8l3-5h3"/>'),
    'ev-tradeshow': stroke('<path d="M3 21V9l9-6 9 6v12"/><path d="M3 21h18M7 21v-7h10v7M7 17h10"/>'),
    'ev-special': stroke('<path d="m12 2 2.2 4.6 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5L4.8 7.3l5-.7L12 2Z"/><path d="M5 21l2-3M19 21l-2-3M12 21v-3"/>'),

    // Equipment departments
    'eq-moving': stroke('<rect x="8" y="2" width="8" height="5" rx="1"/><path d="M6 7h12l-2 6H8L6 7Z"/><circle cx="12" cy="10" r="1.5"/><path d="M10 13l-4 9M14 13l4 9"/>'),
    'eq-par': stroke('<circle cx="12" cy="11" r="7"/><circle cx="12" cy="11" r="3"/><path d="M8 21h8M12 18v3"/>'),
    'eq-spot': stroke('<path d="M3 9l7-3v10l-7-3V9Z"/><path d="M10 8l11-4v14l-11-4"/>'),
    'eq-console': stroke('<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M6 10v5M10 9v6M14 11v4M18 9v6"/><path d="M5 12h2M9 11h2M13 13h2M17 10h2"/>'),
    'eq-fx': stroke('<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><circle cx="12" cy="12" r="2.5"/>'),
    'eq-dimmer': stroke('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8v8M12 8v8M16 8v8"/><path d="M6.5 13h3M10.5 10h3M14.5 14h3"/>'),
    'eq-power': stroke('<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>'),
    'eq-cable': stroke('<path d="M4 4v4a4 4 0 0 0 4 4h8a4 4 0 0 1 4 4v4"/><rect x="2" y="2" width="4" height="3" rx="1"/><rect x="18" y="19" width="4" height="3" rx="1"/>'),
    'eq-case': stroke('<rect x="2" y="7" width="20" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M2 12h20M7 12v3M17 12v3"/>'),
    'eq-comms': stroke('<path d="M3 14v-2a9 9 0 0 1 18 0v2"/><rect x="2" y="14" width="5" height="6" rx="2"/><rect x="17" y="14" width="5" height="6" rx="2"/><path d="M19 20a4 4 0 0 1-4 2h-2"/>'),
    'grid': stroke('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'),

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
