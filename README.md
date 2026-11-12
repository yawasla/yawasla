# Yawasla

Yawasla is an online newsroom for mosques and Muslim associations: publish Janaza prayer announcements, news and donation appeals, and reach your community directly through web push notifications and mail, with no app to install: **<https://yawasla.org>**

[![license](https://img.shields.io/github/license/yawasla/newsroom.svg)](https://github.com/yawasla/newsroom/blob/main/LICENSE)

## Table of contents

- [Why Yawasla](#why-yawasla)
- [Quick start](#quick-start)
- [Deployment](#deployment)
- [Tech stack](#tech-stack)
- [Contact](#contact)
- [Responsibility](#responsibility)
- [License](#license)

## Why Yawasla

- **Open source and self-hosted**: the association owns its data.
- **Focused on communication**: announcements, news and donation appeals.
- **Browser-native**: web push and mail, nothing to download from an app store.
- **Shareable**: every announcement has its own page with a clean preview on WhatsApp and Facebook.
- **Easy and secure**: installable by beginners, secure by default.

## Quick start

⚠️ Early development stage: nothing is usable yet.

#### Requirements

- PHP 8.1+
- Composer
- SQLite 3.26+ (default) or MySQL 8.0+ / MariaDB 10.11+
- Node.js 22+, only to compile the assets from the repository (release archives already contain them)

#### Run locally

From the project root, install the dependencies, compile the assets and create your `.env` from the example:

```
composer install
npm install
npm run build
cp .env.example .env
```

Then start the dev server (PHP's built-in server, port 8000 by default):

```
php bin/console serve [port]
```

Open <http://localhost:8000> and stop the server with `Ctrl+C`.

- To show the debug toolbar (errors, dumps, SQL queries…), set `APP_ENV=dev` in `.env`. Never in production.
- The built-in server ignores `.htaccess` files: headers, caching and the `uploads/` protection can only be tested on Apache.
- Routes and Twig templates are cached: after adding or changing a route, run `php bin/console cache:clear`.
- To create or update the database, run `php bin/console db:migrate`: it applies the pending migrations of every module (`migrations/YYYYMMDD-XX.php`).
- To create an empty migration file in a module, run `php bin/console db:migrate --create <Module>`.
- To move the database to another SQL server, see below.

#### Moving to another SQL server

`php bin/console db:migrate --server <target>` copies the whole database **from** the server set in `DB_DRIVER` **to** the target given on the command line. `DB_DRIVER` must therefore still point to the current server during the copy: change it only once the copy has succeeded.

From SQLite to MySQL / MariaDB:

1. Create the database (`utf8mb4` / `utf8mb4_unicode_ci`), or use an existing one with no table starting with `DB_PREFIX`
2. In `.env`, fill in `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and **keep** `DB_DRIVER=sqlite`
3. Run `php bin/console db:migrate --server mysql`
4. Set `DB_DRIVER=mysql`

From MySQL / MariaDB to SQLite:

1. Make sure `data/database.sqlite` does not exist, or has no table starting with `DB_PREFIX`
2. **Keep** `DB_DRIVER=mysql` and run `php bin/console db:migrate --server sqlite`
3. Set `DB_DRIVER=sqlite`

Put the site in maintenance first: anything written during the copy would be lost. The current database is only read, never changed. If the copy fails, the message names the faulty row, the tables created in the target are removed, and you can run it again once the row is fixed.

#### Routes

Routes are declared with the `#[Route]` attribute on controllers. Parameters such as `{slug}` accept any character, Arabic included, except `/`, `\`, `..` and control characters: a URL containing one of them answers 404.

#### Assets

Sources are in each module's `assets/ts/` (TypeScript) and `assets/scss/` (SCSS, Bootstrap included in `modules/Core/Main`), compiled with [Vite](https://vite.dev) to `public/assets/js/` and `public/assets/css/`. Compiled files are not committed: build them after cloning and after every change.

| Command | Output |
|---|---|
| `npm run check` | checks the TypeScript types, compiles nothing |
| `npm run dev` | checks the types, then compiles once, readable files with source maps |
| `npm run watch` | compiles like `dev` on every change, without checking the types |
| `npm run build` | checks the types, then compiles once, minified files: run it before packaging a release |

Every file directly in a module's `assets/ts/` or `assets/scss/` is an entry, except partials (`_variables.scss`…). File names contain a hash and change with their content: templates load them with the Twig function `vite('@main/ts/app.ts')`, which reads `public/assets/.vite/manifest.json`. `app.rtl.scss` gives the right-to-left version of the styles, used for Arabic.

#### Modules

The whole site is made of modules: `modules/Core/` for the core, `modules/Extension/` for yours. Never edit a core module: disable it and enable your own instead. To customize the home page, copy `templates/custom.twig.example` to `templates/custom.twig` and place the blocks you want with `{{ module('Name') }}`. Updates never replace `modules/Extension/` nor `templates/custom.twig`.

## Deployment

SSL is required.

Download a release archive (it contains the compiled assets, no Node.js needed) or build the assets with `npm run build`, upload the project without `node_modules/`, and point the site's document root to the `public/` folder.

On shared hosting, rename `public/` to your host's web folder (OVH: `www/`, cPanel: `public_html/`) and upload the rest of the project to its parent folder. Everything outside the web folder (code, database, uploaded media) is never reachable from the web.

If the site answers with an empty **503** page, the document root points to the project root instead of `public/`. On Apache, the `.htaccess` at the project root then blocks every file (`.env`, `vendor/`…) until the document root is fixed. Nginx ignores `.htaccess` files: its `root` must point to `public/`.

## Tech stack

- **Backend**: PHP 8.1+, server-rendered pages with [Twig](https://twig.symfony.com)
- **Frontend**: [Bootstrap](https://getbootstrap.com) (CSS and JS, served locally), our styles in SCSS, TypeScript, compiled with [Vite](https://vite.dev); the subscription page (`/feed/`) is a small PWA for web push
- **Database**: SQLite by default, MySQL / MariaDB optional, through [Medoo](https://medoo.in)
- **Notifications**: web push, mail through Brevo (kept interchangeable)
- **License**: Apache 2.0

More architecture details in [CLAUDE.md](./CLAUDE.md).

## Contact

- Website: <https://yawasla.org>
- Mail: [contact@yawasla.org](mailto:contact@yawasla.org)
- GitHub: <https://github.com/yawasla/newsroom>

## Responsibility

Author disclaims any responsibility for the use that is made with this tool.

```text
Al-Nu'man ibn Bashir reported,
The Messenger of Allah (Peace and Blessings be upon Him) said: « Verily, the lawful is clear and the unlawful is clear, and between the two of them they are doubtful matters about which many people don't know. Thus, he who avoids doubtful matters clears himself in regard to his religion and his honor, and he who falls into doubtful matters will fall into the unlawful as the shepherd who pastures near a sanctuary, all but grazing there in. Verily, every king has a sanctum and the sanctum of Allah is his prohibitions. Verily, in the body is a piece of flesh which, if sound, the entire body is sound, and if corrupt, the entire body is corrupt. Truly, it is the heart. »
Sahih al-Bukhārī 52, Sahih Muslim 1599
```

```text
D'après Nu'man Ibn Bachir (qu'Allah l'agrée),
Le Messager d'Allah (que La Prière d'Allah et Son Salut soient sur Lui) a dit : « Certes le halal est clair et certes le haram est clair et il y a entre les deux des choses ambiguës que peu de gens connaissent. Celui qui s'écarte des choses ambiguës a préservé sa religion et son honneur. Quant à celui qui tombe dans les choses ambiguës il tombe dans le haram comme le berger qui fait paitre ses bêtes près d'un enclos réservé et qui sont sur le point de rentrer dedans. Certes chaque roi a un domaine réservé et certes le domaine réservé d'Allah est ses interdits. Certes il y a dans le corps un morceau de chair, si il est bon alors l'ensemble du corps est bon tandis que si il est mauvais alors c'est l'ensemble du corps qui est mauvais, certes il s'agit du coeur. »
Sahih al-Bukhārī 52, Sahih Muslim 1599
```

## License

Copyright © Yawasla contributors

Licensed under the [Apache License 2.0](./LICENSE).
