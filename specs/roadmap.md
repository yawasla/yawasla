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

## À faire

1. **Activation des modules** depuis l'admin : `data/modules.json`, modules verrouillés, dépendances, vidage du cache, désinstallation des extensions
2. **Organisation et config** dans l'admin
3. **Médias** : upload, liste blanche d'images
4. **News** : saisie dans l'admin, page `/news/{slug}`, Open Graph, bloc d'accueil
5. **Janaza** : saisie, annulation, archivage, bloc d'accueil
6. **Article** et catégories
7. **Abonnement mail** : inscription confirmée par jeton, désinscription, envoi via Brevo, digest
8. **Feed** : PWA `/feed/`, web push pour les janaza, guide iOS
9. **Release** : archive avec les assets compilés
