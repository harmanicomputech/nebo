// Everything the website says lives here: company details, services, gallery
// and equipment. Edit this file, then run `node website/build.mjs`.

export const site = {
    url: 'https://nebostage.com.ng',
    app: 'https://app.nebostage.com.ng',
    name: 'Nebo Stage',
    tagline: 'Event production & equipment rental — nationwide',
    description: 'Nebo Stage is a nationwide event production and equipment rental company in Nigeria: stages, truss and roof systems, LED screens, lighting, sound, video and livestreaming, delivered, installed and run by one team.',
    email: 'info@nebostage.com.ng',
    phone: '+234 703 101 2083',
    whatsapp: '2347031012083',
    socials: [
        { name: 'Instagram', handle: '@nebostage', url: 'https://www.instagram.com/nebostage' },
        { name: 'Facebook', handle: 'Nebo Stage', url: 'https://www.facebook.com/profile.php?id=61592085886636' },
        { name: 'X', handle: '@nebostage', url: 'https://x.com/nebostage' },
        { name: 'TikTok', handle: '@nebostage', url: 'https://www.tiktok.com/@nebostage' },
    ],
    // From the previous website.
    stats: [
        { value: 356, suffix: '+', label: 'Happy clients' },
        { value: 45, suffix: '+', label: 'Equipment lines' },
        { value: 178, suffix: ' m', label: 'Of aluminium truss' },
        { value: 24, suffix: ' m²', label: 'Of P3.91 outdoor LED screen' },
    ],
};

/** Our own jobs, filmed on the night. Stills from them lead the gallery. */
export const projects = [
    {
        slug: 'comedy-ward',
        name: 'Comedy Ward',
        kind: 'Comedy show',
        video: 'comedy-ward',
        poster: 'comedy-ward-poster',
        services: ['led-screens-displays', 'event-lighting', 'trussing-rigging'],
        summary: 'A full banquet-hall comedy show: a wide LED screen wall for the show graphics and acts, moving-head beams and a row of PARs on truss, and stage washes for the comedians.',
    },
    {
        slug: 'zamar-7',
        name: 'Zamar 7.0',
        kind: 'Orchestra & choir concert',
        video: 'zamar-7',
        poster: 'zamar-poster',
        services: ['event-lighting', 'trussing-rigging', 'full-event-production'],
        summary: 'Lighting by Nebo Light for a full orchestra and choir: beams over the audience, colour washes across the players, light columns behind the stage and a circular truss rig, run live from the lighting desk.',
    },
];

/** Who we work for. */
export const eventTypes = [
    'Concerts & live shows', 'Political rallies & campaigns', 'Church programmes & crusades', 'Corporate events & conferences',
    'Weddings & celebrations', 'Theatre & school productions', 'TV & film', 'Trade shows & exhibitions',
    'Festivals & outdoor events', 'Award ceremonies', 'Product launches',
];

export const promise = [
    { icon: 'quality', title: 'Quality', text: 'Reliable, modern and well-maintained equipment, checked before it leaves the warehouse and when it comes back.' },
    { icon: 'safety', title: 'Safety', text: 'Secure installations you can trust: rigging sized for the load, pinned and clipped truss, and stages built on level, supported ground.' },
    { icon: 'team', title: 'One team', text: 'An experienced crew with attention to detail delivers, installs, runs and dismantles everything we supply.' },
    { icon: 'map', title: 'Nationwide', text: 'We travel with our own equipment and crew to events in every state of Nigeria.' },
];

export const steps = [
    { title: 'Tell us about your event', text: 'Send the date, venue and the stage, screens, lighting, sound and crew you need — or simply the kind of event you are planning.' },
    { title: 'Get a tailored quotation', text: 'Our production team reviews your request, visits the site where needed and sends a clear production and equipment rental quotation.' },
    { title: 'We build, run and clear', text: 'We deliver, install and test everything ahead of time, operate it through the event and dismantle without delays.' },
];

/**
 * Services. "Book this service" opens the app's request form with the service
 * ticked, using `bookingSlug` when the app's slug differs from `slug`.
 */
