# Nate Hall Insurance website — project notes for Claude

Live site: **https://www.natehallinsurance.com** (hosted on **Hostinger**, files in `public_html`).
Plain HTML/CSS/vanilla JS + one PHP file. No framework, no build step. See `README.md` for deploy steps.

This repo was split out of an earlier working session (in the PlayPoolNation repo) where the site was
built and revised with the owner. Everything below reflects decisions made there. Follow them unless
the owner says otherwise.

## Who you're working with
- The owner is **Nate Hall**, an independent insurance agent in Hartford, SD 57033. Non-technical:
  explain in plain language, avoid jargon, give click-by-click steps for hPanel / Netlify / GitHub.
- Nate updates the live site himself by uploading a zip to Hostinger. After any change, build a zip of
  the site files (exclude `README.md`, `netlify.toml`, `CLAUDE.md`) with files at the zip root, and tell
  him: hPanel → File Manager → `public_html` → upload → Extract → replace files. If an old version shows,
  flush cache at hPanel → Performance → CDN; for Facebook previews use the Sharing Debugger → Scrape Again.

## Business facts (use only these; don't invent anything)
- Business: Nate Hall Insurance. Agent: Nate Hall, independent insurance agent.
- Location: Hartford, SD 57033. No public street address — city/state only.
- Phone: **605-321-5367** (`tel:+16053215367`). Email: **natehallinsurance@gmail.com**.
- Hours: Mon–Fri 8:30 AM–5:30 PM · Saturday by appointment · Sunday closed · evening appointments available.
- Licensed in **5 states: SD, ND, IA, NE, MN** (listed everywhere states appear, incl. JSON-LD + link preview image).
- Facebook: https://www.facebook.com/p/Nate-Hall-Insurance-61587780698267/
- Works with **multiple carriers**. Do **not** name or single out any carrier (Prudential was removed on purpose).
  Emphasize that he's independent and shops multiple companies.

## Products (this order everywhere: tiles, Coverage cards, form checkboxes)
1. Life Insurance (final expense is included here, not a main product)
2. Annuities & Retirement — owner wants this a bit more prominent: it has a "Planning for retirement?"
   callout under the tiles and a gold-bordered "Retirement planning" Coverage card. The callout button
   pre-checks that box on the form (`data-interest` in `js/main.js`).
3. Medicare
4. Health Insurance
5. Cancer & Critical Illness
6. Long-Term Care

Form checkbox values (must match `$allowed` in `contact.php`): `Life insurance`, `Annuities & retirement`,
`Medicare`, `Health insurance`, `Cancer & critical illness`, `Long-term care`.

## Voice and wording rules
- Written in Nate's first-person voice: warm, plain, personal, family-focused. No hard-sell language.
- He's been through hard seasons in life and knows how much the right protection matters.
- **Keep this line exactly** (the owner loves it): "If the right answer is to keep what you already have, I'll tell you that too."
- Meeting options, worded consistently: office box says **"In Person, Phone or Virtual"**; prose says
  **"in person, by phone, or virtually"**. The form has an optional In person / Phone / Virtual choice.
