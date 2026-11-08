# Installation

## Module

- Module du cœur `modules/Core/Install`, verrouillé comme `Main` : on ne peut pas le désactiver
- Route `/install`, hors de `/admin`

## État de l'installation

Le site est installé si `data/installed` existe, ou si la table `user` contient au moins une ligne.

| `data/installed` | Base | Résultat |
|---|---|---|
| présent | sans objet pour `/install` | installé : `/install` répond 404 |
| absent | `user` contient au moins une ligne | fichier recréé, installé : `/install` répond 404 |
| absent | connexion OK, `user` absente ou vide | non installé : `/install` ouvert, le reste en 503 |
| absent | injoignable | rien n'est recréé : maintenance en 503, `/install` s'arrête aux vérifications |

- `data/installed` protège un site installé dont le `.env` serait perdu ou erroné : `Connection` retomberait sur une base SQLite vide, et le site paraîtrait non installé
- La base n'est interrogée que si le fichier manque
- `data/installed` n'est recréé que sur une réponse positive de la base, jamais sur une erreur de connexion
- SQLite : la base est dans `data/`, supprimer `data/` efface le site, qui redevient non installé

## Lancement

Uniquement sur la route `/install` : rien n'y redirige. Pas de clé : le premier qui y va installe.

`.env` est facultatif avec SQLite : sans lui, les valeurs par défaut s'appliquent. Il est nécessaire pour MySQL / MariaDB.

## Base MySQL absente

Si la base nommée dans `.env` n'existe pas, l'étape 0 l'indique, donne l'encodage à utiliser (`utf8mb4` / `utf8mb4_unicode_ci`) et propose un bouton pour la créer. Seul le nom du `.env` est utilisé, jamais une saisie. Si l'utilisateur SQL n'a pas le droit de la créer, un message renvoie vers l'hébergeur.

## Étapes

0. **Vérifications**, étape cachée : PHP 8.1+, extensions (`pdo_sqlite` ou `pdo_mysql`, `mbstring`, `openssl`), SQLite 3.26+, `data/` accessible en écriture, HTTPS, connexion à la base. Une erreur par problème, et pas d'accès à l'étape 1 tant qu'il en reste. Les erreurs restent générales (« Connexion à la base impossible : vérifier `.env` ») : `/install` est ouvert à tous, le détail (hôte, utilisateur, message de PHP) va seulement dans `data/logs/php.log`
1. **Site Internet** : domaine (liens des mails), nom et description du site (`<title>`, `<meta name="description">`). Étape qui guide le SEO : conseil sous chaque champ, compteur de caractères (nom ~60, description ~160), aperçu du résultat dans Google
2. **Organisation** : nom, prérempli avec le nom du site tant qu'il est vide, et adresse facultative (adresse, code postal, ville, pays) ; le reste se complète dans l'admin
3. **Administrateur** : email, prénom, nom, mot de passe saisi deux fois (7 caractères minimum, dont au moins un chiffre et un symbole)
4. **Confirmation** : récapitulatif des saisies
5. **Validation** : lien vers l'admin, dans la langue du site (404 tant que l'admin n'existe pas)

Vérifications :
- PHP trop ancien ou `data/` non inscriptible peuvent planter avant Twig : ces deux tests s'affichent en HTML brut
- HTTPS n'est pas exigé si `APP_ENV=dev`, et le cookie de session n'y est pas `Secure` (`bin/console serve` est en HTTP)

Saisies :
- `site_url` : `https` uniquement (`http` accepté si `APP_ENV=dev`), sous-dossier éventuel inclus, sans slash final
- Email normalisé en minuscules
- `user.roles` : `["admin"]` en JSON
- Mot de passe : règles et hachage dans une classe du noyau, `src/Auth/Password.php`, réutilisée par l'authentification et le reset

## Enregistrement

Rien n'est écrit avant la confirmation : les saisies restent en session, le mot de passe uniquement sous forme de `password_hash()`, calculé dès l'étape 3. Un navigateur fermé en cours de route ne laisse rien en base.

À la confirmation :
1. Migrations
2. Site, organisation et administrateur dans une seule transaction. Site dans `config` : `site_url`, `site_name`, `site_description`
3. Écriture de `data/installed`

En cas d'échec, un message d'erreur général avertit l'utilisateur, le détail va dans `data/logs/php.log` : c'est un développeur qui installe, il sait quoi faire.

Base partagée en mutualisé : `DB_PREFIX` dans `.env` (`Connection::prefix()`, voir `migration.md`). Si des tables du même nom existent déjà, l'installation échoue de la même façon.

## Session et CSRF

L'Install a besoin d'une session et d'un jeton CSRF sur chaque formulaire, avant l'authentification : version minimale, réutilisée ensuite par celle-ci.

- `src/Http/Session.php` : session PHP native, fichiers dans `data/sessions`, cookie `Secure`, `HttpOnly`, `SameSite=Lax`
- `src/Form/Form.php` : vérifie le CSRF, lit uniquement les champs déclarés, valide (requis, longueur maximale, email, URL `https`, ou `http` si `APP_ENV=dev`), garde les erreurs par champ et les valeurs saisies
- Le HTML reste écrit dans Twig : fonction `csrf()` pour le champ caché, partial pour un champ et son erreur
- Effets de lumière : `@shader-ui/core` (npm, compilé par Vite, version figée), activé par `data-shader-ui="form"` sur les formulaires de l'Install ; l'état vient du HTML (`required`, `aria-invalid`)

## Page de maintenance

Site non installé ou base injoignable : toutes les routes sauf `/install` affichent la page de maintenance, en 503. Elle ne dépend pas de la base et ne mentionne jamais `/install`. Une base qui tombe après l'installation mène à cette page, pas à une 500.