export const services = [
    {
        slug: 'stage-rigging',
        name: 'Stage & Rigging',
        // The booking app still lists this service under its original slug.
        bookingSlug: 'stage-staging',
        short: 'Stages, risers, decks and catwalks of any size, with roofed outdoor stages rigged on truss.',
        tagline: 'The platform every great moment stands on.',
        hero: 'mobile-stage-roof',
        intro: [
            'A stage is the first thing your audience sees and the last thing they forget. We build stages that are level, solid and safe under performers, choirs, bands and dignitaries, and that look clean from every seat.',
            'From a low platform for a conference speaker to a roofed outdoor stage for a rally or crusade, we design the size and height around your venue, your programme and your audience.',
        ],
        includes: [
            ['Modular stage decks', 'Aluminium-framed panels that build any shape and size, indoors or outdoors.'],
            ['Roofed outdoor stages', 'Truss towers and roof grid with a sloped run-off, so the show goes on in sun and rain.'],
            ['Risers & catwalks', 'Band and choir risers, thrusts and runways that bring performers to the crowd.'],
            ['Access steps & finishing', 'Staircases, edge fittings and a tidy, safe finish for performers and guests.'],
        ],
        kit: [
            ['Stage deck panels', '50'],
            ['Stage staircases', '2'],
            ['Roof system tower sets', '6 tops, 6 sleeve blocks, 6 steel bases with extension feet'],
            ['Roof fittings', '24 hinges, 12 slant supports, 4 downhill slopes'],
            ['Aluminium single buckles', '24'],
        ],
        perfectFor: ['Concerts & live shows', 'Political rallies & campaigns', 'Church programmes & crusades', 'Theatre & school productions', 'Weddings & celebrations'],
        faqs: [
            ['How big can the stage be?', 'Stages are modular, so the size follows your venue and programme. Tell us the space you have and the number of people on stage, and we will propose the layout.'],
            ['Can you build on grass or uneven ground?', 'Yes. We level the deck on adjustable supports and check the ground before building. Our team will tell you if a site needs extra preparation.'],
            ['Do you provide a roof for outdoor events?', 'Yes. Our roof system is built on truss towers with chain hoists and has a downhill slope for rain run-off.'],
        ],
    },
    {
        slug: 'trussing-rigging',
        name: 'Trussing & Rigging',
        short: 'Ground support, flown truss, roof grids and rigging for lights and screens.',
        tagline: 'Strong lines. Safe loads. Clean looks.',
        hero: 'truss-moving-heads',
        intro: [
            'Truss is the skeleton of your production: it carries the lights, the LED screens, the speakers and the roof. We plan every structure around the load it will hold and build it with the right connectors, pins and clips — every time.',
            'Our 400 × 600 and 400 × 400 aluminium spigot truss builds goalposts, totems, ground-supported towers, roof grids and backdrops, lifted on galvanised chain hoists.',
        ],
        includes: [
            ['Ground support towers', 'Free-standing towers and goalposts for lighting, screens and banners.'],
            ['Roof grids', 'Truss roof systems lifted on chain hoists for outdoor stages.'],
            ['Flown truss & hoists', 'Truss and screens hung from roof grids or venue structure on chain hoists and rated straps.'],
            ['Backdrops & set structure', 'Truss frames for branding, LED walls, scenic pieces and photo walls.'],
        ],
        kit: [
            ['400 × 600 spigot truss', '20 × 3 m, 2 × 2 m, 2 × 1 m (66 m)'],
            ['400 × 400 spigot truss', '20 × 3 m, 21 × 2 m, 2 × 1.5 m, 7 × 1 m (112 m)'],
            ['Galvanised manual chain hoists', '6'],
            ['Lifting straps, 2 t × 3 m', '6'],
            ['Egg-shaped spigot connectors', '350'],
            ['Pins with R-clips', '900'],
        ],
        perfectFor: ['Concerts & live shows', 'Trade shows & exhibitions', 'Corporate events & conferences', 'Festivals & outdoor events'],
        faqs: [
            ['Can truss hold an LED screen?', 'Yes. LED screens are hung from truss on hanging hooks or stacked on ground support. We size the truss and hoists for the screen weight.'],
            ['Can you rig inside our venue?', 'Where the venue allows it, we hang from the building structure. Otherwise we use ground-supported towers that need no fixing points.'],
        ],
    },
    {
        slug: 'barricades',
        name: 'Barricades',
        short: 'Front-of-stage and crowd-control barriers.',
        tagline: 'Keep the crowd close — and the stage safe.',
        hero: 'production-crowd',
        intro: [
            'Big crowds need clear lines. Barricades keep a safe gap between the audience and the stage, create a working pit for security, photographers and crew, and protect cables, equipment and VIP areas.',
            'We plan barrier lines with your security team as part of the stage layout, deliver and install them before doors open and clear them after the event.',
        ],
        includes: [
            ['Front-of-stage barriers', 'A secure line between the audience and the stage, with a working pit behind it.'],
            ['Crowd-control lines', 'Queues, entrances, walkways and separation between audience areas.'],
            ['VIP & backstage zoning', 'Barriers that keep restricted areas, equipment and generators clear.'],
            ['Front-of-house protection', 'A protected position for the sound and lighting control desks.'],
        ],
        kit: [],
        perfectFor: ['Concerts & live shows', 'Political rallies & campaigns', 'Festivals & outdoor events', 'Church programmes & crusades'],
        faqs: [
            ['Do I need barricades?', 'For standing crowds in front of a stage, yes. We will recommend barrier lines based on your expected crowd and the stage layout.'],
        ],
    },
    {
        slug: 'event-lighting',
        name: 'Event Lighting',
        short: 'Moving lights, washes, followspots and lighting design.',
        tagline: 'Light that sets the mood and steers every eye.',
        hero: 'truss-red-beams',
        intro: [
            'Lighting decides how your event feels and how it looks on camera. We design and run lighting that makes performers pop, keeps faces clear for photos and livestreams, and turns a hall or open field into a show.',
            'Our lighting team plans fixtures, positions and cues around your programme, rigs them on truss and operates them live from the console.',
        ],
        includes: [
            ['Moving lights', 'Beams, spots and washes that move, change colour and build energy.'],
            ['Conventionals & LED washes', 'Clean front light for faces, stage washes and audience light.'],
            ['Followspots', 'Keep the spotlight on the speaker, the couple or the headline act.'],
            ['Consoles & operators', 'Programmed cues and a lighting operator for the whole show.'],
            ['Special effects', 'Haze, lasers and effects where the venue and safety allow.'],
            ['Power distribution', 'Dimmers, distro and cabling planned for the load.'],
        ],
        kit: [],
        perfectFor: ['Concerts & live shows', 'Weddings & celebrations', 'Award ceremonies', 'Church programmes & crusades', 'TV & film'],
        faqs: [
            ['Can you light a venue for photos and livestreaming?', 'Yes. We balance stage light so faces look natural on camera as well as in the room.'],
            ['Do you provide a lighting operator?', 'Yes. Every lighting package comes with crew to rig it and an operator to run it.'],
        ],
    },
    {
        slug: 'led-screens-displays',
        name: 'LED Screens & Displays',
        short: 'Indoor and outdoor LED walls and displays.',
        tagline: 'Bright, sharp screens that the back row can read.',
        hero: 'led-giant-screen',
        intro: [
            'LED screens put your speakers, performers, sponsors and content in front of everyone, from the front row to the back of the field. Our P3.91 outdoor panels stay bright in daylight and sharp up close.',
            'We build one large wall or split the panels into side screens, hang them from truss or stack them on ground support, and run them through NovaStar VX600 Pro processors with an operator for your videos, slides and live camera feeds.',
        ],
        includes: [
            ['Main & side screens', 'One centre wall or a pair of IMAG side screens — or both.'],
            ['Outdoor-rated panels', 'P3.91 outdoor LED panels that stay bright in daylight.'],
            ['Flown or ground-stacked', 'Hung from truss on hanging hooks or built on ground support.'],
            ['Processing & playback', 'Video processors, switching and an operator for slides, videos and live feeds.'],
        ],
        kit: [
            ['P3.91 outdoor LED panels, 0.5 × 0.5 m', '48 (12 m²)'],
            ['P3.91 outdoor LED panels, 0.5 × 1 m', '24 (12 m²)'],
            ['NovaStar VX600 Pro video processors', '3'],
            ['LED screen hanging hooks', '18'],
        ],
        perfectFor: ['Political rallies & campaigns', 'Concerts & live shows', 'Corporate events & conferences', 'Church programmes & crusades', 'Weddings & celebrations'],
        faqs: [
            ['How big a screen can you build?', 'Our panels make up to 24 m² of screen — for example a 6 m × 4 m wall, or two 4 m × 3 m side screens.'],
            ['Will the screen work outdoors in daylight?', 'Yes. Our P3.91 panels are outdoor-rated and bright enough for daytime events.'],
            ['Can you show our cameras live on the screen?', 'Yes. We take live camera feeds from our video team or yours and show them alongside slides and videos.'],
        ],
    },
    {
        slug: 'sound-audio-production',
        name: 'Sound & Audio Production',
        short: 'PA systems, monitoring, mixing and engineers.',
        tagline: 'Every word heard. Every beat felt.',
        hero: 'sound-dj-led-wall',
        intro: [
            'If the audience can’t hear it, it didn’t happen. We size the sound system to your venue and crowd so speeches are clear and music hits hard, without feedback or dead spots.',
            'Our engineers set up, tune and mix the system live, with stage monitors for performers and microphones for every speaker, choir and band.',
        ],
        includes: [
            ['PA systems', 'Speaker systems sized for halls, churches, fields and stadium crowds.'],
            ['Mixing consoles', 'Digital mixing at front of house with a dedicated engineer.'],
            ['Stage monitoring', 'Monitor wedges so performers, choirs and bands hear themselves.'],
            ['Microphones', 'Wireless handhelds, lapels, headsets and instrument mics.'],
        ],
        kit: [],
        perfectFor: ['Concerts & live shows', 'Church programmes & crusades', 'Corporate events & conferences', 'Weddings & celebrations', 'Political rallies & campaigns'],
        faqs: [
            ['Do you provide a sound engineer?', 'Yes. Every sound package comes with an engineer who sets up, tunes and mixes the event.'],
            ['Can our band use your system?', 'Yes. Send us the band’s technical rider and we will plan the inputs and monitors.'],
        ],
    },
    {
        slug: 'photography-videography',
        name: 'Photography & Videography',
        short: 'Event coverage, multi-camera video and editing.',
        tagline: 'Your event, captured the way it felt.',
        hero: 'video-tv-studio',
        intro: [
            'We capture your event in photos and multi-camera video — the speeches, the performances, the crowd and the details — and deliver edited coverage you can share and keep.',
            'Because we also run the stage, lighting and screens, our camera team is part of the show: we can put live camera shots on the LED screens while we record.',
        ],
        includes: [
            ['Event photography', 'Edited photos of the people, moments and details that matter.'],
            ['Multi-camera video', 'Full coverage of the programme from several angles.'],
            ['Highlight edits', 'Short highlight films for social media and your archive.'],
            ['Live camera to screen', 'Camera feeds to the LED screens so the back row sees every face.'],
        ],
        kit: [],
        perfectFor: ['Weddings & celebrations', 'Corporate events & conferences', 'Concerts & live shows', 'Award ceremonies', 'Product launches'],
        faqs: [
            ['How do we receive the photos and videos?', 'Edited photos and videos are delivered digitally after the event. Your quotation will state what is included and when.'],
        ],
    },
    {
        slug: 'livestreaming',
        name: 'Livestreaming',
        short: 'Live broadcast to any platform.',
        tagline: 'Bring everyone in — wherever they are.',
        hero: 'stream-green-screen-hall',
        intro: [
            'Reach the people who can’t be in the room. We stream your event live to YouTube, Facebook, Instagram, Zoom or your own platform, with multiple cameras, clean sound from the desk and your branding on screen.',
            'We record the programme at the same time, so you also have the full event to publish afterwards.',
        ],
        includes: [
            ['Multi-camera switching', 'Live cuts between cameras, slides and videos.'],
            ['Sound from the desk', 'A clean audio feed straight from the mixing console.'],
            ['Graphics & lower thirds', 'Your logo, speaker names and messages on screen.'],
            ['Streaming & recording', 'Broadcast to one or several platforms, recorded in full.'],
        ],
        kit: [],
        perfectFor: ['Church programmes & crusades', 'Corporate events & conferences', 'Political rallies & campaigns', 'Weddings & celebrations', 'Award ceremonies'],
        faqs: [
            ['What do we need at the venue?', 'A reliable internet connection is the key. Tell us about the venue and our team will advise on the connection the stream needs.'],
            ['Can you stream to more than one platform?', 'Yes. We can stream to several platforms at once.'],
        ],
    },
    {
        slug: 'full-event-production',
        name: 'Full Event Production',
        short: 'End-to-end technical production, planned and run by one team.',
        tagline: 'One team. Every technical layer. Show-ready.',
        hero: 'production-concert-dancers',
        intro: [
            'Hand us the whole technical side of your event. We plan and deliver the stage, truss and roof, LED screens, lighting, sound, video, livestreaming and barricades as one production — with one point of contact from the first call to the last truck out.',
            'One team means the screen fits the truss, the lights suit the cameras and the sound is checked before your guests arrive. You focus on your programme and your guests; we make it look and sound right.',
        ],
        includes: [
            ['Technical planning', 'Stage layout, site visit, power and a schedule from load-in to breakdown.'],
            ['Every department', 'Staging, truss, screens, lighting, sound, video and streaming from one supplier.'],
            ['Crew & operators', 'Riggers, technicians, engineers and operators for the whole event.'],
            ['Logistics', 'Transport, delivery, installation and dismantling, anywhere in Nigeria.'],
        ],
        kit: [],
        perfectFor: ['Concerts & live shows', 'Political rallies & campaigns', 'Festivals & outdoor events', 'Church programmes & crusades', 'Award ceremonies', 'Product launches'],
        faqs: [
            ['Can you work from our stage design?', 'Yes. Upload your stage layout or technical plan with your request and we will build around it, or we will design one with you.'],
            ['Do you travel outside Lagos and Abuja?', 'Yes. We are nationwide: our equipment and crew travel to events in every state.'],
        ],
    },
];

