# Structure

## Arborescence

```
./
├── bootstrap.php
├── bin/console     CLI : serve [port], cache:clear, db:migrate
├── package.json    npm run check | dev | build | watch
├── vite.config.ts  build des assets ; tsconfig.json pour la vérification des types
├── .env            config (APP_ENV, BDD, SMTP), non versionné ; modèle : .env.example
├── src/            noyau (namespace Yawasla\)
│   ├── Auth/         Auth (accès des routes, connexion), Password, Throttle
│   ├── Controller/   AbstractController.php (base), AbstractAdminController.php
│   ├── Command/      commandes de bin/console
│   ├── Core/         Config, Modules
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
├── data/           base SQLite, sessions, logs, cache/ (Twig, routes.php)
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
- Le Router vérifie la session avant d'instancier le contrôleur ; droit insuffisant → 404
- Par défaut `guest`, sauf en **strict route mode** : aucun défaut, chaque route doit déclarer son `auth`. Le mode se définit dans le contrôleur abstrait (désactivé dans `AbstractController`, activé dans `AbstractAdminController`)
- Refusé à la compilation (`LogicException`) : valeur d'`auth` inconnue, route sans `auth` en strict route mode, route `/admin` dont le contrôleur n'hérite pas d'`AbstractAdminController` (qui envoie aussi `no-store` et `noindex`)

**Langue** : `/admin` et `/login` portent la langue dans l'URL (`/admin/fr/…`, `/login/ar/reset`), retirée avant le match (`Router::LOCALIZED`) ; sans préfixe : anglais. `path()` et `localize()` ajoutent la langue en cours. Sur ces pages, `main/layout.twig` affiche un bouton rond flottant en haut (menu des trois langues, même page dans l'autre langue, via `locale_urls()`) ; le bouton du thème est en bas. Ailleurs, la langue vient de `APP_LANG` et le bouton n'apparaît pas.

**Paramètres** : le type vient de la signature de la méthode, sans syntaxe dans le chemin. Chaque `{param}` correspond au paramètre de la méthode du même nom, transmis par nom.
- `string` : `[^/]+`, en 404 s'il contient `/`, `\`, `..` ou un caractère de contrôle
- `int` : `[1-9]\d{0,17}`, converti en entier (ni `0`, ni zéro en tête, ni dépassement d'entier) ; sinon 404 sans atteindre le contrôleur. Sans conversion, `strict_types` ferait d'un `int $id` une 500
- `bool`, hors strict route mode uniquement : `true` / `false` / `1` / `0`, convertis par le Router (jamais la règle de PHP, où `"false"` vaut `true`) ; sinon 404
- `float`, hors strict route mode uniquement : `-?(0|[1-9]\d{0,14})(\.\d{1,15})?`, converti en flottant (négatif permis, ni exposant, ni zéro en tête, ni `NAN` / `INF`) ; sinon 404
- Strict route mode : `string` et `int` uniquement
- Refusé à la compilation (`LogicException`) : `{param}` sans paramètre du même nom dans la méthode, paramètre sans type ou d'un type non permis

## Modules

Tout le site est fait de modules, ceux du cœur compris : le noyau (`src/`) ne contient que le socle commun.

```
modules/Core/News/
├── Module.php      tableau : version, requires (modules requis), locked (cœur uniquement)
├── src/            namespace Yawasla\Module\Core\News\ ; Controller/ (routes #[Route]), Block.php
├── templates/news/
├── translations/   fr.php, ar.php
├── migrations/     YYYYMMDD-XX.php (extensions uniquement ; le cœur met tout dans Core/Main/migrations)
└── assets/         ts/, scss/
```

- Nom du module = nom du dossier (`News`), son identifiant en minuscules (`news`) sert aux templates (`news/show.twig`) et aux assets (`@news/ts/…`). Noms uniques entre `Core/` et `Extension/`
- **Un module ne remplace jamais un module du cœur** : on désactive celui du cœur et on active le sien. Twig cherche d'abord dans le cœur, puis dans les extensions, puis dans `templates/`
- `Core/Main` (layout, partials, erreurs, debug, thème, Bootstrap, migrations du cœur) est verrouillé, comme `Auth`, `Install` et `Admin` à venir : on ne peut pas les désactiver
- Authentification en deux parties : le noyau (`src/Auth/`) porte ce dont le Router et les autres modules ont besoin (accès, mot de passe, jetons, session) et ne dépend d'aucun module ; `Core/Auth` porte les pages (connexion sous `/login`, lien, déconnexion, reset), leurs templates, traductions et mails. Détail : `specs/auth.md`
- Traductions : celles de chaque module sont fusionnées, celles du cœur priment
- Bloc : `{{ module('Banner') }}` rend `banner/block.twig` avec les données de `Block` (classe invocable qui renvoie un tableau), ou rien si le module est absent ou désactivé
- Accueil : `custom.twig` s'il existe, sinon `main.twig` ; tous deux étendent `index.twig`. `custom.twig` appartient à l'utilisateur, non versionné et jamais remplacé à la mise à jour
- Mise à jour : on remplace tout sauf `modules/Extension/`, `templates/custom.twig`, `data/`, `.env` et `public/uploads/`
- Activation en base, depuis l'admin (à venir) : en attendant, tous les modules trouvés sont actifs. Changer l'activation doit vider `data/cache/` (routes compilées)
- Les extensions s'installent en déposant leur dossier sur le serveur, jamais depuis l'admin : une extension est du PHP avec tous les droits

## Assets

- Sources dans `assets/ts/` et `assets/scss/` de chaque module ; Bootstrap (npm) est compilé avec le SCSS de `Core/Main`
- Vite compile tout fichier `modules/*/*/assets/{ts,scss}/*` sauf les partials `_*.scss`, vers `public/assets/js/` et `css/`, préfixé par le module (`main-app-[hash].css`)
- Noms hashés : `vite('@main/ts/app.ts')` dans Twig lit `assets/.vite/manifest.json` et écrit les balises (CSS, `modulepreload`, script) ; `vite(…, module: false)` pour un script classique (`theme.ts`, bloquant pour éviter le flash de thème). `asset()` reste pour les fichiers statiques (images)
- RTL : `app.rtl.scss` passe par rtlcss, seule entrée retournée
- Chaque build vide `css/`, `js/` et `.vite/` : on n'y écrit jamais à la main
- `npm run build` et `npm run dev` vérifient les types (`tsc`) avant de compiler ; `npm run watch` ne les vérifie pas
- Fichiers compilés non versionnés : les archives de release les contiennent
