# Maison Aurelle

A personal five-star hotel booking site, built as a learning project: PHP, MySQL and vanilla JavaScript, with a slash-wipe motion system in ink, charcoal, ivory and champagne gold. The hotel is fictional.

## Status

Built and working:
- Home, with the quick-booking bar and room carousel
- Rooms and Suites, with filters and an accordion
- Three-step booking with live totals and a booking ID
- Manage Reservation: look up, change dates (preview, then confirm), cancel, send an inquiry
- Experiences: spa and gym, with "Add to my stay"
- Contact: map, click-to-call and copy buttons, inquiry form, FAQ
- AI concierge: a floating chat on every page
- Booking extras: date picker with a nights dial, hold countdown, rolling totals, confirmation emails with calendar files
- Motion: hero slider with slash wipes, Golden Hour loader, layered parallax, room card flips, pinned treatment menu
- Staff sign-in and a front desk for managing reservations, inquiries, rooms and add-ons, with a phpMyAdmin button

Contact details in `includes/config.php` are placeholders.

## Try it with the sample booking

`database/seed.sql` includes a booking you can look up straight away on Manage Reservation:

- Booking ID: `MA-SAMPLE22`
- Email: `sample.guest@example.test`

It starts 30 days after the day you import, and comes with one inquiry. The seed is safe to import again: it leaves your rooms, add-ons and other bookings alone, and resets the sample, including its dates.

## Staff sign-in

Guests don't have accounts: they book and manage a stay with a booking ID and email. Staff sign in at `login.php`, linked from the footer as "Staff sign in", and land on the front desk at `admin.php`. Anyone not signed in who opens `admin.php` is sent to the sign-in page.

To set the password, run this in the project folder. It saves only a hash in `.env`:

```powershell
C:\xampp\php\php.exe tools\set-admin-password.php
```

The username is `admin`. To change it, add `ADMIN_USERNAME=your-name` to `.env`. Then open http://localhost:8000/login.php.

Sign-ins are limited to 5 attempts per 15 minutes per IP, and a session ends after 30 idle minutes. This is one shared login, which suits a personal project. A real hotel would need individual accounts.

### What staff can do

- **Bookings:** search and filter, change dates (the new total shows first), cancel with or without the fee, set the status and payment status, and see the history, which marks changes made by staff. Date changes and cancellations email the guest. "New reservation" opens the normal booking form.
- **Inquiries:** read them, reply by email, and mark them read or replied.
- **Rooms and Add-ons:** change prices and room counts, and take things off sale. New bookings use the change straight away. Existing bookings keep their price unless their dates change.
- **Database:** see row counts and open phpMyAdmin. The button only shows on the computer running the site, and needs Apache running in the XAMPP Control Panel. phpMyAdmin edits tables directly, so it skips the site's double-booking and price checks.

## Photos

The site uses the photos credited below. They show real places, not a real Maison Aurelle. To replace one, put your photo in `assets/img/originals/` using the names below (`.jpg`, `.png` or `.webp`), then run the tool. It center-crops each one, compresses it, and adds a smaller copy for phones. The originals stay out of git.

```powershell
C:\xampp\php\php.exe -d extension=gd tools\optimize-images.php
```

| Name | Size | Subject |
| --- | --- | --- |
| `hero` | 1920x1080 | The lobby or a suite in warm, low light, with calm space on the left for the headline |
| `spa` | 1200x1500 | Candlelight or another calm spa detail |
| `gym` | 1200x1500 | A barbell or other equipment in low light |
| `rooms/deluxe` | 1200x1500 | A tailored room in charcoal and linen, with a city view |
| `rooms/premier` | 1200x1500 | A high-floor room with wide windows and a reading nook |
| `rooms/junior-suite` | 1200x1500 | Open-plan suite with a marble bath, park and skyline |
| `rooms/executive-suite` | 1200x1500 | Separate lounge with a dining table for four, panoramic view |
| `rooms/garden-villa` | 1200x1500 | Plunge pool and a walled garden terrace |
| `rooms/presidential-suite` | 1200x1500 | A top-floor suite with a library or a wide terrace |

Dark, warm photos suit the design best. Use only photos you may use: your own, or ones under a license that allows this, and note the source of each. If a photo changes, update its alt text: in `experiences.php` for the spa and gym, or in `ROOM_PHOTOS` in `includes/helpers.php` for rooms. `ROOM_PHOTOS` also sets which part of a room photo stays in view in a wide panel.

### Photo credits