/**
 * Gallery photos, grouped by service. `service` is the service slug; the first
 * group a photo appears in is its home on the gallery page.
 */
export const gallery = [
    { image: 'comedy-ward-led-screen', service: 'led-screens-displays', project: 'comedy-ward', alt: 'Comedy Ward: LED screen wall behind the stage with the crowd in front' },
    { image: 'comedy-ward-screen-cast', service: 'led-screens-displays', project: 'comedy-ward', alt: 'Comedy Ward: the cast on the LED screen under moving-head beams' },
    { image: 'comedy-ward-stage-beams', service: 'event-lighting', project: 'comedy-ward', alt: 'Comedy Ward: moving-head beams and red PARs over the stage and screen' },
    { image: 'comedy-ward-hall-wash', service: 'event-lighting', project: 'comedy-ward', alt: 'Comedy Ward: banquet hall in magenta light with beams over the stage' },
    { image: 'zamar-orchestra-conductor', service: 'event-lighting', project: 'zamar-7', alt: 'Zamar 7.0: conductor and orchestra under beams and light columns' },
    { image: 'zamar-violins-colour', service: 'event-lighting', project: 'zamar-7', alt: 'Zamar 7.0: violinists lit in red, blue and green from a circular truss' },
    { image: 'zamar-violin-beams', service: 'event-lighting', project: 'zamar-7', alt: 'Zamar 7.0: white beams sweeping over the violin section' },
    { image: 'zamar-beams-crowd', service: 'full-event-production', project: 'zamar-7', alt: 'Zamar 7.0: the audience in front of a stage full of beams' },
    { image: 'zamar-lighting-desk', service: 'event-lighting', project: 'zamar-7', alt: 'Zamar 7.0: the lighting desk running the show' },
    { image: 'mobile-stage-roof', service: 'stage-rigging', alt: 'Outdoor stage with truss roof, side towers and line-array speakers' },
    { image: 'hall-stage-purple', service: 'stage-rigging', alt: 'Hall stage washed in purple and pink light' },
    { image: 'choir-on-stage', service: 'stage-rigging', alt: 'Choir and cast on a stage in front of a painted backdrop' },
    { image: 'stage-deck-warm', service: 'stage-rigging', alt: 'Stage deck lit with warm white spotlights and a lighting desk' },

    { image: 'truss-moving-heads', service: 'trussing-rigging', alt: 'Moving head lights rigged on black truss' },
    { image: 'truss-beams-violet', service: 'trussing-rigging', alt: 'Truss towers with PAR cans and speakers in violet light' },
    { image: 'truss-red-beams', service: 'trussing-rigging', alt: 'Red light beams cutting through haze under a truss grid' },
    { image: 'truss-blue-beams', service: 'trussing-rigging', alt: 'Blue and violet beams from lights on a truss grid' },
    { image: 'truss-pars-speakers', service: 'trussing-rigging', alt: 'Moving lights hanging from truss above a stage' },

    { image: 'lighting-green-tubes', service: 'event-lighting', alt: 'Green beams and LED tubes over a stage' },
    { image: 'lighting-red-bars', service: 'event-lighting', alt: 'Red LED light bars hanging in a dark venue' },
    { image: 'lighting-colour-path', service: 'event-lighting', alt: 'Walkway lit with colourful gobo projections at night' },
    { image: 'lighting-light-stairs', service: 'event-lighting', alt: 'Illuminated steps leading to a wall of lights' },
    { image: 'lighting-fresnel-blue', service: 'event-lighting', alt: 'Fresnel stage light with barn doors' },

    { image: 'led-giant-screen', service: 'led-screens-displays', alt: 'Large screen in front of a seated audience' },
    { image: 'led-banquet-screens', service: 'led-screens-displays', alt: 'Banquet hall with screens on both sides of the stage' },
    { image: 'led-screen-corridor', service: 'led-screens-displays', alt: 'Corridor lined with display screens' },
    { image: 'led-touch-kiosk', service: 'led-screens-displays', alt: 'Visitor using an interactive display screen' },

    { image: 'sound-dj-led-wall', service: 'sound-audio-production', alt: 'DJ booth with speakers, truss lighting and an LED wall' },
    { image: 'sound-consoles', service: 'sound-audio-production', alt: 'Mixing and lighting consoles in front of a lit stage' },

    { image: 'video-tv-studio', service: 'photography-videography', alt: 'Camera crew filming a studio production' },
    { image: 'video-camera-operator', service: 'photography-videography', alt: 'Camera operator filming a studio interview' },
    { image: 'video-studio-lights', service: 'photography-videography', alt: 'Studio lights set up for a video shoot' },
    { image: 'video-fresnel-green', service: 'photography-videography', alt: 'Film light in front of a green screen' },

    { image: 'stream-green-screen-hall', service: 'livestreaming', alt: 'Production road case and green-screen monitor in a hall' },

    { image: 'production-concert-dancers', service: 'full-event-production', alt: 'Performers dancing on a lit concert stage in front of the crowd' },
    { image: 'production-crowd', service: 'full-event-production', alt: 'Crowd with hands raised in front of a lit stage' },
    { image: 'production-banquet', service: 'full-event-production', alt: 'Banquet hall dressed for a corporate dinner with stage screens' },
];

