# Yawasla — Contexte projet

Newsroom en ligne pour les mosquées et associations musulmanes (annonces Janaza, actualités, appels au don…), diffusée par web push et mail, sans appli à installer. Open source (Apache 2.0), auto-hébergé.

**Deux critères arbitrent tout : facilité et sécurité.** Facile à installer et à maintenir par un débutant ; sécurisé par défaut, car un site compromis qui publie au nom d'une mosquée nuit durablement à son image. Moins de code = moins de surface d'attaque : en cas de doute, la solution la plus simple.

## Méthode de travail

- **Le porteur du projet décide de ce qu'on fait.** Claude ne propose pas de plan ni de refonte de sa propre initiative
- On réfléchit ensemble sur chaque sujet ; Claude donne son avis quand on le lui demande
- Claude ne code qu'une fois l'accord explicite obtenu, uniquement ce qui a été validé, puis le porteur relit
- Ce fichier n'est mis à jour que sur demande, ou pour une décision critique

## Principes

Repères plutôt que règles : ils ne doivent pas freiner le développement, tant que ça reste simple et sûr.

- PHP rendu côté serveur, Twig pour les vues
- Front : Bootstrap (CSS et JS), nos styles en SCSS, nos scripts en modules ES natifs
- Classes simples ; singletons via le trait `Singleton` pour les services transverses (`Config`, `Router`, `Translator`, `Database`, `Debug`)
- Base de données via Medoo, appelé dans `Model/`
- Une dépendance ou un outil de plus seulement s'il simplifie vraiment
- Le cœur n'est pas modifié par l'utilisateur : personnalisation via un système dédié (à venir)

## Environnement

- PHP 8.1+ ; SQLite par défaut, MySQL / MariaDB en option
- SSL obligatoire (web push, cookies `Secure`). Hébergement cible : mutualisé (OVH, cPanel) comme VPS
- Dev uniquement : Node 22+ et sass pour compiler le SCSS. Le CSS compilé est versionné, l'installation n'a pas besoin de Node

## Structure

```
./
├── bootstrap.php
├── bin/console     CLI : serve [port], cache:clear
├── package.json    npm run dev | build | watch (SCSS → CSS)
├── .env            config (APP_ENV, BDD, SMTP), non versionné ; modèle : .env.example
├── src/            classes (namespace Yawasla\)
│   ├── Controller/   Controller.php (base) + Front/, Fil/, Admin/
│   ├── Command/      commandes de bin/console
│   ├── Core/         Config, Database
│   ├── Debug/        barre de debug, dump()
│   ├── Http/         Router, Route (attribut)
│   ├── I18n/         Translator, filtre Twig |trans
│   ├── Trait/        Singleton
│   └── …             Model/, Auth/, Mail/, Notify/, Seo/, Form/ à venir
├── templates/      vues Twig : front/, fil/, admin/, errors/, partials/, debug/
├── translations/   fr.php, ar.php (l'anglais est écrit dans le code)
├── data/           base SQLite, sessions, logs, cache/ (Twig, routes.php)
├── nbe/            ancien moteur NBE, non versionné : on y pioche des idées, rien n'en est chargé
└── public/         racine web, renommable (www/, public_html/…)
    ├── index.php   seul point d'entrée PHP
    ├── assets/     scss/ → css/, js/, vendor/ (Bootstrap copié à la main)
    └── uploads/    médias
```

- Dossiers de `src/` créés à l'arrivée de leur première classe
- Installation possible en sous-dossier : préfixe déduit de `SCRIPT_NAME`, ajouté par `path()` et `asset()`
- Routes en attributs `#[Route]`, compilées dans `data/cache/routes.php`. **`data/cache/` est supprimé à chaque mise à jour**
- Dev : `php bin/console serve [port]`, `npm run watch`, `APP_ENV=dev` dans `.env`

## Sécurité

**Aucun commentaire dans le code** (projet open source) : le pourquoi des protections est ici, nulle part ailleurs.

Règles :
- Twig échappe tout : `|raw` uniquement sur du HTML de confiance
- Rien de la requête HTTP en nom de colonne, clé Medoo, chemin de fichier ou `redirectExternal()`
- Pas d'URL absolue en base ; liens des mails depuis le domaine en base, jamais l'en-tête `Host`
- Pas de JS ni de style inline, pas de CDN : la CSP l'interdit, tout ajout externe doit y être déclaré
- Uploads : liste blanche d'images uniquement (un SVG ou un HTML exécuterait du JS sur le domaine)
- `AdminController` ne protège encore rien : aucune route dans `Admin/` avant l'authentification

En place :
- `bootstrap.php` envoie les en-têtes (CSP, HSTS sans `includeSubDomains`…) pour tout serveur ; `public/.htaccess` pour les fichiers statiques
- Racine du projet exposée par erreur : `.htaccess` bloque tout, `index.php` répond 503 (nginx non couvert)
- `data/` et scripts dans `uploads/` refusés par leur `.htaccess`
- Debug seulement si `APP_ENV` vaut exactement `dev` ; erreurs jamais affichées au client
- Langue filtrée par liste blanche ; paramètres de route en 404 s'ils contiennent `/`, `\`, `..` ou un caractère de contrôle
- `redirect()` : chemin interne uniquement (`//`, `/\` et caractères de contrôle mènent ailleurs) ; `redirectExternal()` : `http`/`https`. Sinon, accueil

## Produit

| Chemin | Rôle |
|---|---|
| `/` | Newsroom publique : infos de l'organisme, annonces |
| `/annonce/{slug}` | Page d'une annonce, balises Open Graph (aperçu WhatsApp/Facebook) |
| `/fil/` | **Seule partie PWA** (manifest et service worker limités à `/fil/`) : fil, abonnement push et mail |
| `/admin/` | Back-office, hors portée du service worker |

- iOS : web push uniquement si le site est ajouté à l'écran d'accueil (iOS 16.4+) → guide dans `/fil/`, et le mail reste indispensable

## Remarques

**Authentification** — on part du principe qu'il y aura des attaques
- Session PHP native, fichiers dans `data/sessions`, cookie `Secure`, `HttpOnly`, `SameSite=Lax`
- Connexion en deux temps : identifiants, puis lien à usage unique de 5 min par mail. Le lien ouvre une page avec un bouton qui valide (jamais à l'ouverture : scanners de liens)
- Réponse d'échec uniforme quelle que soit la raison ; limitation par IP qui n'annonce jamais le blocage

**Mail** : deux voies. Sécurité (connexion, reset) via PHPMailer (SMTP ou `mail()`), indépendante du fournisseur de diffusion. Diffusion des annonces via Brevo, interchangeable.

**i18n** : interface du système en fr, en, ar ; langue de l'admin dans l'URL (`/admin/fr/…`), celle du front dans `.env`. RTL : propriétés CSS logiques dans nos styles, `bootstrap.rtl.min.css` pour Bootstrap. Langue d'interface ≠ langue du contenu.

## Notes

Plugin WordPress dédié, installeur avancé, mise à jour automatique, multi-utilisateur complet, gestion des prières.