- Coverage and FAQ copy is general and educational, never promises about specific policies.
- Privacy policy uses "I/me", says the site is for adults 18+ ("I do not knowingly collect personal
  information from minors") — the owner didn't want "under 13" wording.
- Testimonials carousel exists but is hidden (`TESTIMONIALS_ENABLED = false` in `js/main.js`). Never add
  fake reviews — only real ones with the client's permission.
- Footer ends with a small gray designer credit under the copyright on all three pages:
  "Website by Justin Couch" (plain text, no link). Justin built the site; keep it.
- Footer disclaimer (keep): "Insurance products are offered through various carriers. Not all products are
  available in all states. This site is for informational purposes and is not a contract or offer of coverage."

## Contact form
- Posts to `contact.php`, which emails each request to natehallinsurance@gmail.com (From
  `no-reply@natehallinsurance.com`, Reply-To = visitor). Nothing is stored. Honeypot field `bot-field`,
  5 submissions/IP/hour, input validation, header-injection protection, Central time zone.
- `js/main.js` shows "Thank you!" **only** when contact.php returns `{"ok":true}`; otherwise an error with the
  phone number. Don't weaken this — a silently lost lead is the worst failure.
- Checkbox name is `interests[]` (PHP needs the brackets to receive multiple values).
- Netlify Forms is no longer used (site moved off Netlify; old copy may still exist at
  dashing-gumption-107aca.netlify.app). `netlify.toml` is only relevant if it ever returns to Netlify.
- Test locally with `php -S localhost:8000`. To capture mail without sending, run PHP with
  `-d sendmail_path=<script that writes stdin to a file>`.

## Design
- Palette from the brushed-steel shield logo: ink `#14171C`, charcoal `#262B33`, steel `#5B6573`,
  silver `#C9CED6`, mist `#F3F4F6`, gold `#C9A24A` (gold text on light backgrounds `#8A6420`).
- Fonts: Cormorant Garamond (headings, Google Fonts) + system sans-serif body.
- Header logo text must stay light on hover/tap (`.brand:hover` override) — the global `a:hover` turns links dark.
- Mobile-first; sticky header and a sticky bottom Call / Free Quote bar on phones.
- Accessibility matters: axe-core has reported zero violations; tabs follow the WAI-ARIA pattern and the FAQ
  uses buttons with `aria-expanded`. Keep it that way.

## Images
- `assets/nate.jpg` + `assets/nate.webp`: 560×700 (4:5). When Nate sends a new photo, crop to 4:5 keeping
  head and shoulders (trim evenly from the long side), resize to 560×700, strip metadata, JPEG q≈82
  progressive and WebP q≈78. Update the `alt` text if the clothing changes (currently "black quarter-zip pullover"). The headshot `src`/`srcset`/preload use `?v=YYYYMMDD` too — bump it when the photo changes.
- `assets/og-image.jpg`: 1200×630 link-preview card — headshot in a silver frame on the left; on the right
  "NATE HALL INSURANCE", "Nate Hall", "Your local insurance agent", "Independent Insurance Agent",
  "Hartford, South Dakota", "Licensed in SD · ND · IA · NE · MN", and the six products. No contact info.
  It was rendered from an HTML template with Playwright (Chromium is at `/opt/pw-browsers/chromium` in
  cloud sessions); regenerate it whenever the photo, states, or products change.
- `assets/logo.png`, `logo-sm.*`, `favicon.png`, `apple-touch-icon.png` come from Nate's shield logo.
- `assets/client-meeting-{700,1100,1600}.{webp,jpg}`: photo of Nate going over paperwork with a client, shown
  full-width above the "Get to Know Me" heading (`.feature-photo`, lazy-loaded, responsive `srcset`).
  Its URLs carry `?v=YYYYMMDD` as well — bump it when the photo changes. Cropped to 16:9 (1600×900 max).
- `assets/kitchen-table-{700,967}.{webp,jpg}`: photo of Nate with a couple at a kitchen table, shown above the
  "Common Questions" heading inside the FAQ's narrow container (same `.feature-photo` style, versioned URLs).
  The original is only 975px wide, which is why it sits in the narrower FAQ column instead of full width.

## Cache-busting (important)
- `css/styles.css` and `js/main.js` are linked with a version query (`?v=YYYYMMDD`) in index.html,
  privacy.html, and thanks.html. **Bump it to today's date whenever styles.css or main.js changes.**
  Hostinger's CDN once kept serving an old styles.css after an upload (new photo showed unstyled),
  and the version change is what forces it and visitors' browsers to fetch the new file.

## Before finishing any change
- Check the page at 375px and 1280px (no horizontal scroll), run axe-core, and confirm tabs, FAQ, tile
  links, and the form still work. After structural HTML edits, confirm tags are balanced and there's no
  leftover/duplicated content (a past edit left a stray duplicate Cancer paragraph).

## Still open (optional)
- `TODO_BIO`: longer bio (background, family, hobbies) — commented template in the About tab.
- `TODO_NPN`: license/NPN number — commented out in the footers.
- Page `<title>` still reads "Life & Final Expense Insurance Agent in Hartford, SD"; it was offered to change
  it to reflect the new product lineup (e.g. "Life Insurance, Medicare & Retirement") — owner hasn't decided.
- Medicare card mentions Medicare Advantage and drug plans as general education; confirm with the owner if he
  doesn't handle those.
