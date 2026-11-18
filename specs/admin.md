# Admin

Module `Core/Admin`, verrouillé. Toutes les pages sous `/admin`, langue dans l'URL (`/admin/fr/…`), contrôleurs hérités d'`AbstractAdminController` (strict route mode, `no-store`, `noindex`). Accès et redirection vers `/login` : `specs/auth.md`.

## Layout

`admin/layout.twig` étend `main/layout.twig` ; chaque page admin l'étend et remplit `title` (titre de la page et `<title>`), `actions` (boutons à côté du titre : « Nouvelle annonce »…) et `main`. Le contexte `admin` (menu, nom du site, utilisateur, version, `dev`, `maintenance`) est ajouté par `AbstractAdminController::render()`.

- Ordinateur : menu latéral fixe, contenu à côté. Côté inversé en arabe par les propriétés CSS logiques et rtlcss
- Mobile : menu dans un offcanvas Bootstrap (`offcanvas-lg`), ouvert par un bouton de la barre du haut
- Messages flash (`main/partials/flash.twig`) : notifications en bas à droite (`specs/structure.md`)
- Formulaire de saisie (Site, organisation…) : dans une carte (`card shadow-sm`, `card-body p-4`) sur le fond `bg-body-tertiary` du contenu, largeur limitée (`col-lg-9 col-xl-7`), comme la connexion ; « Supprimer » dans la carte, à droite des boutons (formulaire à part, relié par l'attribut `form`)
- Bouton flottant du thème en bas, comme sur le front ; celui de la langue n'est pas affiché : la langue est dans la barre du haut

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
- Pour l'instant : Tableau de bord, Site, Organisation, Modules (`specs/modules.md`). Les autres entrées arrivent avec leurs modules
- Version de Yawasla en bas du menu

## Barre du haut

Écrite en dur dans `admin/layout.twig`.

- Nom du site, lien « Ouvrir le site » vers `/`
- Badge permanent « Mode dev » si `APP_ENV=dev` (l'admin ne voit pas forcément la barre de debug)
- Interrupteur « Maintenance », toujours visible, allumé quand le site l'est, et « Vider le cache » (`Yawasla\Core\Cache::clear()`, partagé avec `cache:clear`) : `SystemController`, `POST /admin/maintenance` et `/admin/cache`, admin uniquement, CSRF, flash
- Retour sur la page d'origine par le champ `back` : accepté seulement s'il commence par `/admin` (après le préfixe d'installation), sinon accueil de l'admin, et toujours via `redirect()`
- Langue (même page dans l'autre langue, via `locale_urls()`)
- Menu utilisateur : prénom, déconnexion (`POST /logout` avec CSRF)

## Maintenance

Fichier `data/maintenance` (`Yawasla\Core\Maintenance`) : sa présence met le site en maintenance (comme `data/installed` pour l'installation). Vaut pour toute l'installation, se lit sans base.

- Visiteurs : page de maintenance en 503 (`Retry-After`), comme site non installé ou base injoignable
- Aussi tant qu'une mise à jour du cœur n'est pas terminée, sans fichier `data/maintenance` : `specs/modules.md`, partie Version. `Maintenance::active()` couvre les deux cas (`bootstrap.php`, bandeau) ; `Maintenance::enabled()` ne sert qu'à l'interrupteur
- Restent ouverts (vérifié dans `bootstrap.php`) : `LoginController` (`/login` et ses pages, `/logout`), tout contrôleur `AbstractAdminController` ; un admin connecté voit le site normalement, avec un bandeau en haut des pages publiques : « Maintenance active : site non visible pour les visiteurs »
- Sur les pages publiques, seulement ce bandeau (`maintenance_banner()`, bloc `maintenance_banner` de `main/layout.twig`, vidé par les layouts d'admin, de connexion et d'erreur)
- Activation : interrupteur de la barre du haut, `php bin/console maintenance on|off`, ou fichier créé / supprimé par FTP si l'admin est cassé
- `data/` est refusé par son `.htaccess` et n'est pas vidé à la mise à jour

## Site et organisation

Deux pages de `Core/Admin`, `auth: 'admin'`, au menu (`globe`, `buildings`) :

| Méthode | Chemin | Nom |
|---|---|---|
| GET, POST | `/admin/site` | `admin_site` |
| GET | `/admin/organizations` | `admin_organizations` |
| GET, POST | `/admin/organizations/new` | `admin_organizations_new` |
| GET, POST | `/admin/organizations/{id}` | `admin_organizations_edit` |
| POST | `/admin/organizations/{id}/delete` | `admin_organizations_delete` |

- Une méthode par page comme `/login` : `Form::validate()`, puis flash et 303 (Site : même URL ; organisation : liste) ; erreurs réaffichées sur place
- Partagé avec l'install : règles (`Setting::FIELDS`, `Organization::FIELDS`), partials `main/partials/site-fields.twig` et `organization-fields.twig` (`full` pour tous les champs), `@main/ts/seo.ts`, traductions dans `Core/Main`

**Site** : `site_url`, `site_name`, `site_description` dans `config` (`Setting::get()` / `set()`), avec le guide SEO de l'install.
- `site_url` ne sert qu'aux liens des mails : fausse, elle bloque la connexion. Acceptée seulement si identique à l'adresse où l'admin est ouvert (`Host` sert à cette vérification, jamais aux liens)
- Secours : `php bin/console config site_url <url>`

**Organisations** : la principale et ses secondaires (`specs/database.md`).
- Liste : la principale, puis les secondaires par nom. Ajouter, modifier une secondaire ; la principale se modifie mais ne se supprime pas
- Supprimer une secondaire : bouton sur sa page, refusé (flash) si des news, janaza ou utilisateurs y sont rattachés ; la principale n'a pas le bouton et la suppression est aussi refusée côté serveur
- Champs : nom (requis, unique sans tenir compte de la casse : erreur sur le champ), identifiant libre « (SIREN, RNA, etc.) », email, téléphone, fax, adresse, code postal, ville, pays, site web. Champ vidé : `NULL`
- Pas d'affichage public pour l'instant

## Barre de debug

En bas de toutes les pages en `APP_ENV=dev`, admin compris. Lien « Admin » tout à droite (`path('admin')`, vers `/login` hors connexion). La barre du haut n'affiche jamais de détail technique (erreur, requête, trace) : ce rôle reste à la barre de debug.
