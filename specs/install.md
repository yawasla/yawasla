# Installation

## Module

- Module du cœur `modules/Core/Install`, verrouillé comme `Main` : on ne peut pas le désactiver
- Route `/install`, hors de `/admin`

## Lancement

Uniquement sur la route `/install` : rien n'y redirige. Pas de clé : le premier qui y va installe. Il se lance si :
- aucune base n'est configurée
- la base existe mais sans table `migration`
- la table `migration` existe mais est vide

Sinon `/install` répond 404.

## Étapes

0. **Vérifications**, étape cachée : PHP 8.1+, extensions (`pdo_sqlite` ou `pdo_mysql`, `mbstring`, `openssl`), `data/` accessible en écriture, HTTPS, `.env` et connexion à la base. Une erreur par problème, et pas d'accès à l'étape 1 tant qu'il en reste. Les erreurs restent générales (« Connexion à la base impossible : vérifier `.env` ») : `/install` est ouvert à tous, le détail (hôte, utilisateur, message de PHP) va seulement dans `data/logs/php.log`
1. **Site Internet** : domaine (liens des mails), nom et description du site (`<title>`, `<meta name="description">`). Étape qui guide le SEO : conseil sous chaque champ, compteur de caractères (nom ~60, description ~160), aperçu du résultat dans Google
2. **Organisation** : nom, le reste se complète dans l'admin
3. **Administrateur** : email, prénom, nom, mot de passe
4. **Confirmation** : récapitulatif des saisies
5. **Validation** : lien vers l'admin

Rien n'est écrit avant la confirmation : les saisies restent en session, puis tout est enregistré d'un coup (migrations, site, organisation, administrateur). Site dans `config` : `site_url`, `site_name`, `site_description`. Un navigateur fermé en cours de route ne laisse rien en base.

## Page de maintenance

Site non installé, base injoignable : toutes les routes sauf `/install` affichent la page de maintenance, en 503. Elle ne dépend pas de la base et ne mentionne jamais `/install`.
