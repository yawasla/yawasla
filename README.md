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
- SQLite (default) or MySQL 8.0+ / MariaDB 10.11+
- Node.js 22+, only to compile the styles (the compiled CSS is committed)

#### Run locally

From the project root, install the dependencies and create your `.env` from the example:

```
composer install
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

#### Routes

Routes are declared with the `#[Route]` attribute on controllers. Parameters such as `{slug}` accept any character, Arabic included, except `/`, `\`, `..` and control characters: a URL containing one of them answers 404.

#### Styles

Sources are in `public/assets/scss/`, compiled to `public/assets/css/` with [Sass](https://sass-lang.com). Install it once with `npm install`, then:

| Command | Output |
|---|---|
| `npm run dev` | compiles once, readable CSS with source maps |
| `npm run watch` | same as `dev`, recompiles on every change |
| `npm run build` | compiles once, minified CSS: run it before committing |

Partials (`_variables.scss`…) are only imported; every other `.scss` file gives a `.css` file of the same name.

## Deployment

SSL is required.

Upload the project and point the site's document root to the `public/` folder.

On shared hosting, rename `public/` to your host's web folder (OVH: `www/`, cPanel: `public_html/`) and upload the rest of the project to its parent folder. Everything outside the web folder (code, database, uploaded media) is never reachable from the web.

If the site answers with an empty **503** page, the document root points to the project root instead of `public/`. On Apache, the `.htaccess` at the project root then blocks every file (`.env`, `vendor/`…) until the document root is fixed. Nginx ignores `.htaccess` files: its `root` must point to `public/`.

## Tech stack

- **Backend**: PHP 8.1+, server-rendered pages with [Twig](https://twig.symfony.com)
- **Frontend**: [Bootstrap](https://getbootstrap.com) (CSS and JS, served locally), our styles in SCSS, native ES modules; the subscription page (`/fil/`) is a small PWA for web push
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
