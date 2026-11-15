# Admin

Module `Core/Admin`, verrouillé. Toutes les pages sous `/admin`, langue dans l'URL (`/admin/fr/…`), contrôleurs hérités d'`AbstractAdminController` (strict route mode, `no-store`, `noindex`). Accès et redirection vers `/login` : `specs/auth.md`.

## Layout

`admin/layout.twig` étend `main/layout.twig` ; chaque page admin l'étend et remplit `title` (titre de la page et `<title>`), `actions` (boutons à côté du titre : « Nouvelle annonce »…) et `main`. Le contexte `admin` (menu, nom du site, utilisateur, version, `dev`, `maintenance`) est ajouté par `AbstractAdminController::render()`.

- Ordinateur : menu latéral fixe, contenu à côté. Côté inversé en arabe par les propriétés CSS logiques et rtlcss
- Mobile : menu dans un offcanvas Bootstrap (`offcanvas-lg`), ouvert par un bouton de la barre du haut
- Messages flash (`main/partials/flash.twig`) en haut du contenu
- Les boutons flottants de langue et de thème de `main/layout.twig` ne sont pas affichés : ils passent dans la barre du haut

## Menu latéral

Déclaré par les modules dans `module.json` (`specs/modules.md`, partie Format) :

```json
"admin": [
    {"route": "admin_news", "label": "News", "icon": "newspaper"}
]
```

- Assemblé depuis les modules actifs : une extension ajoute sa page sans toucher au cœur, un module désactivé disparaît du menu
- Une entrée n'apparaît que si l'utilisateur peut ouvrir sa route (`auth`) : jamais de lien vers une 403
- Entrée active : la route en cours ou ses sous-routes (`admin_news_edit` active `admin_news`), la plus précise l'emporte ; libellé traduit par `|trans`
- Pour l'instant : Tableau de bord, Modules (`specs/modules.md`). Les autres entrées arrivent avec leurs modules
- Version de Yawasla en bas du menu

## Barre du haut

Écrite en dur dans `admin/layout.twig`.

- Nom du site, lien « Ouvrir le site » vers `/`
- Badge permanent « Mode dev » si `APP_ENV=dev` (l'admin ne voit pas forcément la barre de debug)
- Interrupteur « Maintenance », toujours visible, allumé quand le site l'est, et « Vider le cache » (`Yawasla\Core\Cache::clear()`, partagé avec `cache:clear`) : `SystemController`, `POST /admin/maintenance` et `/admin/cache`, admin uniquement, CSRF, flash
- Retour sur la page d'origine par le champ `back` : accepté seulement s'il commence par `/admin` (après le préfixe d'installation), sinon accueil de l'admin, et toujours via `redirect()`
- Langue (même page dans l'autre langue, via `locale_urls()`) et thème
- Menu utilisateur : prénom, déconnexion (`POST /logout` avec CSRF)

## Maintenance

Fichier `data/maintenance` (`Yawasla\Core\Maintenance`) : sa présence met le site en maintenance (comme `data/installed` pour l'installation). Vaut pour toute l'installation, se lit sans base.

- Visiteurs : page de maintenance en 503 (`Retry-After`), comme site non installé ou base injoignable
- Aussi tant qu'une mise à jour du cœur n'est pas terminée, sans fichier `data/maintenance` : `specs/modules.md`, partie Version. `Maintenance::active()` couvre les deux cas (`bootstrap.php`, bandeau) ; `Maintenance::enabled()` ne sert qu'à l'interrupteur
- Restent ouverts (vérifié dans `bootstrap.php`) : `LoginController` (`/login` et ses pages, `/logout`), tout contrôleur `AbstractAdminController` ; un admin connecté voit le site normalement, avec un bandeau en haut des pages publiques : « Maintenance active : site non visible pour les visiteurs »
- Sur les pages publiques, seulement ce bandeau (`maintenance_banner()`, bloc `maintenance_banner` de `main/layout.twig`, vidé par les layouts d'admin, de connexion et d'erreur)
- Activation : interrupteur de la barre du haut, `php bin/console maintenance on|off`, ou fichier créé / supprimé par FTP si l'admin est cassé
- `data/` est refusé par son `.htaccess` et n'est pas vidé à la mise à jour

## Barre de debug

En bas de toutes les pages en `APP_ENV=dev`, admin compris. La barre du haut n'affiche jamais de détail technique (erreur, requête, trace) : ce rôle reste à la barre de debug.
