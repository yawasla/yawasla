# Abonnements

Module `Core/Communication` : il diffuse ce que News, Janaza et Article produisent (Article pour le digest), sans saisir de contenu. Il en dépend (`requires`).
- Front : `/feed` (PWA, service worker, abonnement push et mail, guide iOS), confirmation et désinscription
- Admin : abonnés (mail et push, nombre), réglages d'envoi (clé Brevo, expéditeur, `digest_frequency`)
- Tâches du cron : envoi des news et janaza en push et par mail, digest

Les visiteurs reçoivent les news et les janaza sans appli à installer : par web push ou par mail. Abonnement depuis `/feed`, seule partie PWA du site. Aucun compte : un abonné n'est jamais un `user`.

## Ce qui part

| Contenu | Push | Mail | Digest (mail) |
|---|---|---|---|
| Janaza | oui | oui | non |
| News | oui | oui | oui |
| Article | non | non | oui |

Tout part par le cron (`php bin/console cron`), jamais pendant la requête de l'admin qui publie. Sa fréquence se règle chez l'hébergeur, à l'installation : elle fixe le délai d'envoi d'une janaza.

- News et janaza : texte court (`content` en `VARCHAR(255)`), envoyé tel quel en notification
- Abonnements non rattachés à une organisation, pas de filtre par mosquée : on reçoit les contenus de la principale et des secondaires
- Titre de la notification (et objet du mail) : le nom de la mosquée concernée (`organization_id` de la news ou de la janaza)

## Mail

Table `email_subscription` : un email unique (normalisé en minuscules), choix `news_subscribed`, `janaza_subscribed`, `digest_subscribed`, faits par l'abonné à l'inscription.

- Inscription confirmée par un lien à usage unique (`token`, type d'abonné) : rien n'est envoyé avant la confirmation
- Désinscription en un clic depuis chaque mail
- Envoi via Brevo, interchangeable ; les mails de sécurité (connexion, reset) restent sur PHPMailer
- Liens des mails construits depuis `site_url`, jamais depuis l'en-tête `Host`

## Digest

Récapitulatif par mail des news et articles (jamais les janaza). Fréquence réglée par l'admin pour tout le site : `digest_frequency` dans `config`, `weekly` ou `monthly`. `digest_included` : le contenu doit figurer dans le prochain digest ; `digest_sent_at` : il y est parti. Un contenu n'est jamais envoyé deux fois.

## Push

Table `push_subscription` : `endpoint` (son hash `endpoint_hash` unique), clés `p256dh` et `auth`.

- Pas de choix : un abonné push reçoit les janaza et les news
- Manifest et service worker limités à `/feed`
- iOS : push seulement si le site est ajouté à l'écran d'accueil (iOS 16.4+) → guide dans `/feed`, et le mail reste indispensable
