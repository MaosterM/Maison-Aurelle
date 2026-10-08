# Maison Aurelle

A five-star hotel booking site: PHP 8 + MySQL, vanilla JS, with a slash-wipe motion system in charcoal, ivory and champagne gold.

## Status
Phase 1 done: database, PHP core, design system, motion engine, Home page.
Next: booking engine, Rooms, Experiences, Manage Reservation, Contact, AI concierge.

## Run locally
1. Copy `.env.example` to `.env` and set your database credentials.
2. Import `database/schema.sql`, then `database/seed.sql`.
3. Serve this folder with PHP 8.2+ (for example `php -S localhost:8000`) and open `index.php`.
4. Optional: add `assets/img/hero.jpg` for the hero background.

## Stack
HTML, CSS, vanilla ES modules, PHP 8.2 (PDO), MySQL 8 or MariaDB 10.6+.
