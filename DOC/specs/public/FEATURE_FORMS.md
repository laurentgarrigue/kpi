# Formulaires d'inscription

**Phase** : 4b — **Statut** : 📝 Brouillon (10/10/2026) — **Routes** : `/forms/{slug}`,
`/forms/{slug}/entries` (+ `/en/…`) — **Remplace** : Ninja Forms (formulaires), TablePress (listes publiques),
Email Log, Akismet — **Stratégie** : § 5.3 de
[PUBLIC_SITE_REDESIGN_STRATEGY.md](../../developer/in-progress/plans/PUBLIC_SITE_REDESIGN_STRATEGY.md)

## 1. Objectif

Permettre aux organisateurs d'ouvrir les inscriptions à un tournoi (ou un stage, une formation d'arbitres) sans
WordPress : un formulaire simple, des e-mails de confirmation, une liste publique des inscrits si souhaité, un
export. Le paiement, quand il existe, reste chez **HelloAsso** (aucune donnée bancaire chez KPI).

## 2. Constructeur (app4)

Rubrique « Site public › Formulaires » (capacité Rédacteur ou profils 1–2 ; capacité `forms` attribuable à un
organisateur pour **ses** formulaires, Q-P4-6).

| Réglage | Détail |
|---|---|
| Identité | titre FR / EN, slug, introduction (texte riche, FEATURE_CMS.md § 3), rattachement optionnel à un événement ou une compétition (le formulaire s'affiche aussi sur leur page) |
| Champs | types : texte court, texte long, e-mail, téléphone, nombre, date, liste déroulante, choix unique, cases à cocher, **club** (liste des clubs KPI), **compétition** (liste de la saison) ; libellés FR / EN, obligatoire, aide, valeur min / max ; ordre par glisser-déposer |
| Ouverture | dates d'ouverture et de clôture, nombre maximal d'inscriptions, **liste d'attente** au-delà (oui / non) |
| Notifications | e-mail de confirmation au déclarant (texte personnalisable avec les réponses), notification aux organisateurs (adresses) |
| Liste publique | désactivée par défaut ; colonnes publiées choisies parmi les champs **non personnels** proposés (club, équipe, catégorie, nombre) — jamais e-mail ni téléphone |
| Mode HelloAsso | URL du formulaire / billetterie HelloAsso ; affichage en **widget intégré** (iframe officielle, chargée au clic) ou **bouton** ; import CSV de l'export HelloAsso pour alimenter la liste publique |
| RGPD | finalité, responsable, durée de conservation (défaut : 3 mois après la date de l'événement ou la clôture), mention affichée sous le formulaire |

Les inscriptions sont consultables, modifiables (statut : confirmée, en attente, annulée) et **exportables**
(CSV / Excel via OpenSpout, déjà utilisé) dans app4.

## 3. Page publique `/forms/{slug}`

1. `<h1>` titre, introduction, état : « Ouvert jusqu'au … », « Complet — liste d'attente », « Clos », « Ouvre le … ».
2. Formulaire HTML accessible (libellés, aides, erreurs liées par `aria-describedby`, récapitulatif des erreurs
   en tête au focus), fonctionnel **sans JavaScript** (POST classique, rendu serveur des erreurs).
3. Envoi → page de confirmation (« Votre inscription est enregistrée » / « en liste d'attente ») + e-mail.
4. Mode HelloAsso : bouton ou widget (aucune requête vers HelloAsso avant le clic, comme la carte des clubs).
5. Liste publique `/forms/{slug}/entries` si activée : tableau des colonnes publiées, nombre d'inscrits, mise à
   jour toutes les 5 minutes (cache).
6. Formulaire inconnu ou non publié → 404 ; clos → page visible, formulaire masqué.

## 4. api2

| Endpoint | Rôle |
|---|---|
| `GET /forms/{slug}` | définition publique (champs, état, textes) — public |
| `POST /forms/{slug}/submissions` | validation serveur des champs (types, obligatoires, bornes, listes), contrôle d'ouverture et de capacité **en transaction** (pas de dépassement en cas d'envois simultanés), enregistrement, e-mails ; réponse `{ status: 'confirmed'\|'waiting' }` ; erreurs `422 { errors: { champ: code } }` |
| `GET /forms/{slug}/entries` | colonnes publiées uniquement, si la liste est publique — public |
| `/admin/cms/forms…` | CRUD formulaires, inscriptions, export, import CSV HelloAsso, journal des e-mails — authentifié |

Tables `kp_form`, `kp_form_field`, `kp_form_entry` (réponses en JSON validé), `kp_mail_log` (destinataire, sujet,
statut d'envoi, date — sans le corps au-delà de 30 jours). Envoi par `symfony/mailer` (déjà installé et configuré :
`MAILER_FROM`), **synchrone** après l'enregistrement : un échec d'envoi n'annule pas l'inscription, il est journalisé
(`kp_mail_log.status = failed`) et renvoyable depuis app4. Pas de Messenger (pas de worker à exploiter) tant que
les volumes restent faibles.

## 5. Anti-spam et sécurité

- **Pot de miel** (champ caché) + **délai minimal** de remplissage (3 s, horodatage signé dans le formulaire).
- **Limitation de débit** : 5 envois / 10 min / IP / formulaire (même mécanisme que `SearchThrottle`, IP relayée
  par app3 en `X-Forwarded-For`).
- Captcha respectueux de la vie privée (ex. ALTCHA, preuve de travail auto-hébergée) **seulement si** le spam
  persiste (Q-P4-7) ; jamais reCAPTCHA (appel tiers).
- Les e-mails n'incluent jamais de contenu HTML saisi par l'utilisateur (texte échappé).

## 6. RGPD

- Minimisation : le constructeur rappelle de ne demander que le nécessaire ; e-mail du déclarant obligatoire
  (confirmation), le reste au choix de l'organisateur.
- **Purge automatique** (commande planifiée quotidienne `app:forms:purge`) à l'échéance de conservation :
  inscriptions supprimées, statistiques agrégées conservées (nombre d'inscrits).
- Liste publique : jamais de coordonnées ; un inscrit peut demander son retrait (lien dans l'e-mail de
  confirmation → l'organisateur est notifié).

## 7. Critères d'acceptation

- **FRM-01** — `/forms/{slug}` affiche le formulaire ouvert et fonctionne sans JavaScript (POST, erreurs rendues
  par le serveur) ; inconnu → 404 ; clos ou pas encore ouvert → message, sans formulaire.
- **FRM-02** — La validation serveur refuse les champs obligatoires vides, les types et bornes invalides et les
  valeurs hors liste (`422` avec un code par champ).
- **FRM-03** — Au-delà du maximum, l'inscription passe en liste d'attente (si activée) ou est refusée ; deux envois
  simultanés ne dépassent jamais la capacité.
- **FRM-04** — Chaque inscription envoie la confirmation au déclarant et la notification aux organisateurs ; les
  envois sont journalisés.
- **FRM-05** — Pot de miel, délai minimal et limitation de débit bloquent les envois automatisés (429 au-delà).
- **FRM-06** — La liste publique n'expose que les colonnes publiées, jamais e-mail ni téléphone.
- **FRM-07** — Le mode HelloAsso n'effectue aucune requête vers HelloAsso avant le clic ; l'import CSV alimente la
  liste publique.
- **FRM-08** — L'export CSV / Excel contient toutes les inscriptions du formulaire (app4, droits vérifiés).
- **FRM-09** — `app:forms:purge` supprime les inscriptions échues et conserve le nombre d'inscrits.
- **FRM-10** — Le formulaire rattaché à un événement ou une compétition est proposé sur leur page.

## 8. Questions ouvertes (à trancher avant implémentation)

- **Q-P4-6 — Qui crée les formulaires ?** Proposé : rédacteurs et profils 1–2, plus une capacité `forms`
  attribuable aux organisateurs, limitée à leurs formulaires.
- **Q-P4-7 — Captcha.** Proposé : pas de captcha au lancement (pot de miel + délai + débit), ALTCHA auto-hébergé
  si nécessaire.
- **Q-P4-8 — Formulaires Ninja Forms en cours.** Reprendre les formulaires ouverts au moment de la bascule
  (définition seulement, pas les inscriptions) ou les recréer à la main ?
- **Q-P4-9 — Synchronisation HelloAsso.** Hors MVP (import CSV) ; webhooks de paiement à étudier ensuite.
