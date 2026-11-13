# Roadmap

Ce qui est décidé, dans l'ordre. Une idée de `ideas.md` n'arrive ici qu'une fois validée.

## Fait

- Socle : Router (routes en attributs, compilées), Twig, i18n fr / en / ar avec RTL, barre de debug, en-têtes de sécurité
- Assets : Vite, TypeScript, Bootstrap compilé depuis npm, noms hashés
- Modules : `modules/Core` et `modules/Extension`, routes, templates, traductions, assets et blocs par module, `custom.twig`
- Schéma de la base : `specs/database.md`, migration `Core/Main/migrations/20261031-01.php`
- Install : `/install` (vérifications, site, organisation, premier utilisateur), fermé une fois l'installation terminée ; `Migrator` traducteur vers SQLite, `db:migrate`, changement de serveur `db:migrate --server` : `specs/install.md`, `specs/migration.md`
- Authentification : accès dans `#[Route(auth:)]`, paramètres de route typés, module `Core/Auth` (connexion en deux temps, jetons, limitation, session 24 h, déconnexion, reset) : `specs/auth.md`
- Migrations : `Migrator` (up / down, désinstallation d'une extension), `php bin/console db:migrate`

## À faire

1. **Admin** : layout, menu, routes `/admin/{locale}`
2. **Activation des modules** depuis l'admin : table `module`, modules verrouillés, dépendances, vidage du cache, désinstallation des extensions
3. **Organisation et config** dans l'admin, mode maintenance
4. **Médias** : upload, liste blanche d'images
5. **News** : saisie dans l'admin, page `/news/{slug}`, Open Graph, bloc d'accueil
6. **Janaza** : saisie, annulation, archivage, bloc d'accueil
7. **Article** et catégories
8. **Abonnement mail** : inscription confirmée par jeton, désinscription, envoi via Brevo, digest
9. **Feed** : PWA `/feed/`, web push pour les janaza, guide iOS
10. **Release** : archive avec les assets compilés
