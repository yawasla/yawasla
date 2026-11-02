# Base de données

Schéma créé par `modules/Core/Main/migrations/20261031-01.php`, pour SQLite et MySQL / MariaDB. Toutes les tables du cœur sont dans les migrations de `Core/Main` ; une extension a les siennes dans son dossier `migrations/`.

## Tables

| Table | Rôle |
|---|---|
| `organization` | l'association ou la mosquée : identité, coordonnées, `in_maintenance` |
| `user` | compte de l'admin, rattaché à une organisation |
| `token` | jeton à usage unique : lien de connexion, reset, confirmation d'abonnement… |
| `publication` | ce qui est publié, quel que soit son type : auteur, slug, nom, dates |
| `news`, `janaza`, `article` | contenu propre à chaque type de publication |
| `media` | fichier rattaché à une publication |
| `category` | catégories des articles |
| `email_subscription` | abonnés par mail, avec leurs choix (news, janaza, digest) |
| `push_subscription` | abonnements web push, pour les janaza uniquement |
| `config` | réglages clé / valeur |
| `module` | modules connus et leur activation |
| `migration` | migrations déjà jouées, par module |

## Choix

**Publications** : `publication` porte ce qui est commun à tous les types (slug, auteur, publication) ; `news`, `janaza` et `article` portent le contenu, liés par `publication_id`. Une liste ou le fil n'interroge que `publication`.
- `publication_id` n'est volontairement pas unique dans les tables enfants : plus tard, une publication pourra regrouper plusieurs contenus (un article et plusieurs médias…)
- Le type est dans `publication.type`

**Organisations** : un utilisateur appartient à une seule organisation et n'en change jamais ; on ne peut que le désactiver. L'organisation d'une publication est celle de son auteur. Prépare le multi-organisation sans le mettre en place.

**Emails uniques sur toute l'installation** : une même adresse ne peut pas exister dans deux organisations (`user.email` et `email_subscription.email` uniques). L'email est normalisé en minuscules avant écriture : `UNIQUE` est insensible à la casse en MySQL, sensible en SQLite.

**Abonnements** : le push ne sert qu'aux janaza ; pour les news et le digest, on s'abonne par mail. Les abonnements ne sont pas rattachés à une organisation.

**Digest** : `digest_included` dit qu'un contenu doit figurer dans le prochain digest, `digest_sent_at` qu'il y est parti. Un contenu n'est jamais envoyé deux fois.

**Jetons** : seul le hash est stocké (`token_hash`), jamais le jeton. Un jeton est valable s'il n'a pas expiré (`expired_at`) et n'a pas servi (`used_at` NULL). Il appartient soit à un utilisateur, soit à un abonné, selon `type`.

**Booléens et dates** : `is_published` / `published_at` et `is_activated` / `deactivated_at` coexistent volontairement ; le code met toujours les deux à jour ensemble.

**Migrations** : `migration` enregistre chaque migration jouée par `(module, name)`, unique.

## Migrations

Écriture, `Migrator`, traduction vers SQLite et changement de serveur : `specs/migration.md`. Lancement : `php bin/console db:migrate` ; plus tard `Install` et l'activation d'un module.

## Conventions

- Tables au singulier, colonnes en `snake_case`, dates en `DATETIME`, booléens en `BOOLEAN`
- MySQL : `utf8mb4` / `utf8mb4_unicode_ci` sur chaque table, ajouté par le `MySQLAdapter` s'il manque
- Une clé étrangère a exactement le type de l'id référencé (`INT UNSIGNED`), sinon MySQL refuse la table
- Index sur chaque clé étrangère : MySQL les crée seul, le `Migrator` les ajoute en SQLite s'ils manquent
- SQLite : `PRAGMA foreign_keys = ON` à chaque connexion (fait par `Connection`)
