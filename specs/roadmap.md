# Roadmap

Ce qui est décidé, dans l'ordre. Une idée de `ideas.md` n'arrive ici qu'une fois validée.

## Fait

- Socle : Router (routes en attributs, compilées), Twig, i18n fr / en / ar avec RTL, barre de debug, en-têtes de sécurité
- Assets : Vite, TypeScript, Bootstrap compilé depuis npm, noms hashés
- Modules : `modules/Core` et `modules/Extension`, routes, templates, traductions, assets et blocs par module, `custom.twig`
- Schéma de la base : `specs/database.md`, migration `Core/Main/migrations/20261031-01.php`
- Migrations : `Migrator` (up / down, désinstallation d'une extension), `php bin/console migrate`

## À faire

1. **Install** : création de la base, de l'organisation et du premier utilisateur ; se ferme une fois l'installation terminée
2. **Authentification** : connexion en deux temps, jetons, limitation par IP, sessions, CSRF
3. **Admin** : layout, menu, routes `/admin/{locale}`
4. **Activation des modules** depuis l'admin : table `module`, modules verrouillés, dépendances, vidage du cache, désinstallation des extensions
5. **Organisation et config** dans l'admin, mode maintenance
6. **Médias** : upload, liste blanche d'images
7. **News** : saisie dans l'admin, page `/news/{slug}`, Open Graph, bloc d'accueil
8. **Janaza** : saisie, annulation, archivage, bloc d'accueil
9. **Article** et catégories
10. **Abonnement mail** : inscription confirmée par jeton, désinscription, envoi via Brevo, digest
11. **Feed** : PWA `/feed/`, web push pour les janaza, guide iOS
12. **Release** : archive avec les assets compilés
