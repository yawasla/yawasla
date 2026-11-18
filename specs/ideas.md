# Idées

Pistes non décidées. Une idée validée passe dans `roadmap.md`.

## Fonctionnalités

- **Module Backup** : chaque jour par le cron, dump de la base en PHP (pas de `mysqldump` sur mutualisé) + `public/uploads/` dans une archive sous `data/backups/`, avec rotation. À trancher : format (`zip` pas toujours présent), `.env` inclus ou non (secrets), nombre gardé, téléchargement depuis l'admin
- **API Janaza** : activer une API pour partager les prières avec le projet janaza.cloud
- **API Corpus** : module Yawasla qui interroge l'API de corpus.yawasla.org pour récupérer un article ou une page de la bibliothèque des Compagnons (synthèse et hadiths)
- Plugin WordPress dédié
- Mise à jour automatique
- Multi-utilisateur complet
- **Alertes de santé** dans la barre du haut de l'admin : `MAIL_FROM` vide, cron arrêté, `data/` non inscriptible… En clair et traduit, jamais de détail technique
- **Message de maintenance** personnalisable (« Retour prévu à 18 h ») sur la page 503
- **Bouton rouge** : un admin déconnecte toutes les sessions sauf la sienne, en cas de compte piraté. Suppression des fichiers `sess_*` de `data/sessions` sauf `session_id()` ; POST avec CSRF et confirmation. Déconnecte tout le monde (on ne sait pas quel fichier appartient à quel compte) ; à combiner avec la désactivation du compte ou le changement de son mot de passe, sinon le pirate se reconnecte
- Compteurs de vues et de likes : à compter hors de `publication`, en évitant les robots et les doublons
- Publication regroupant plusieurs contenus (un article et plusieurs médias…) : `publication_id` n'est pas unique dans les tables enfants pour le permettre

## Technique

- **Commandes de module** (`src/Command/` d'un module) : à la première qui en a besoin ; celles du socle restent dans le noyau
- **PostgreSQL** : un `PostgreSQLAdapter` à côté des autres. Peu demandé (mutualisé : MySQL / MariaDB), et coûteux au-delà de l'adaptateur : booléens stricts (`1` / `0` refusés dans toutes les requêtes Medoo), `LIKE` sensible à la casse, `GROUP BY` strict, backticks, `MODIFY` en `ALTER COLUMN … TYPE … USING`, `INSERT IGNORE` en `ON CONFLICT DO NOTHING`, séquences à recaler après `Transfer`, clés étrangères désactivables seulement en superutilisateur
- **Niveau éditeur** pour les extensions, contre les collisions de noms entre devs : `modules/Extension/Acme/Gallery`, id `acme/gallery` (templates, assets `@acme/gallery/…`, `module('Acme/Gallery')`, `requires`, namespace, table `migration`). Le cœur garde ses noms courts. Une activation par module, pas par éditeur ; migrations dans chaque module, pas de `Main` par éditeur. À trancher avant la première release : l'id du module est enregistré en base
- Désactivation d'un module requis par d'autres : refus, ou cascade avec confirmation listant les modules concernés
- Extensions distribuées déjà compilées : aujourd'hui un seul build et un seul manifest pour tous les modules
- `module()` sans `block.twig` : ne rien afficher au lieu d'une erreur (`custom.twig.example` appelle `module('News')`, qui n'a pas encore de bloc)
- Cache d'un an pour les CSS et JS dans `public/.htaccess`, possible grâce aux noms hashés
- Textes en dur à passer en anglais avec `|trans` (« Fil des annonces »…)
