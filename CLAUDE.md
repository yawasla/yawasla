# Yawasla — Contexte projet

Newsroom en ligne pour les mosquées et associations musulmanes (annonces Janaza, actualités, appels au don…), diffusée par web push et mail, sans appli à installer. Open source (Apache 2.0), auto-hébergé.

**Deux critères arbitrent tout : facilité et sécurité.** Facile à installer et à maintenir par un débutant ; sécurisé par défaut, car un site compromis qui publie au nom d'une mosquée nuit durablement à son image. Moins de code = moins de surface d'attaque : en cas de doute, la solution la plus simple.

## Méthode de travail

- **Le porteur du projet décide de ce qu'on fait.** Claude ne propose pas de plan ni de refonte de sa propre initiative
- On réfléchit ensemble sur chaque sujet ; Claude donne son avis quand on le lui demande
- Claude ne code qu'une fois l'accord explicite obtenu, uniquement ce qui a été validé, puis le porteur relit
- Ce fichier n'est mis à jour que sur demande, ou pour une décision critique

## Principes

- **Pas de framework** : PHP rendu côté serveur, CSS simple, JS vanilla uniquement là où c'est indispensable (push)
- **Outillage au fur et à mesure** : Composer et Node ajoutés seulement quand un besoin réel le justifie. Le débutant ne lance jamais ni l'un ni l'autre : `vendor/` et les assets compilés sont livrés dans l'archive
- **Pas d'abstraction maison** (conteneur, ORM, cache…) : des classes simples. Base de données via Medoo (un fichier, aucune dépendance), appelé uniquement dans `Model/`, jamais dans les contrôleurs
- **Une fonctionnalité complète avant la suivante** : publier une annonce visible en public avant tout le reste

## Contraintes

- **PHP 8.1+** : rien au-delà de 8.1 (pas de `readonly` sur les classes ni de constantes typées, qui sont en 8.2/8.3)
- SQLite par défaut ; MySQL 8 / MariaDB 10.11 en option
- **SSL obligatoire** (web push, cookies `Secure`). Hébergement cible : mutualisé (OVH, cPanel) comme VPS

## Structure

```
org/            cœur, jamais exposé au web
├── bootstrap.php
├── .env        config (APP_ENV dev/prod, APP_HOST, BDD, SMTP), non versionné ; modèle : .env.example
├── src/        classes (namespace Yawasla\)
│   ├── Controller/   Controller.php (base) + Front/, Fil/, Admin/
│   ├── Core/         Config, Database, Migrator, erreurs
│   ├── Http/         Router, Route (attribut), Session, Csrf
│   ├── Auth/         Auth, LoginThrottle, TokenStore
│   ├── Model/        une classe par table, avec ses requêtes SQL
│   ├── Mail/         Mailer (mails de sécurité)
│   ├── Notify/       WebPush, Brevo (diffusion)
│   ├── I18n/         Translator, filtre Twig |trans
│   ├── Seo/          Sitemap, Schema.org, Open Graph
│   └── Form/         Validator, Upload
├── templates/  vues Twig : front/, fil/, admin/ (comme les contrôleurs), errors/
├── data/       base SQLite, sessions, logs, cache/ (Twig, routes.php)
├── nbe/        ancien moteur NBE, non versionné : on y pioche du code à réécrire, rien n'en est chargé
└── public/     racine web, renommable (www/, public_html/…)
    ├── index.php   seul point d'entrée PHP
    └── uploads/    médias, nom aléatoire, rangés par dossier
```

- `public/index.php` retrouve le cœur via `__DIR__ . '/../'`, jamais par le nom du dossier
- Dossiers de `src/` créés à l'arrivée de leur première classe
- Routes en attributs `#[Route]` sur les contrôleurs (sur la classe : préfixe), compilées dans `data/cache/routes.php`, régénéré seulement s'il n'existe pas. **`data/cache/` est supprimé à chaque mise à jour**
- Dev : `php -S localhost:8000 -t public public/index.php`

## Conventions

- Jamais d'URL absolue enregistrée en base (contenu, réglages) : liens relatifs via `path()`, URL absolues des pages calculées à la requête
- Vues en Twig (échappement automatique) : `|raw` uniquement sur du HTML de confiance
- Clés des tableaux Medoo (colonnes, opérateurs) toujours écrites dans le code, jamais issues de la requête HTTP
- Commentaires uniquement sur les points critiques (sécurité, piège non évident) : une ligne, le pourquoi, jamais ce que le code dit déjà

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
- Connexion en deux temps, sans désactivation ni « appareil de confiance » : identifiants, puis lien à usage unique de 5 min par mail. Le lien ouvre une page avec un bouton qui valide (jamais à l'ouverture : scanners de liens)
- Réponse d'échec uniforme (code, corps et temps) quelle que soit la raison ; limitation par IP qui n'annonce jamais le blocage
- Liens absolus des mails construits depuis `APP_HOST` (`.env`), **jamais** l'en-tête `Host`. Changer de domaine = copier les fichiers + modifier cette ligne ; l'admin avertit si le domaine courant diffère
- Jamais de stack trace au client ; jamais de sécurité désactivée en dev

**Mail** : deux voies. Sécurité (connexion, reset) via PHPMailer (SMTP ou `mail()`), indépendante du fournisseur de diffusion. Diffusion des annonces via Brevo, interchangeable.

**i18n** : fr au lancement, arabe prévu dès maintenant. RTL : propriétés CSS logiques uniquement. Langue d'interface ≠ langue du contenu.

## Notes

Plugin WordPress dédié, installeur avancé, mise à jour automatique, multi-utilisateur complet, gestion des prières.