/** Equipment we rent, by department. Quantities are our own inventory (2026). */
export const equipment = [
    {
        name: 'LED Screens', service: 'led-screens-displays',
        items: [
            ['P3.91 outdoor LED panel, 0.5 × 0.5 m', '48'],
            ['P3.91 outdoor LED panel, 0.5 × 1 m', '24'],
            ['NovaStar VX600 Pro video processor', '3'],
            ['LED screen hanging hook', '18'],
        ],
    },
    {
        name: 'Trussing', service: 'trussing-rigging',
        items: [
            ['Truss 400 × 600, 3 m', '20'], ['Truss 400 × 600, 2 m', '2'], ['Truss 400 × 600, 1 m', '2'],
            ['Truss 400 × 400, 3 m', '20'], ['Truss 400 × 400, 2 m', '21'], ['Truss 400 × 400, 1.5 m', '2'], ['Truss 400 × 400, 1 m', '7'],
            ['Egg-shaped connector', '350'], ['Pin with R-clip', '900'], ['Fasteners and joints', '8'],
        ],
    },
    {
        name: 'Roof System', service: 'stage-rigging',
        items: [
            ['Top section', '6'], ['Sleeve block', '6'], ['Hinge', '24'], ['Steel pipe base with extension feet', '6'],
            ['Aluminium slant support 50 × 1800', '12'], ['Top multi-directional adapter', '2'], ['Customised downhill slope', '4'],
        ],
    },
    {
        name: 'Rigging', service: 'trussing-rigging',
        items: [['Manual chain hoist (galvanised)', '6'], ['Lifting strap 2 t × 3 m', '6']],
    },
    {
        name: 'Staging', service: 'stage-rigging',
        items: [['Stage panel', '50'], ['Staircase', '2'], ['Aluminium single buckle', '24'], ['Single rack 300 mm wide', '1']],
    },
];

/** Departments we supply beyond the counted inventory above (from our equipment list). */
export const departments = [
    'Moving lights', 'Conventionals & LEDs', 'Followspots', 'Consoles', 'Special FX', 'Dimmers',
    'Power distribution', 'Cables', 'Road cases', 'Communications',
];
