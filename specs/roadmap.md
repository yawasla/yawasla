# Roadmap

Ce qui est décidé, dans l'ordre. Une idée de `ideas.md` n'arrive ici qu'une fois validée.

## Fait

- Socle : Router (routes en attributs, compilées), Twig, i18n fr / en / ar avec RTL, barre de debug, en-têtes de sécurité
- Assets : Vite, TypeScript, Bootstrap compilé depuis npm, noms hashés
- Modules : `modules/Core` et `modules/Extension`, routes, templates, traductions, assets et blocs par module, `custom.twig`
- Schéma de la base : `specs/database.md`, migration `Core/Main/migrations/20261031-01.php`
- Install : `/install` (vérifications, site, organisation, premier utilisateur), fermé une fois l'installation terminée ; `Migrator` traducteur vers SQLite, `db:migrate`, changement de serveur `db:migrate --server` : `specs/install.md`, `specs/migration.md`
- Authentification : accès dans `#[Route(auth:)]`, paramètres de route typés, module `Core/Auth` (connexion en deux temps, jetons, limitation, session 24 h, déconnexion, reset), `/admin` → `/login` hors connexion, 403, `MAIL_DRIVER=log` : `specs/auth.md`
- Migrations : `Migrator` (up / down, désinstallation d'une extension), `php bin/console db:migrate`
- Admin : layout, menu, barre du haut, icônes, maintenance : `specs/admin.md`
- Site et organisations dans l'admin : page Site (`site_url` vérifiée contre l'adresse ouverte, `config site_url`), principale et secondaires : `specs/admin.md`
- Modules : activation depuis l'admin et en console, `data/modules.json`, modules verrouillés, dépendances, versions et fin de mise à jour, vidage du cache, désinstallation et corbeille des extensions : `specs/modules.md`

## À faire

1. **Utilisateurs** : comptes, rôles, utilisateur limité à sa mosquée
2. **Médias** : upload, liste blanche d'images
3. **News** : saisie dans l'admin, page `/news/{slug}`, Open Graph, bloc d'accueil
4. **Janaza** : saisie, annulation, archivage, bloc d'accueil
5. **Article** et catégories
6. **Cron** : une seule tâche chez l'hébergeur (`php bin/console cron`), tâches déclarées par les modules, rattrapage, verrou `flock`, alerte dans l'admin s'il ne tourne pas
7. **Communication** (module `Core/Communication`, `specs/subscription.md`) : `/feed` en PWA, abonnements push et mail (confirmation par jeton, désinscription), guide iOS, envois par le cron via Brevo, digest, abonnés et réglages dans l'admin
8. **Release** : archive avec les assets compilés
