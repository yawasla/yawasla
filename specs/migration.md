# Migrations

Une migration s'écrit une seule fois, en SQL MySQL simple. Le `Migrator` l'exécute telle quelle sur MySQL / MariaDB et la traduit pour SQLite : les mêmes fichiers servent sur les deux bases, et permettent de changer de serveur SQL sur demande.

## Écrire une migration

- Fichiers `migrations/YYYYMMDD-XX.php` dans chaque module (`XX` : numéro sur deux chiffres dans la journée), triés par nom. Un autre nom est refusé. Création : `php bin/console db:migrate --create <Module>`
- Le cœur met toutes ses tables dans `Core/Main/migrations` (verrouillé, livré en un bloc). Chaque extension garde les siennes dans son dossier : elle s'installe et se désinstalle seule
- Une migration renvoie une classe anonyme qui étend `Yawasla\Database\Migration` et définit `up()` et `down()`. Chaque requête passe par `$this->sql()`, une requête par appel
- La migration ne connaît pas la base : pas de `$driver`, pas de condition

```php
return new class extends Migration {
    public function up(): void
    {
        $this->sql('CREATE TABLE category (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            parent_id INT UNSIGNED NULL,
            CONSTRAINT category_parent_id_fk FOREIGN KEY (parent_id) REFERENCES category (id)
        )');
        $this->sql('CREATE INDEX category_name ON category (name)');
    }

    public function down(): void
    {
        $this->sql('DROP TABLE category');
    }
};
```