All from [Unsplash](https://unsplash.com/license), free to use under the Unsplash License. Credit isn't required, but it's appreciated.

| Used for | Photo | Photographer |
| --- | --- | --- |
| Home hero | [Lobby bar](https://unsplash.com/photos/hotel-lobby-bar-with-chandelier-f1JeYZ4B_XI) | Adrien Olichon |
| Spa | [Three candles](https://unsplash.com/photos/three-lit-candles-sitting-on-a-table-in-front-of-a-window-8mMkX1gkkD4) | Sixteen Miles Out |
| Gym | [Barbell](https://unsplash.com/photos/a-close-up-of-a-barbell-weight-on-a-gym-floor-feR_xwPVfmI) | Rodrigo Rodrigues |
| Deluxe Room | [Bed and lamp](https://unsplash.com/photos/turned-on-study-lamp-beside-bed-F2SrBdv9swk) | Jp Valery |
| Premier Room | [Curtained window](https://unsplash.com/photos/a-bedroom-with-a-bed-chair-and-a-window-BLZ4xjos6E0) | Ryunosuke Kikuno |
| Junior Suite | [Marble bathroom](https://unsplash.com/photos/a-bathroom-with-a-sink-and-a-mirror-QCNV78nqCOQ) | Bilal Mansuri |
| Executive Suite | [Lounge](https://unsplash.com/photos/empty-gray-fabric-sofas-with-turned-on-floor-lamp-HwsRkHfSU60) | Annie Spratt |
| Garden Villa | [Pool at night](https://unsplash.com/photos/a-pool-in-front-of-a-building-at-night-iO202mjA1PQ) | Javier Haro |
| Presidential Suite | [Study](https://unsplash.com/photos/an-office-with-a-desk-chair-and-bookshelves-ugkOAFZZlxw) | Florian Peeters |

## Motion

All motion is plain CSS and JavaScript with no libraries, and it adds to pages that already work without it.

- The footer's Motion button switches to Calm, which stops the slider, parallax and pinning, and skips the loader. The choice is remembered.
- With `prefers-reduced-motion`, the loader and page wipes are skipped and everything rests in its final state.
- Without JavaScript, the hero shows its first image, every room shows its full details, and the treatment menu is a swipeable row.
- The Golden Hour loader plays once per visit, on the first page only.
- The slider can be paused, and it holds still while its controls are hovered or focused. With Calm or reduced motion it never moves by itself, so the pause button is hidden.

## Email

Nothing needs setting up to try it. By default each message is saved as an `.eml` file in `storage/mail/` (ignored by git and blocked from the web), which you can open in most mail apps.

Guests get an email when they book, change dates or cancel, with a calendar file attached. The front desk gets one for each inquiry. To really send them, set these in `.env`:

```
MAIL_TRANSPORT=smtp
SMTP_HOST=your-provider's-host
SMTP_PORT=587
SMTP_USER=your-username
SMTP_PASS="your-password"
MAIL_FROM="an-address-your-provider-allows"
FRONT_DESK_EMAIL="where-inquiries-should-go"
SITE_URL="https://your-site"
```

A booking is saved even if its email fails, and the confirmation page then leaves out the "on its way" line.

## AI concierge

The chat works with no setup, answering common questions from a built-in FAQ. To turn on the AI, add a key to `.env`:

```
LLM_API_KEY=your-key-here
```

- It uses the Claude Messages API format. `LLM_API_URL` and `LLM_MODEL` are optional and default to Anthropic's API and `claude-opus-5-5`.
- Each message costs a little money. For a cheaper option, set `LLM_MODEL=claude-haiku-5-5`.
- The model can only read room availability, prices and add-ons. It cannot book, change or cancel anything: `prefill_booking` only builds a link, and the guest confirms on the booking page.
- Prices in the chat's cards come from `quote()` and `available_rooms()`, not from the model's text.
- If the API fails, the chat falls back to the FAQ. Messages are limited to 500 characters and 20 per 10 minutes per IP.
- `php -S` handles one request at a time, so other pages wait while a chat reply is being generated. This only affects local development.

## Run locally on Windows with XAMPP

1. In the XAMPP Control Panel, start MySQL.
2. Copy `.env.example` to `.env`. The defaults match XAMPP.
3. In phpMyAdmin, import `database/schema.sql` once, then `database/seed.sql`.
4. In the project folder, start the site:

   ```powershell
   C:\xampp\php\php.exe -S localhost:8000 router.php
   ```

5. Open http://localhost:8000.

`router.php` matters: the PHP built-in server ignores `.htaccess`, so without the router it serves `.env` and `database/*.sql` to anyone who can reach it.

## Requirements

PHP 8.1 or newer with `pdo_mysql` (developed against XAMPP's PHP 8.1), and MySQL 8 or MariaDB 10.4 or newer.

## Layout

| Path | Purpose |
| --- | --- |
| `index.php`, `rooms.php`, `booking.php`, `experiences.php`, `manage.php`, `contact.php`, `login.php`, `admin.php` | Pages |
| `api/quote.php`, `api/availability.php`, `api/booking-ics.php`, `api/chat.php` | Price preview, availability for the date picker, calendar download, and the concierge |
| `includes/` | Config, database, session and CSRF, helpers, booking, reservation, mail and concierge logic, hotel facts, header, footer and chat widget |
| `assets/css/`, `assets/js/`, `assets/img/` | Styles, scripts and images |
| `database/` | Schema and seed data, including the sample booking |
| `tools/` | The photo tool and the staff password tool, for the command line only |

Prices are always calculated on the server by `quote()` in `includes/booking-engine.php`. The browser only previews them. Hotel settings such as tax, service charge and time zone are constants in `includes/config.php`.

## Stack

Semantic HTML, CSS and ES modules with no frameworks or build step. PHP with PDO prepared statements, and MySQL or MariaDB. The Contact map uses [Leaflet](https://leafletjs.com) with [OpenStreetMap](https://www.openstreetmap.org/copyright) tiles, loaded only when the map nears the screen.
