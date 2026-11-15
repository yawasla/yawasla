# Structure

## Arborescence

```
./
├── bootstrap.php
├── bin/console     CLI : serve [port], cache:clear, db:migrate, maintenance [on|off], module list|enable|disable
├── package.json    npm run check | dev | build | watch
├── vite.config.ts  build des assets ; tsconfig.json pour la vérification des types
├── .env            config (APP_ENV, BDD, SMTP), non versionné ; modèle : .env.example
├── src/            noyau (namespace Yawasla\)
│   ├── Auth/         Auth (accès des routes, connexion), Password, Throttle
│   ├── Controller/   AbstractController.php (base), AbstractAdminController.php
│   ├── Command/      commandes de bin/console
│   ├── Core/         Config, Modules, ModuleManager, Installation, Maintenance, Cache, Trash, System
│   ├── Database/     Connection, AbstractMigration, Migrator, AbstractAdapter, MySQLAdapter, SQLiteAdapter, Transfer
│   ├── Debug/        barre de debug, dump()
│   ├── Form/         Form (CSRF, validation)
│   ├── Http/         Router, Route (attribut), Vite (balises des assets)
│   ├── I18n/         Translator, filtre Twig |trans
│   ├── Mail/         Mailer (PHPMailer, mails de sécurité)
│   ├── Model/        User, Token, Setting (table config)
│   ├── Trait/        Singleton
│   └── …             Notify/, Seo/ à venir
├── modules/
│   ├── Core/         modules du cœur : Main, Auth, Install, News, Feed…
│   └── Extension/    modules de l'utilisateur
├── templates/      index.twig (structure de l'accueil), main.twig (accueil du cœur), custom.twig.example
├── specs/          specs détaillées par partie
├── data/           base SQLite, sessions, logs, cache/ (Twig, routes.php), installed, maintenance, modules.json, trash/
├── nbe/            ancien moteur NBE, non versionné : on y pioche des idées, rien n'en est chargé
└── public/         racine web, renommable (www/, public_html/…)
    ├── index.php   seul point d'entrée PHP
    ├── assets/     js/, css/, .vite/ (compilés depuis les modules, non versionnés), img/
    └── uploads/    médias
```

