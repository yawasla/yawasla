# Modules

Tout le site est fait de modules, ceux du cœur compris : le noyau (`src/`) ne contient que le socle commun.

```
modules/Core/News/
├── module.json     version, requires, locked, admin, description : voir Format
├── src/            namespace Yawasla\Module\Core\News\ ; Controller/ (routes #[Route]), Block.php
├── templates/news/
├── translations/   fr.php, ar.php
├── migrations/     YYYYMMDD-XX.php (extensions uniquement ; le cœur met tout dans Core/Main/migrations)
└── assets/         ts/, scss/
```

- Nom du module = nom du dossier (`News`), son identifiant en minuscules (`news`) sert aux templates (`news/show.twig`) et aux assets (`@news/ts/…`). Noms uniques entre `Core/` et `Extension/`
- **Un module ne remplace jamais un module du cœur** : on désactive celui du cœur et on active le sien. Twig cherche d'abord dans le cœur, puis dans les extensions, puis dans `templates/`
- `Core/Main` (layout, partials, erreurs, debug, thème, Bootstrap, migrations du cœur) est verrouillé, comme `Auth`, `Install` et `Admin` (`specs/admin.md`) : on ne peut pas les désactiver
- Authentification en deux parties : le noyau (`src/Auth/`) porte ce dont le Router et les autres modules ont besoin (accès, mot de passe, jetons, session) et ne dépend d'aucun module ; `Core/Auth` porte les pages (connexion sous `/login`, lien, déconnexion, reset), leurs templates, traductions et mails. Détail : `specs/auth.md`
- Traductions : celles de chaque module sont fusionnées, celles du cœur priment
- Bloc : `{{ module('Banner') }}` rend `banner/block.twig` avec les données de `Block` (classe invocable qui renvoie un tableau), ou rien si le module est absent ou désactivé
- Accueil : `custom.twig` s'il existe, sinon `main.twig` ; tous deux étendent `index.twig`. `custom.twig` appartient à l'utilisateur, non versionné et jamais remplacé à la mise à jour
- Mise à jour : on remplace tout sauf `modules/Extension/`, `templates/custom.twig`, `data/`, `.env` et `public/uploads/`
- Les extensions s'installent en déposant leur dossier sur le serveur, jamais depuis l'admin : une extension est du PHP avec tous les droits

## Format

`module.json`, des données et pas du code : lu par `json_decode()`, jamais exécuté, même pour une extension inactive.

```json
{
  "version": "1.2.0",
  "requires": ["News"],
  "locked": false,
  "admin": [
    {"route": "admin_gallery", "label": "Gallery", "icon": "images"}
  ],
  "description": {"en": "Photo galleries", "fr": "Galeries photo", "ar": "معارض الصور"}
}
```

| Clé | Obligatoire | Règle |
|---|---|---|
| `version` | oui | chaîne `X.Y.Z`, comparée par `version_compare()` |
| `requires` | non | liste de noms de modules |
| `locked` | non | booléen, pris en compte pour le cœur seulement |
| `admin` | non | entrées du menu (`specs/admin.md`) : `route`, `label` (traduit), `icon` (même contrôle que `icon()`), toutes en chaînes |
| `description` | non | objet par langue (`Translator::LOCALES`), `en` obligatoire, chaînes de 255 caractères au plus. Page des modules : langue de l'interface, sinon `en`. Dans `module.json` et pas dans les traductions : celles d'une extension inactive (du PHP) ne sont pas chargées |

- Vérifié à la lecture par `Modules` : JSON invalide, objet attendu, type de chaque clé, clé inconnue (une faute de frappe comme `require` serait sinon ignorée) → mal formé. Cœur : `LogicException` ; extension : inactive avec un warning (Dépendances)

## Activation

Fichier `data/modules.json` : pour chaque module installé, sa version installée et, si l'admin a changé son activation, son état.

```json
{
  "Main":    {"version": "1.0.0"},
  "News":    {"version": "1.0.0", "active": false},
  "Gallery": {"version": "2.1.0", "active": true}
}
```

Sans `active`, un module garde son état par défaut : actif pour le cœur, inactif pour une extension. Une extension déposée sur le serveur ne tourne donc qu'une fois activée par l'admin. Comme `data/maintenance` : vaut pour toute l'installation, se lit sans base, n'est pas vidé à la mise à jour.

- Une extension qui casse le site ou l'admin se désactive en éditant le fichier par FTP : la liste compilée garde la date et la taille de `data/modules.json`, une différence vide `data/cache/` et recompile
- Lecture : seuls les noms de modules présents sur le disque, un `active` booléen et une `version` en chaîne sont retenus ; un module verrouillé est toujours actif, quoi que dise le fichier
- Écriture atomique : fichier temporaire puis `rename()`
- Liste compilée dans `data/cache/` comme les routes (modules trouvés, `module.json` lus, désactivés retirés), recompilée à chaque requête en dev. Changer l'activation vide `data/cache/`
- Fichier absent : état par défaut pour tous (cœur actif, extensions inactives)

