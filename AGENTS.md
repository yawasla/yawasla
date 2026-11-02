# Yawasla — Contexte projet

Newsroom en ligne pour les mosquées et associations musulmanes (annonces Janaza, actualités, appels au don…), diffusée par web push et mail, sans appli à installer. Open source (Apache 2.0), auto-hébergé.

**Deux critères arbitrent tout : facilité et sécurité.** Facile à installer et à maintenir par un débutant ; sécurisé par défaut, car un site compromis qui publie au nom d'une mosquée nuit durablement à son image. Moins de code = moins de surface d'attaque : en cas de doute, la solution la plus simple.

## Méthode de travail

- **Le porteur du projet décide de ce qu'on fait.** L'agent ne propose pas de plan ni de refonte de sa propre initiative
- On réfléchit ensemble sur chaque sujet ; l'agent donne son avis quand on le lui demande
- L'agent ne code qu'une fois l'accord explicite obtenu, uniquement ce qui a été validé, puis le porteur relit
- Ce fichier n'est mis à jour que sur demande, ou pour une décision critique

## Principes

Repères plutôt que règles : ils ne doivent pas freiner le développement, tant que ça reste simple et sûr.

- PHP rendu côté serveur, Twig pour les vues
- Front : Bootstrap (CSS et JS, installé par npm et compilé avec nos sources), nos styles en SCSS, nos scripts en TypeScript, le tout compilé par Vite
- Noms de classes, méthodes et messages d'erreur en anglais ; classes abstraites préfixées par `Abstract`
- Classes simples ; singletons via le trait `Singleton` pour les services transverses (`Config`, `Router`, `Translator`, `Connection`, `Debug`)
- Base de données via Medoo, appelé dans `Model/`
- Une dépendance ou un outil de plus seulement s'il simplifie vraiment
- Le cœur n'est pas modifié par l'utilisateur : il passe par un module dans `modules/Extension/` et par `templates/custom.twig`

## Environnement

- PHP 8.1+ ; SQLite par défaut, MySQL / MariaDB en option
- SSL obligatoire (web push, cookies `Secure`). Hébergement cible : mutualisé (OVH, cPanel) comme VPS
- Dev uniquement : Node 22+ et Vite pour compiler le SCSS et le TypeScript. Les fichiers compilés ne sont pas versionnés : les archives de release à télécharger pour l'installation les contiennent, l'installation n'a donc pas besoin de Node

## Specs

Le détail est dans `specs/` : lire le fichier concerné avant de travailler sur une partie, le mettre à jour avec ce qui a été validé.

| Fichier | Contenu |
|---|---|
| `structure.md` | arborescence, parcours d'une requête, modules, assets |
| `database.md` | tables, relations, choix et conventions |
| `migration.md` | écriture des migrations, `Migrator`, traduction vers SQLite, changement de serveur SQL |
| `roadmap.md` | ce qui est décidé, dans l'ordre, avec son statut |
| `ideas.md` | pistes non décidées |

## Sécurité

**Aucun commentaire dans le code** (projet open source) : le pourquoi des protections est ici, nulle part ailleurs.

Règles :
- Twig échappe tout : `|raw` uniquement sur du HTML de confiance
- Rien de la requête HTTP en nom de colonne, clé Medoo, chemin de fichier ou `redirectExternal()`
- Pas d'URL absolue en base ; liens des mails depuis le domaine en base, jamais l'en-tête `Host`
- Pas de JS ni de style inline, pas de CDN : la CSP l'interdit, tout ajout externe doit y être déclaré
- Uploads : liste blanche d'images uniquement (un SVG ou un HTML exécuterait du JS sur le domaine)
- `AbstractAdminController` ne protège encore rien : aucune route `/admin` avant l'authentification ; le Router refuse toute route `/admin` dont le contrôleur n'hérite pas d'`AbstractAdminController`

En place :
- `bootstrap.php` envoie les en-têtes (CSP, HSTS sans `includeSubDomains`…) pour tout serveur ; `public/.htaccess` pour les fichiers statiques
- Racine du projet exposée par erreur : `.htaccess` bloque tout, `index.php` répond 503 (nginx non couvert)
- `data/` et scripts dans `uploads/` refusés par leur `.htaccess` ; `assets/.vite/` refusé par `public/.htaccess`
- Debug seulement si `APP_ENV` vaut exactement `dev` ; erreurs jamais affichées au client
- Langue filtrée par liste blanche ; paramètres de route en 404 s'ils contiennent `/`, `\`, `..` ou un caractère de contrôle
- `redirect()` : chemin interne uniquement (`//`, `/\` et caractères de contrôle mènent ailleurs) ; `redirectExternal()` : `http`/`https`. Sinon, accueil

## Produit

| Chemin | Rôle |
|---|---|
| `/` | Newsroom publique : infos de l'organisme, annonces |
| `/news/{slug}` | Page d'une annonce, balises Open Graph (aperçu WhatsApp/Facebook) |
| `/feed/` | **Seule partie PWA** (manifest et service worker limités à `/feed/`) : fil, abonnement push et mail |
| `/admin/` | Back-office, hors portée du service worker |

- iOS : web push uniquement si le site est ajouté à l'écran d'accueil (iOS 16.4+) → guide dans `/feed/`, et le mail reste indispensable

## Remarques

**Authentification** — on part du principe qu'il y aura des attaques
- Session PHP native, fichiers dans `data/sessions`, cookie `Secure`, `HttpOnly`, `SameSite=Lax`
- Connexion en deux temps : identifiants, puis lien à usage unique de 5 min par mail. Le lien ouvre une page avec un bouton qui valide (jamais à l'ouverture : scanners de liens)
- Réponse d'échec uniforme quelle que soit la raison ; limitation par IP qui n'annonce jamais le blocage

**Mail** : deux voies. Sécurité (connexion, reset) via PHPMailer (SMTP ou `mail()`), indépendante du fournisseur de diffusion. Diffusion des annonces via Brevo, interchangeable.

**i18n** : interface du système en fr, en, ar ; langue de l'admin dans l'URL (`/admin/fr/…`), celle du front dans `.env`. RTL : propriétés CSS logiques dans nos styles, Bootstrap compilé en `app.rtl.scss` via rtlcss. Langue d'interface ≠ langue du contenu.
