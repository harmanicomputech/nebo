// Privacy policy for the Nebo Stage operations app and website. Used by the
// website's /privacy/ page and by the standalone file for the Play Store.
// Keep it in step with what the app actually stores (see the app's migrations).

export const policy = {
    updated: '7 November 2026',
    intro: 'This policy explains what personal information Nebo Stage collects through the Nebo Stage operations app (on Android and the web at app.nebostage.com.ng) and through this website, why we collect it, and the choices you have. Nebo Stage is a nationwide event production and equipment rental company in Nigeria.',
    sections: [
        {
            id: 'who', title: 'Who we are',
            html: `<p>Nebo Stage (“we”, “us”) is responsible for the personal information described here. You can reach us at <a href="mailto:info@nebostage.com.ng">info@nebostage.com.ng</a> or on <a href="tel:+2347031012083">+234 703 101 2083</a>.</p>
<p>The operations app is a work tool for Nebo Stage staff. Accounts are created by our administrators; the app has no public sign-up.</p>`,
        },
        {
            id: 'collect', title: 'Information we collect',
            html: `<h3>Staff using the app</h3>
<ul>
<li><strong>Account details:</strong> name, work email address, phone number, job title and role. Passwords are stored only as secure one-way hashes; we cannot read them.</li>
<li><strong>Work records you create:</strong> events, equipment and stock records, allocations, load lists, returns, logistics trips, maintenance jobs, notes, quotations and the photos or documents you upload.</li>
<li><strong>Photos:</strong> the app uses your camera only when you choose to take a photo, for example for an equipment condition report. Photos are uploaded to our server; nothing else on your device is accessed.</li>
<li><strong>Sign-in and security data:</strong> the IP address and browser or device type used to sign in, and an activity log of important actions (for example who changed an equipment record and when).</li>
<li><strong>Notifications:</strong> if you turn on notifications, we store the push subscription your device gives us so we can send you work notifications.</li>
</ul>
<h3>Customers and people who request a quotation</h3>
<ul>
<li><strong>Contact details:</strong> name, company, email address, phone number and address.</li>
<li><strong>Event details:</strong> event name, type, dates, venue, the services and equipment you need, budget range, and any files you attach, such as a stage design.</li>
<li><strong>Submission data:</strong> the IP address and browser type of a request, used to prevent spam and abuse.</li>
<li><strong>Quotation responses:</strong> when you accept or decline a quotation, the name you give and your answer.</li>
</ul>
<h3>Website visitors</h3>
<p>This website does not use cookies for tracking, analytics or advertising. Our web server keeps standard access logs (IP address, page requested, browser type) for security and to keep the site running.</p>`,
        },
        {
            id: 'use', title: 'How we use it',
            html: `<ul>
<li>To plan and deliver events: scheduling, equipment allocation, crew, transport and maintenance.</li>
<li>To respond to production requests and send, track and manage quotations.</li>
<li>To sign staff in, keep accounts secure and keep a record of important changes.</li>
<li>To send work notifications and emails, such as a new request, an assignment or a status change.</li>
<li>To meet our legal, accounting and tax obligations.</li>
</ul>
<p>We use this information because we need it to provide our services and run our business (our legitimate interests and contracts with customers), and where the law requires it.</p>`,
        },
        {
            id: 'not', title: 'What we don’t do',
            html: `<ul>
<li>We do not sell or rent personal information.</li>
<li>We do not show ads or use advertising or analytics trackers in the app.</li>
<li>We do not access your contacts, location, microphone or files other than those you choose to upload.</li>
</ul>`,
        },
        {
            id: 'share', title: 'Who we share it with',
            html: `<p>Only with service providers that help us run the app and website, and only as far as they need to:</p>
<ul>
<li><strong>Hosting:</strong> the server and database where the app and website run.</li>
<li><strong>Email delivery:</strong> the provider that sends our emails, such as request confirmations and password resets.</li>
<li><strong>Push notification services</strong> run by Google, Apple or Mozilla, which deliver notifications to your device. Notification contents are encrypted before they are sent.</li>
</ul>
<p>We may also disclose information if the law requires it, or to protect our rights, our staff or others.</p>`,
        },
        {
            id: 'keep', title: 'How long we keep it',
            html: `<p>We keep business records, such as events, quotations and equipment history, for as long as we need them to run the business and meet legal and accounting requirements. When a staff member leaves, an administrator deactivates their account. You can ask us to delete your personal information at any time (see below); some records may need to be kept where the law requires.</p>`,
        },
        {
            id: 'security', title: 'How we protect it',
            html: `<ul>
<li>All traffic to the app and website is encrypted with HTTPS.</li>
<li>Passwords are hashed, sessions are protected, and access inside the app is limited by role, so each person sees only what their job needs.</li>
<li>Important actions are recorded in an activity log, and uploaded files are checked before they are stored.</li>
</ul>`,
        },
        {
            id: 'rights', title: 'Your rights and deleting your data',
            html: `<p>Under the Nigeria Data Protection Act 2023 you can ask to see the personal information we hold about you, have it corrected, have it deleted, or object to how we use it.</p>
<p><strong>To delete your account or data,</strong> email <a href="mailto:info@nebostage.com.ng?subject=Data%20deletion%20request">info@nebostage.com.ng</a> with the subject “Data deletion request” from the email address we hold for you. We will confirm your request and complete it within 30 days. If you are unhappy with how we handle your information, you can also contact the Nigeria Data Protection Commission.</p>`,
        },
        {
            id: 'children', title: 'Children',
            html: `<p>The app is for Nebo Stage staff and business customers. It is not intended for children under 18, and we do not knowingly collect their information.</p>`,
        },
        {
            id: 'changes', title: 'Changes to this policy',
            html: `<p>If we change this policy, we will update the date at the top of this page. Significant changes will be announced in the app.</p>`,
        },
    ],
};