## Dépendances

`requires` dans `module.json` : les modules dont celui-ci a besoin.

- Activer un module dont un module requis est inactif : refusé, le message liste les modules à activer d'abord
- Désactiver un module requis par un module actif : refusé, le message liste les modules qui en dépendent
- État incohérent au chargement (fichier édité par FTP, `requires` ajouté par une mise à jour, extension requise supprimée du disque) : le site ne plante jamais. À la compilation, un module dont un module requis est inactif ou absent est traité comme inactif, en cascade, avec un `trigger_error(E_USER_WARNING)` et un signalement sur la page des modules. `data/modules.json` n'est pas réécrit : le module repart dès que le module requis revient
- Erreur dans un module du cœur (notre code, vue en dev avant toute release) : `LogicException` à la compilation. Un module du cœur ne requiert pas d'extension ; un module verrouillé ne requiert que des modules verrouillés (sinon l'état incohérent le rendrait inactif, l'admin compris)
- Erreur dans une extension (code tiers déposé par FTP) : extension inactive avec un `trigger_error(E_USER_WARNING)`, jamais de plantage du site. Cycle (A requiert B qui requiert A, ou A lui-même : cherché explicitement, le retrait en cascade ne le voit pas) ; `module.json` mal formé (voir Format)
- Ces warnings arrivent dans le panneau d'erreurs de la barre de debug (liste recompilée à chaque requête en dev) et dans `php.log` partout (une fois par compilation hors dev)
- Onglet « Modules » de la barre de debug : chaque module de la liste compilée avec son état (actif / inactif) et sa raison (verrouillé, désactivé par l'admin, extension non activée, requiert X, cycle, `module.json` mal formé). Même information que la page des modules de l'admin

## Activer, désactiver

Depuis la page des modules de l'admin : POST avec CSRF, admin uniquement.

- Activer : vérification des `requires`, puis migrations en attente du module (`Migrator`, verrou `flock`) **avant** d'écrire `data/modules.json`. Une migration échoue : le module reste inactif, message traduit sans détail technique (le détail dans `php.log`). Puis écriture du fichier et vidage de `data/cache/`
- Désactiver : vérification des dépendances, écriture du fichier, vidage de `data/cache/`. Tables et données gardées

Console, même déroulé et mêmes vérifications que l'admin :
- `php bin/console module list` : chaque module, son état et sa raison (comme l'onglet de debug)
- `php bin/console module enable|disable <Module>`
- Nom cherché parmi les modules connus, jamais utilisé comme chemin (comme `db:migrate --create`)

## Version

`version` de `module.json` comparée à celle de `data/modules.json` à la compilation de la liste (`data/cache/` est supprimé à chaque mise à jour) : sans base, le site sait qu'une mise à jour est déposée mais pas terminée.

- Mise à jour terminée à la connexion d'un admin (une fois le lien du mail validé, `System::tasks()`, qui purge aussi la corbeille ; le futur cron l'appellera aussi) : migrations en attente des modules actifs et des extensions voulues en attente de mise à jour dont les modules requis sont aussi migrés (`Migrator`, verrou `flock`), puis écriture de la nouvelle version. Un `module.json` invalide n'est jamais en attente de mise à jour
- La version n'est écrite qu'une fois toutes ses migrations réussies ; `db:migrate` l'écrit aussi, sinon le fichier et la table `migration` divergent
- Tant que la mise à jour n'est pas terminée (nouveau code sur l'ancien schéma) :
  - extension : traitée comme inactive, sans réécrire `data/modules.json`, raison « mise à jour à terminer » (warning, onglet Modules) ; elle repart seule une fois ses migrations jouées
  - cœur : ses modules verrouillés ne peuvent pas être désactivés et toutes ses migrations sont dans `Core/Main` → site en maintenance (503) pour les visiteurs, comme `data/maintenance` ; `/login` et l'admin restent ouverts pour terminer la mise à jour
- Version des fichiers plus ancienne que celle installée : même traitement (cœur en maintenance, extension inactive), raison « version plus ancienne que celle installée ». Rien n'est joué. Une extension désactivée puis réactivée par l'admin enregistre la version de ses fichiers, même plus ancienne
- Install : écrit la version de chaque module. Fichier absent ou module sans version : traité comme une mise à jour à terminer

## Page d'admin

`ModulesController` dans `Core/Admin` (verrouillé : la page ne disparaît jamais), entrée de menu dans `Core/Admin/module.json` (`{"route": "admin_modules", "label": "Modules", "icon": "puzzle-piece"}`). `auth: 'admin'`.

