# Nate Hall Insurance website

A single-page site for Nate Hall Insurance in Hartford, SD. It's built with plain HTML, CSS, and vanilla JS. There's no framework and no build step.

```
natehallinsurance/
├── index.html        Main page
├── privacy.html      Privacy policy (linked from the footer)
├── thanks.html       Form confirmation page (used only if JavaScript is off)
├── 404.html          "Page not found" page (root-relative links so it works at any missing URL)
├── .htaccess         404 page, non-www → www redirect, security headers (Apache/LiteSpeed)
├── robots.txt        Allows all crawlers; points to the sitemap
├── sitemap.xml       Lists / and /privacy.html (add new public pages here)
├── favicon.ico       Generated from assets/favicon.png (16/32/48px)
├── contact.php       Emails each contact form submission (needs PHP, e.g. Hostinger)
├── css/styles.css
├── js/main.js        Tabs, FAQ accordion, testimonials carousel, form submit
├── assets/           logo.png, nate.jpg (+ .webp), og-image.jpg, favicons
├── netlify.toml      Only used if the site is ever hosted on Netlify again
├── CLAUDE.md         Project notes and decisions for Claude Code sessions
└── README.md
```

## Still optional

The site files contain no comments or placeholders (by design). Two optional additions are still open:

- **Longer bio** (background, family, hobbies): add a paragraph to the About tab in `index.html`.
- **NPN / license number**: add `<p>NPN: …</p>` under "Licensed in…" in the footer of each page.

> Link preview: `assets/og-image.jpg` (1200×630) is what shows when the site is shared on Facebook or by text. It's Nate's headshot with his name, title, and town, and it doesn't include contact info. Facebook caches previews, so after changing it, run the page URL through the [Sharing Debugger](https://developers.facebook.com/tools/debug/) and click **Scrape Again**.

**Already filled in:**
- Site address: `https://www.natehallinsurance.com`, used in the canonical links, Open Graph tags, and JSON-LD. To change it, search for `natehallinsurance.com`. (The original Netlify address was `https://dashing-gumption-107aca.netlify.app`.)
- Email: `natehallinsurance@gmail.com` (contact section, privacy page, JSON-LD).
- Hours: Mon–Fri 8:30 AM–5:30 PM, Saturday by appointment, Sunday closed, evening appointments available (office box). The JSON-LD lists only the Mon–Fri hours, because schema.org has no way to say "by appointment."
- Phone number `605-321-5367` (`tel:+16053215367`). It's used in the Call buttons, office box, contact section, form messages, privacy and thanks pages, and the JSON-LD. To change it, search for both formats.

## Testimonials

The carousel is built but hidden. To turn it on, open `js/main.js`, add real client quotes to the `TESTIMONIALS` array as `{ quote: 'Their words', name: 'Jane D.', place: 'Hartford, SD' }`, and set `TESTIMONIALS_ENABLED = true`. Only use real reviews, in the client's own words, with their permission.

## How the contact form works

The site is hosted on **Hostinger** at www.natehallinsurance.com. The form posts to `contact.php`, which Hostinger runs. It emails each request to **natehallinsurance@gmail.com** with the visitor's email as Reply-To, so clicking **Reply** in Gmail answers them directly. Nothing is saved on the server.

- **Settings** are at the top of `contact.php`: the inbox (`$TO`), the sender address (`$FROM`, currently `no-reply@natehallinsurance.com`), and a limit of 5 submissions per visitor per hour.
- **Spam protection:** a hidden "honeypot" field that bots fill in (those submissions are dropped silently) plus the hourly limit.
- **Safety:** names and emails are checked, line breaks are stripped from anything that goes into an email header, and only the six known product names are accepted.
- The page shows "Thank you!" **only** when `contact.php` confirms the email was sent. Otherwise the visitor sees an error with Nate's phone number, so a request is never silently lost.

**After every upload, send a test** from the live site and confirm it reaches the Gmail inbox. If it lands in spam, mark it **Not spam** once. If test emails never arrive:
1. In hPanel go to **Emails** and create the mailbox `no-reply@natehallinsurance.com` (or change `$FROM` in `contact.php` to an address that exists on the domain). Many servers reject mail "from" an address that doesn't exist.
2. Make sure the domain's SPF record includes Hostinger's mail servers (hPanel → **Emails → Email configuration** shows the recommended DNS records).

## Run it locally

```bash
php -S localhost:8000
# open http://localhost:8000
```

Using `php -S` (not a plain static server) lets the form run. Real emails only go out if your machine has a mail program set up; otherwise the form shows its "could not send" error, which is expected.

## Deploy on Hostinger

1. In hPanel, open **Websites → natehallinsurance.com → File Manager** and go into `public_html`.
2. Upload the site files (index.html, privacy.html, thanks.html, 404.html, contact.php, .htaccess, robots.txt, sitemap.xml, favicon.ico, and the `css`, `js`, and `assets` folders) so `index.html` sits directly in `public_html`. Replace the old files when asked.
   **`.htaccess` caution:** Hostinger may already have a `.htaccess` in `public_html` (hidden file; turn on "Show hidden files"). Download a copy before replacing it, and merge any rules it had (for example a Force-HTTPS rule) into the new one.
3. You don't need to upload `README.md`, `CLAUDE.md`, or `netlify.toml`.
4. Open the site and send a test from the form (see above).

## Hosting elsewhere

`contact.php` needs a host that runs PHP and can send mail, which Hostinger shared hosting does. Netlify and GitHub Pages don't run PHP; on those hosts you'd switch the form to Netlify Forms or a service like Formspree.

## Notes

- Palette (from the logo): ink `#14171C`, charcoal `#262B33`, steel `#5B6573`, silver `#C9CED6`, mist `#F3F4F6`, gold `#C9A24A` (gold text on light backgrounds uses `#8A6420`).
- Font: Cormorant Garamond (Google Fonts) for headings, system fonts for body text.
- Accessibility check: axe-core reported zero violations at 375px and 1280px. Tabs follow the WAI-ARIA pattern (Arrow keys, Home, End), and the FAQ uses buttons with `aria-expanded`.
- No carrier is named on the site. The copy emphasizes that Nate is independent and works with multiple carriers.
- Products (in this order everywhere: tiles, Coverage cards, form checkboxes): Life Insurance (final expense included), Annuities & Retirement, Medicare, Health Insurance, Cancer & Critical Illness, Long-Term Care. Annuities & Retirement also gets a callout under the tiles and a highlighted Coverage card. The callout's button pre-checks that box on the form.
- Licensed states: SD, ND, IA, NE, MN (office box, Service Area tab, FAQ, footers, JSON-LD, link preview image).
- Meeting options are worded consistently as "in person, by phone, or virtually". The form has an optional "How would you like to meet?" choice (In person / Phone / Virtual).
