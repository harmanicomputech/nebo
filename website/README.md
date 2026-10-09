# Nebo Stage website

The public brand website for **nebostage.com.ng**. It is a static site (HTML, CSS and a small script), separate from the operations app at `app.nebostage.com.ng`. Booking happens in the app: every "Book now" button opens the app's request form, and each service page ticks its own service on that form.

## Pages

- Home
- Services, plus one page for each of the 9 services
- Gallery, grouped by service
- Equipment
- About
- Contact
- 404

## Change the words, photos or videos

Everything the site says is in `content.mjs`:

- **`site`**: phone, email, WhatsApp number, social links and headline stats.
- **`services`**: one entry per service: name, intro, what's included, kit from our inventory, "perfect for" and FAQs. Each `slug` should match the app's service slug so "Book this service" ticks the right box; where it differs, set `bookingSlug` to the app's slug.
- **`projects`**: our own filmed jobs (videos in `src/media`, posters in `src/images`).
- **`gallery`**: each photo and the service it belongs to. Photos with a `project` are from our own jobs; they are badged and shown first.
- **`equipment`**: the equipment page: each group's photo, description, key specs and item names (no quantities). `departments` are the other equipment types, with icons.

### Adding a photo

1. Save it into `src/images` as WebP, in two sizes: `name-800.webp` and `name-1600.webp`. If the photo is small, the 800 size alone is enough.

   ```
   convert photo.jpg -resize 800x800\> -quality 74 src/images/name-800.webp
   ```

2. Add the photo to `gallery` in `content.mjs`, with its `service` and an `alt` description.

### Adding a video

1. Save it into `src/media` as H.264 MP4. Use `-movflags +faststart` so it starts playing quickly.
2. Save a poster frame next to it in `src/images`.
3. Add an entry to `projects` in `content.mjs`.

## Build and publish

```
scripts/build-website.sh
```

This writes `website/dist` and creates `nebo-stage-website.zip` in the repository root.

To publish:

1. Back up the domain's `public_html`, which holds the old WordPress site.
2. Empty `public_html`.
3. Extract the zip into it. Show hidden files: the zip includes an `.htaccess`.

The `.htaccess` does the following:

- forces HTTPS and the bare domain;
- sends the old WordPress addresses to the new pages;
- serves the 404 page;
- sets the security headers and caching.

To preview locally, run `node website/build.mjs`, then serve `website/dist` with any static server, for example `python3 -m http.server -d website/dist 8080`.