| Méthode | Chemin | Nom | Rôle |
|---|---|---|---|
| GET | `/admin/modules` | `admin_modules` | liste |
| POST | `/admin/modules/{module}/enable` | `admin_modules_enable` | activer |
| POST | `/admin/modules/{module}/disable` | `admin_modules_disable` | désactiver |
| GET | `/admin/modules/{module}/uninstall` | `admin_modules_uninstall` | page de confirmation |
| POST | `/admin/modules/{module}/uninstall` | `admin_modules_uninstall_confirm` | désinstaller |
| POST | `/admin/modules/update` | `admin_modules_update` | terminer la mise à jour |
| GET | `/admin/modules/trash` | `admin_modules_trash` | corbeille |
| POST | `/admin/modules/trash/{entry}/restore` | `admin_modules_restore` | restaurer depuis la corbeille |

- `{module}` cherché parmi les modules connus, 404 sinon, jamais utilisé comme chemin
- Chaque POST : `Form::validToken()`, puis 303 vers la liste avec un flash (succès, ou refus listant les modules en cause : « Activez d'abord : News »)
- Désinstallation confirmée sur une page à part : ce qui sera supprimé (données et dossier)
- Liste : cœur puis extensions, triés par nom, en `list-group` (lisible sur mobile). Par module : nom, badge Yawasla (cœur, non traduit) / Extension, état, version (et version installée si différente), description, « Dépend de : … » et « Requis par : … » (modules présents, actifs ou non), chacune si non vide, et la raison seulement si c'est un problème à régler (mise à jour, version plus ancienne, `module.json` invalide, cycle)
- Actions : module verrouillé → cadenas, aucune action ; sinon Activer / Désactiver ; extension inactive → Désinstaller en plus
- La liste recompile (`Modules::discover()`) : une extension tout juste déposée y apparaît sans vider le cache
- Bouton « Corbeille » en haut à droite (bloc `actions`), avec le nombre de dossiers, affiché si elle n'est pas vide. Page de la corbeille : par dossier, nom du module, date de désinstallation, jours avant suppression, bouton Restaurer ; mention « Les données ne sont pas restaurées ». Plusieurs dossiers du même module : chacun avec sa date
- « Terminer la mise à jour » affiché seulement si une mise à jour est en attente : même déroulé qu'à la connexion (un admin déjà connecté ne se reconnecte pas)

## Désinstallation

Extensions uniquement. Un module du cœur se désactive seulement (sauf s'il est verrouillé) : ses tables sont dans `Core/Main`. Supprime les tables et le dossier de l'extension ; pour la réinstaller, on redépose son dossier.

1. Vérifications : extension, inactive, aucun module n'en dépend, dossier déplaçable, `modules/Extension/` et `data/` sur le même disque (`rename()` échoue sinon). Sinon refus avant de toucher à quoi que ce soit, tables comprises
2. Confirmation
3. `Migrator::uninstall()` : `down()` dans l'ordre inverse, lignes de `migration` supprimées
4. Retrait de `data/modules.json` (inactive par défaut si le dossier revient)
5. Dossier déplacé par `rename()` dans `data/trash/` : atomique, l'extension disparaît d'un coup
6. Vidage de `data/cache/`

Corbeille `data/trash/` : un dossier y est supprimé définitivement après 7 jours, vérifié à chaque connexion d'un admin (`System::tasks()`), ou tout de suite par le bouton « Vider le cache » et `cache:clear` (`Trash::clear()`). Dossier nommé avec sa date de mise à la corbeille (`Gallery-20261115-153000`) : `rename()` garde la date de modification des fichiers, et deux désinstallations de la même extension ne se percutent pas. Échec de suppression : warning, le reste ne gêne pas (`data/` refusé par son `.htaccess`, jamais chargé).

Restauration : `rename()` du dossier de `data/trash/` vers `modules/Extension/<Nom>`. Extension inactive, comme tout juste déposée ; ses tables ont été supprimées, l'activation les recrée vides.
- `{entry}` au format `Nom-AAAAMMJJ-HHMMSS` (plus `-xxxx` si deux désinstallations tombent la même seconde), cherché dans la liste réelle de la corbeille, jamais utilisé comme chemin ; un lien symbolique n'est jamais restauré
- Refus : dossier du même nom déjà dans `modules/Extension/`, nom d'un module du cœur, dossier non déplaçable ou sur un autre disque

Suppression récursive :
- Chemin jamais construit depuis la requête : module cherché dans la liste compilée, son dossier connu
- `realpath()` strictement dans `modules/Extension/` (puis dans `data/trash/` pour la purge)
- Lien symbolique jamais suivi : `unlink()` du lien, sinon un lien vers `/` ou `data/` ferait supprimer sa cible
