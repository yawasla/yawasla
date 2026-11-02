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
│   ├── Controller/   AbstractController.php (base), AbstractAdminController.php
│   ├── Command/      commandes de bin/console
│   ├── Core/         Config, Modules
│   ├── Database/     Connection, AbstractMigration, Migrator, SQLiteAdapter, Transfer
│   ├── Debug/        barre de debug, dump()
│   ├── Http/         Router, Route (attribut), Vite (balises des assets)
│   ├── I18n/         Translator, filtre Twig |trans
│   ├── Trait/        Singleton
│   └── …             Model/, Auth/, Mail/, Notify/, Seo/, Form/ à venir
├── modules/
│   ├── Core/         modules du cœur : Main, News, Feed…
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
- Routes en attributs `#[Route]`, compilées dans `data/cache/routes.php`. **`data/cache/` est supprimé à chaque mise à jour**
- Dev : `php bin/console serve [port]`, `npm run watch`, `APP_ENV=dev` dans `.env`

## Requête

`public/index.php` → `bootstrap.php` (en-têtes de sécurité, autoload des modules, gestion des erreurs) → `Router` (route compilée → contrôleur d'un module) → `Controller::render()` (Twig, barre de debug en dev).

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
- `Core/Main` (layout, partials, erreurs, debug, thème, Bootstrap, migrations du cœur) est verrouillé, comme `Admin` et `Install` à venir : on ne peut pas les désactiver
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
