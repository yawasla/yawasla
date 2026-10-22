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

#### Run locally

From the project root, install the dependencies and create your `.env` from the example, with `APP_ENV=dev`:

```
composer install
cp .env.example .env
```

Then start PHP's built-in server:

```
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000> and stop the server with `Ctrl+C`.

- `public/index.php` at the end routes every URL through the app router.
- The built-in server ignores `.htaccess` files: headers, caching and the `uploads/` protection can only be tested on Apache.
- Routes are cached: after adding or changing a route, delete `data/cache/routes.php`.

## Deployment

SSL is required.

Upload the project and point the site's document root to the `public/` folder.

On shared hosting, rename `public/` to your host's web folder (OVH: `www/`, cPanel: `public_html/`) and upload the rest of the project to its parent folder. Everything outside the web folder (code, database, uploaded media) is never reachable from the web.

## Tech stack

- **Backend**: plain PHP 8.1+, server-rendered pages, PDO
- **Frontend**: HTML, CSS and vanilla JavaScript; the subscription page (`/fil/`) is a small PWA for web push
- **Database**: SQLite by default, MySQL / MariaDB optional
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