- Dossiers de `src/` et des modules créés à l'arrivée de leur premier fichier
- Installation possible en sous-dossier : préfixe déduit de `SCRIPT_NAME`, ajouté par `path()` et `asset()`
- Routes en attributs `#[Route]`, compilées dans `data/cache/routes.php`, recompilées à chaque requête en dev. **`data/cache/` est supprimé à chaque mise à jour**
- Pas de slash final dans les URL (sauf l'accueil) : `/feed/` redirige en 301 vers `/feed`, query string conservée. `<link rel="canonical">` (chemin relatif) sur toute page servie par une route
- Méthode non acceptée par une route existante : 405 avec l'en-tête `Allow`
- Dev : `php bin/console serve [port]`, `npm run watch`, `APP_ENV=dev` dans `.env`

## Requête

`public/index.php` → `bootstrap.php` (en-têtes de sécurité, autoload des modules, gestion des erreurs) → `Router` (route compilée → contrôleur d'un module) → `Controller::render()` (Twig, barre de debug en dev).

**Accès** : `#[Route(..., auth: 'guest'|'user'|'admin')]`, sur la classe (hérité par ses méthodes) ou sur la méthode, qui l'emporte.
- `guest` : tout le monde ; `user` : compte connecté et actif ; `admin` : compte avec le rôle `admin` (inclut `user`)
- Le Router vérifie la session avant d'instancier le contrôleur. Refus : non connecté, `/admin/*` → 302 vers `/login`, ailleurs 404 ; connecté sans le droit → 403. Détail : `specs/auth.md`
- Par défaut `guest`, sauf en **strict route mode** : aucun défaut, chaque route doit déclarer son `auth`. Le mode se définit dans le contrôleur abstrait (désactivé dans `AbstractController`, activé dans `AbstractAdminController`)
- Refusé à la compilation (`LogicException`) : valeur d'`auth` inconnue, route sans `auth` en strict route mode, route `/admin` dont le contrôleur n'hérite pas d'`AbstractAdminController` (qui envoie aussi `no-store` et `noindex`) et l'inverse (contrôleur admin hors `/admin` : il passerait la maintenance), `auth: 'guest'` sur un contrôleur admin
- Regex des routes en `$#D` : sans `D`, `$` accepte un saut de ligne final

**Langue** : `/admin`, `/login` et `/install` portent la langue dans l'URL (`/admin/fr/…`, `/login/ar/reset`, `/install/fr`), retirée avant le match (`Router::LOCALIZED`) ; sans préfixe : la langue de `APP_LANG` (anglais si absente ou inconnue), les autres langues ont leur préfixe (`/login/en` si `APP_LANG=fr`). `path()` et `localize()` ajoutent la langue en cours. Sur ces pages, `main/layout.twig` affiche un bouton rond flottant en haut (menu des trois langues, même page dans l'autre langue, via `locale_urls()`) ; le bouton du thème est en bas. Ailleurs, la langue vient de `APP_LANG` et le bouton n'apparaît pas.

**Messages flash** : `Session::flash($type, $message)` (`success`, `info`, `warning`, `danger`, sinon exception), message déjà traduit, affiché une fois par `main/partials/flash.twig` (inclus dans les layouts de connexion et d'admin), toujours échappé, fermable (alerte Bootstrap `alert-dismissible`). `flashes()` ne démarre pas de session sans cookie.

**Paramètres** : le type vient de la signature de la méthode, sans syntaxe dans le chemin. Chaque `{param}` correspond au paramètre de la méthode du même nom, transmis par nom.
- `string` : `[^/]+`, en 404 s'il contient `/`, `\`, `..` ou un caractère de contrôle
- `int` : `[1-9]\d{0,17}`, converti en entier (ni `0`, ni zéro en tête, ni dépassement d'entier) ; sinon 404 sans atteindre le contrôleur. Sans conversion, `strict_types` ferait d'un `int $id` une 500
- `bool`, hors strict route mode uniquement : `true` / `false` / `1` / `0`, convertis par le Router (jamais la règle de PHP, où `"false"` vaut `true`) ; sinon 404
- `float`, hors strict route mode uniquement : `-?(0|[1-9]\d{0,14})(\.\d{1,15})?`, converti en flottant (négatif permis, ni exposant, ni zéro en tête, ni `NAN` / `INF`) ; sinon 404
- Strict route mode : `string` et `int` uniquement
- Refusé à la compilation (`LogicException`) : `{param}` sans paramètre du même nom dans la méthode, paramètre sans type ou d'un type non permis

## Modules

Tout le site est fait de modules, ceux du cœur compris : `specs/modules.md`.

## Assets

- Sources dans `assets/ts/` et `assets/scss/` de chaque module ; Bootstrap (npm) est compilé avec le SCSS de `Core/Main`
- Vite compile tout fichier `modules/*/*/assets/{ts,scss}/*` sauf les partials `_*.scss`, vers `public/assets/js/` et `css/`, préfixé par le module (`main-app-[hash].css`)
- Noms hashés : `vite('@main/ts/app.ts')` dans Twig lit `assets/.vite/manifest.json` et écrit les balises (CSS, `modulepreload`, script) ; `vite(…, module: false)` pour un script classique (`theme.ts`, bloquant pour éviter le flash de thème). `asset()` reste pour les fichiers statiques (images)
- RTL : `app.rtl.scss` passe par rtlcss, seule entrée retournée
- Chaque build vide `css/`, `js/`, `media/` et `.vite/` : on n'y écrit jamais à la main
- Icônes : Phosphor (MIT), style `regular` seul, par npm, importé par `@main/ts/app.ts` pour tout le site ; police téléchargée seulement si une icône est affichée. Plugin Vite `woff2-only` : seul le woff2 est copié (le paquet déclare aussi ttf, woff et un svg de 3 Mo). Twig `icon('moon')` → `<i class="ph ph-moon" aria-hidden="true"></i>` (nom en minuscules et tirets, sinon exception) ; `icon-flip` retourne les icônes directionnelles en RTL
- Police : Google Sans (OFL), variable 400–700, sans italique, sur tout le site. Exception à npm : le paquet Fontsource n'a pas l'arabe, les woff2 `latin`, `latin-ext` et `arabic` de Google Fonts sont versionnés dans `Core/Main/assets/fonts/` avec `OFL.txt`, déclarés dans `_fonts.scss` (`unicode-range` : l'arabe n'est chargé que s'il est affiché). Mise à jour : retélécharger ces trois fichiers
- `npm run build` et `npm run dev` vérifient les types (`tsc`) avant de compiler ; `npm run watch` ne les vérifie pas
- Fichiers compilés non versionnés : les archives de release les contiennent