Conventions :
- SQL MySQL simple : ce qui sort des règles ci-dessous est refusé en SQLite
- Id déclaré en ligne : `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, jamais `PRIMARY KEY (id)` à part
- Index nommés `table_colonne` : en SQLite, un nom d'index est unique dans toute la base, en MySQL par table
- Clés étrangères nommées `CONSTRAINT table_colonne_fk` : MySQL exige le nom pour `DROP FOREIGN KEY`, sinon il en invente un (`table_ibfk_1`)
- Charset inutile : ajouté par le `Migrator` en MySQL (règle 20)

## Classe `Migration`

`src/Database/Migration.php`, abstraite :
- `up()` et `down()` : à écrire
- `sql(string $query)` : ajoute une requête à la liste
- `queries()` : la liste, lue par le `Migrator`

Un objet neuf par migration (`require` du fichier) : aucune requête ne passe d'une migration à l'autre, rien à réinitialiser. Ni exécution ni nettoyage dans le constructeur ou le destructeur : le destructeur s'exécute à un moment qu'on ne choisit pas, y compris après une erreur.

## `Migrator`

- Verrou `flock()` non bloquant sur `data/migration.lock` pendant toute l'exécution : deux migrations lancées en même temps, la seconde s'arrête avec un message. Fonctionne sur les deux bases et en mutualisé (`LOCK TABLES` valide la transaction en cours en MySQL et n'existe pas en SQLite)
- Ordre : `Core/Main`, puis les autres modules actifs du cœur, puis les extensions ; un module passe toujours après ceux qu'il requiert (`requires`). Chaque migration jouée est enregistrée dans `migration` par `(module, name)`. La table `migration` est créée par la première migration de `Core/Main` : tant qu'elle n'existe pas, tout est à jouer
- Pour chaque migration : `up()` (ou `down()`), traduction des requêtes si SQLite, exécution dans une transaction, enregistrement, arrêt à la première erreur
- MySQL valide seul les `CREATE` / `ALTER` : une migration à moitié jouée se corrige à la main. SQLite annule tout
- Jamais plusieurs requêtes dans un seul `sql()` : MySQL ne remonterait que l'erreur de la première, et le multi-requêtes est désactivé dans `Database` (il aggrave les injections SQL)

Déroulé d'une migration en SQLite :
1. `PRAGMA foreign_keys = OFF`, **avant** `BEGIN` : dans une transaction, ce PRAGMA est ignoré sans erreur, et le `DROP TABLE` d'une recréation supprime alors les lignes enfants en `ON DELETE CASCADE` (vérifié : Phinx ouvre sa transaction avant le PRAGMA)
2. `BEGIN`
3. Requêtes traduites, recréations de table comprises
4. Index manquants sur les clés étrangères (règle 21)
5. Enregistrement dans `migration`
6. `PRAGMA foreign_key_check` : une violation annule tout
7. `COMMIT`, puis `PRAGMA foreign_keys = ON`

**Désinstaller** une extension (depuis le tableau de bord des modules, à venir) : `Migrator::uninstall()` joue ses `down()` dans l'ordre inverse, supprime ses lignes de `migration` et la désactive. Refusé pour le cœur (ses tables sont dans `Core/Main`, verrouillé) et si un autre module en dépend. **Désactiver** un module garde ses données.

## Règles

Avant d'appliquer les règles, les chaînes `'…'` et `"…"` sont mises de côté puis remises à la fin : une règle ne touche jamais l'intérieur d'une chaîne (`DEFAULT 'KEY'`…). En SQLite, elles sont remises au format SQLite : échappements MySQL décodés (`\'`, `\n`…), `'` doublé, `"…"` devient `'…'` (une chaîne en MySQL, un nom de colonne en SQLite).

### Traduction vers SQLite

| # | Écrit (MySQL) | En SQLite | Pourquoi |
|---|---|---|---|
| 1 | `[TINY\|SMALL\|MEDIUM\|BIG]INT[(n)] [UNSIGNED] AUTO_INCREMENT PRIMARY KEY` | `INTEGER PRIMARY KEY AUTOINCREMENT` | **critique** : SQLite accepte la forme MySQL sans erreur, mais les ids insérés valent `NULL`. Seul `INTEGER` exact donne l'id auto ; `AUTOINCREMENT` ne réutilise jamais l'id d'une ligne supprimée, comme MySQL |
| 2 | `UNSIGNED` | retiré | `INT(11) UNSIGNED`, `DECIMAL(10,2) UNSIGNED` : erreur de syntaxe |
| 3 | `JSON` | `TEXT` | sinon SQLite peut transformer `"123"` en nombre |
| 4 | `col ENUM('a', 'b')` | `col VARCHAR CHECK (col IN ('a', 'b'))` | `ENUM` n'existe pas |
| 5 | `ENGINE=`, `[DEFAULT] CHARSET=`, `CHARACTER SET=`, `COLLATE=`, `AUTO_INCREMENT=`, `COMMENT=` après la `)` de la table | retirés | erreur de syntaxe |
| 6 | `CHARACTER SET …`, `COLLATE …` sur une colonne | retirés | SQLite ne connaît pas `utf8mb4_*` |
| 7 | `COMMENT '…'` sur une colonne | retiré | erreur de syntaxe |
| 8 | `INDEX nom (cols)`, `KEY nom (cols)` dans un `CREATE TABLE` | retiré, puis `CREATE INDEX nom ON t (cols)` après la table ; sans nom : `table_colonne` | interdit dans un `CREATE TABLE` |
| 9 | `UNIQUE KEY nom (cols)`, `UNIQUE INDEX nom (cols)` dans un `CREATE TABLE` | retiré, puis `CREATE UNIQUE INDEX nom ON t (cols)` après la table | idem |
| 10 | `ALTER TABLE t ADD [UNIQUE] INDEX\|KEY nom (cols)` | `CREATE [UNIQUE] INDEX nom ON t (cols)` | pas d'`ADD INDEX` |
| 11 | `DROP INDEX nom ON t`, `ALTER TABLE t DROP INDEX nom` | `DROP INDEX nom` | syntaxe différente |
| — | longueur de préfixe `col(100)` et `USING BTREE` dans un index | retirés | n'existent pas |
| 12 | `ADD COLUMN … AFTER col`, `FIRST` | `AFTER` / `FIRST` retiré | la colonne va toujours à la fin |
| 13 | `RENAME TABLE a TO b` | `ALTER TABLE a RENAME TO b` | syntaxe différente |
| 14 | `TRUNCATE TABLE t` | `DELETE FROM t` | n'existe pas |
| 15 | `INSERT IGNORE` | `INSERT OR IGNORE` | syntaxe différente |

Passent tels quels : `VARCHAR(n)`, `TEXT`, `MEDIUMTEXT`, `LONGTEXT`, `TINYINT(1)`, `BOOLEAN`, `DATETIME[(n)]`, `DECIMAL(p,s)`, `CHECK`, `FOREIGN KEY … REFERENCES … ON DELETE / ON UPDATE`, `ADD COLUMN`, `RENAME COLUMN` (seuls dans leur `ALTER TABLE`), `CREATE INDEX`, `DROP TABLE`.

### Recréation de table en SQLite

SQLite ne sait pas modifier une colonne ni une contrainte : le `Migrator` recrée la table, selon la procédure officielle de SQLite (« Making Other Kinds Of Table Schema Changes »).

| # | Écrit (MySQL) |
|---|---|
| 16 | `ALTER TABLE … MODIFY`, `CHANGE`, `ALTER COLUMN … SET DEFAULT / DROP DEFAULT` |
| 17 | `ADD` / `DROP FOREIGN KEY`, `ADD CONSTRAINT`, `ADD` / `DROP PRIMARY KEY` |
| 18 | `ADD COLUMN` que SQLite refuse : `NOT NULL` sans `DEFAULT`, `DEFAULT` non constant (`CURRENT_TIMESTAMP`), `UNIQUE`, `REFERENCES` avec un `DEFAULT` non `NULL` |
| 19 | plusieurs opérations dans un même `ALTER TABLE` : une seule recréation pour toutes |
| — | `DROP COLUMN` : le `DROP COLUMN` natif échoue sur une colonne indexée, MySQL supprime l'index avec. Une colonne utilisée par une contrainte (clé étrangère, `UNIQUE`, `CHECK`…) est refusée : supprimer la contrainte d'abord |

Procédure, dans la transaction de la migration (clés étrangères déjà désactivées) :
1. Lire le `CREATE TABLE` de `t`, ses index et ses triggers dans `sqlite_master`
2. Découper le `CREATE TABLE` en éléments de premier niveau (colonnes, contraintes) : virgules hors parenthèses et hors chaînes. Remplacer, retirer ou ajouter un élément entier, jamais de regex de remplacement sur toute la requête (fragile chez Phinx). Garde les `CHECK`, que `PRAGMA table_info` perdrait
3. `CREATE TABLE t_new` avec la nouvelle définition
4. `INSERT INTO t_new (…) SELECT … FROM t`, colonnes renommées et supprimées comprises
5. `DROP TABLE t`, puis `ALTER TABLE t_new RENAME TO t`
6. Recréer les index et les triggers : ceux d'une colonne supprimée sont retirés, ceux d'une colonne renommée réécrits

Un échec annule toute la migration : la table reste intacte. Par exemple `ADD COLUMN … NOT NULL` sans `DEFAULT` sur une table non vide, que MySQL accepte en remplissant `''` ou `0` : écrire un `DEFAULT`.

### Ajouts automatiques

| # | Base | Règle |
|---|---|---|
| 20 | MySQL | `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` ajouté à un `CREATE TABLE` qui n'a ni `CHARSET`, ni `CHARACTER SET`, ni `COLLATE`. Si le dev en a écrit un, on ne touche à rien. Seule règle appliquée en MySQL : l'hébergeur crée souvent la base dans son propre jeu de caractères, parfois `latin1`, et l'arabe et les emojis seraient perdus sans erreur |
| 21 | SQLite | après les requêtes de la migration, pour chaque clé étrangère des tables touchées : si aucun index ne commence par sa colonne, `CREATE INDEX table_colonne`. MySQL fait de même seul. Si le nom est déjà pris, rien n'est créé : seulement plus lent, jamais faux |

### Refus

Message clair plutôt qu'une traduction approximative qui passerait en silence.

| # | Construction | Base |
|---|---|---|
| 22 | `SET(…)`, `ON UPDATE CURRENT_TIMESTAMP` (les dates sont écrites par le code), `FULLTEXT`, `SPATIAL`, `ON DUPLICATE KEY UPDATE` | SQLite |
| — | plusieurs requêtes dans un seul `sql()` | les deux |

## Commandes

| Commande | Rôle |
|---|---|
| `php bin/console db:migrate` | joue les migrations en attente sur la base actuelle (remplace `migrate`) |
| `php bin/console db:migrate --server mysql\|sqlite` | copie toute la base vers un autre serveur SQL |
| `php bin/console db:migrate --create [Module]` | crée un fichier de migration vide dans le module |

`--create` :
- Module en argument, demandé s'il manque. `News`, `Core/News` ou `Extension/News` : les noms sont uniques entre `Core/` et `Extension/`, le préfixe est vérifié s'il est donné
- Le nom n'est jamais utilisé comme chemin : seul un module connu est accepté. `Main` ou une extension, jamais un autre module du cœur
- Fichier `YYYYMMDD-XX.php` à la date du jour, `XX` suivant libre ; dossier `migrations/` créé s'il manque ; jamais d'écrasement

## Changer de serveur SQL

`db:migrate --server mysql|sqlite`, logique dans `src/Database/Transfer.php` :
0. Refus si la base actuelle a des migrations en attente, ou si la cible est le serveur actuel
1. Base cible depuis le `.env`, jamais en ligne de commande (pas de mot de passe dans l'historique du shell) : vers MySQL, l'utilisateur remplit `DB_HOST`, `DB_NAME`… en gardant `DB_DRIVER=sqlite` ; vers SQLite, `data/database.sqlite`. La cible doit être vide. `Database` ne gère qu'une connexion : la commande ouvre la seconde
2. Schéma : toutes les migrations rejouées sur la cible
3. Données : copie table par table, clés étrangères désactivées, dans une transaction, ids d'origine conservés. La table `migration` n'est pas copiée (remplie au point 2)
4. Vérification : nombre de lignes comparé table par table, avant de valider. Le `.env` n'est pas modifié : la commande indique quoi changer
5. Échec : toutes les tables créées dans la cible sont supprimées (elle était vide au départ), on peut relancer après correction

Pièges, tous deux en annulation avec un message qui désigne la ligne :
- SQLite vers MySQL, `UNIQUE` : `utf8mb4_unicode_ci` ignore casse et accents, « Café » et « cafe » deviennent le même slug
- SQLite accepte des valeurs que MySQL refuse (texte dans un `INT`…)

## Références

Étudiés pour la recréation de table en SQLite : Phinx (`SQLiteAdapter`), Doctrine DBAL (`SQLitePlatform`), Laravel (`SQLiteGrammar`). Tous trois font écrire le schéma avec une API PHP et apportent des dépendances lourdes ; aucun ne traduit du SQL brut. Repris : l'ordre officiel de recréation (Phinx, Laravel), la relecture des index et triggers (Phinx), la recréation pour les `ADD COLUMN` refusés (DBAL), `ENUM` en `CHECK` (Laravel), l'index de clé étrangère seulement s'il manque (DBAL).
