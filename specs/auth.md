# Authentification

On part du principe qu'il y aura des attaques : un compte compromis publie au nom de la mosquée.

## Deux parties

- **Noyau, `src/Auth/`** : ce dont le Router et les autres modules ont besoin, sans dépendre d'aucun module. `Auth` (accès des routes, utilisateur connecté), `Password` (règles et hachage, partagé avec l'Install), `Throttle` (limitation) ; modèles `User` et `Token` dans `src/Model/`, envoi par `src/Mail/Mailer.php`
- **Module `Core/Auth`**, verrouillé : `LoginController` et ses pages, templates, traductions et mails. Routes : `/login`, `/login/confirm/{token}`, `/login/reset`, `/login/reset/{token}`, `/logout`. Langue dans l'URL comme l'admin (`/login/fr/reset`), sauf `/logout` qui ne fait que déconnecter et rediriger vers `/login` dans la langue du site ; sans préfixe : anglais. Le lien du mail garde la langue de la demande, et la connexion mène à l'admin dans cette langue

## Accès des routes

Déclaré dans `#[Route(auth: 'guest'|'user'|'admin')]`, vérifié par le Router : `specs/structure.md`, partie Requête.

- Refus, non connecté : tout chemin sous `/admin`, existant ou non (rien ne trahit les routes), → 302 vers `/login` dans la même langue, sans `?next=` (redirection ouverte) ; ailleurs 404. Connecté sans le droit → 403
- `/login` avec une session active → admin dans la langue de l'URL
- `Auth::allows()` lit `user_id` en session ; sans cookie de session, aucune session n'est démarrée
- L'utilisateur est rechargé depuis la base à chaque requête (`User::findActive()`) : un compte désactivé perd l'accès à sa requête suivante, sa session est vidée

## Connexion

En deux temps, sous `/login` :

1. **Identifiants** : email et mot de passe. Réponse identique quelle que soit l'issue (compte inconnu, désactivé, mauvais mot de passe, IP limitée) ; compte inconnu : le mot de passe saisi est haché (même coût qu'une vérification), pour que le temps de réponse ne trahisse pas l'existence du compte
2. **Lien par mail** : si les identifiants sont bons, un lien à usage unique valable 5 min est envoyé. Il ouvre une page avec un bouton qui valide la connexion (`/login/confirm/{token}`), jamais à l'ouverture (les scanners de liens des messageries l'ouvriraient). Le lien n'est pas lié au navigateur qui a saisi les identifiants : on ouvre souvent le mail sur un autre appareil

À la validation : jeton marqué utilisé (une seule requête l'emporte), compte toujours actif, ID de session régénéré, `user_id` en session, `user.connected_at` mis à jour.

## Jetons

Table `token` (`specs/database.md`), `type` = `login` ou `reset`.

- Jeton aléatoire envoyé dans le lien, seul son hash est stocké (`token_hash`)
- Valable s'il n'a pas expiré (`expired_at`) et n'a pas servi (`used_at` NULL)
- `Token::create()` supprime d'abord les jetons expirés ou utilisés, tous types : pas de cron

## Limitation

5 tentatives (POST des formulaires de connexion et de reset) par heure et par IP, comptées dans un fichier sous `data/cache/`, nommé d'après le hash de l'IP (rien de la requête dans un chemin de fichier). Au-delà : plus de formulaire, et les POST sont redirigés sans être lus. `data/cache/` vidé à la mise à jour remet les compteurs à zéro.

- Connexion validée : compteur de l'IP qui valide supprimé
- Compteur impossible à écrire : tout est bloqué avec un warning, jamais ouvert (la limitation tomberait en silence)

## Session

Session PHP native (`src/Http/Session.php`) : fichiers dans `data/sessions`, cookie `Secure` (sauf `APP_ENV=dev`), `HttpOnly`, `SameSite=Lax`, ID régénéré à la connexion. CSRF via `Form` sur tous les formulaires.

Pas de déconnexion automatique (pas d'usage sur ordinateur public) : la session dure 24 h, renouvelées à chaque requête. Cookie de 24 h renvoyé à chaque requête et `gc_maxlifetime` à 24 h : sans activité pendant 24 h, PHP supprime la session, ce qui évite que les sessions abandonnées s'accumulent dans `data/sessions`.

## Déconnexion

`POST /logout` avec CSRF (un formulaire HTML ne sait envoyer que GET et POST ; en GET, un lien venu d'ailleurs déconnecterait l'utilisateur). Session détruite (données, fichier, cookie), puis redirection vers `/login`.

## Reset du mot de passe

Mail via PHPMailer (voie sécurité, indépendante du fournisseur de diffusion), même réponse uniforme que la connexion.

1. `/login/reset` : formulaire avec l'email ; réponse toujours identique (« si un compte existe, un mail a été envoyé »). Compte dans la limitation
2. Lien à usage unique (`type = reset`) valable 30 min : il faut le temps de choisir un mot de passe
3. Le lien ouvre le formulaire du nouveau mot de passe (`Password::isValid()`) ; rien ne change à l'ouverture
4. À la validation : mot de passe changé, jeton marqué utilisé, autres jetons valables du compte annulés, redirection vers `/login` sans connexion automatique (on repasse par les deux temps)


## Mail

- `MAIL_DRIVER` (`smtp`, `mail`, `log`) lu en minuscules, sans espaces ; `MAIL_FROM` vide → warning, mail non envoyé
- `log` (dev) : mail entier ajouté à `data/logs/mail.log`, pas envoyé. Hors `APP_ENV=dev` : ignoré avec un warning, envoi par `mail()`. Jamais dans la barre de debug : un `dev` oublié en prod livrerait le lien à quiconque connaît le mot de passe
