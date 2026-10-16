# Formulaires d'inscription

**Phase** : 4b — **Statut** : ✅ Validée (11/10/2026, décisions Q-P4-6 à 9) — **Routes** : `/forms/{slug}`,
`/forms/{slug}/entries` (+ `/en/…`) — **Remplace** : Ninja Forms (formulaires), TablePress (listes publiques),
Email Log, Akismet — **Écrans app4** : [PAGE_CMS.md](../PAGE_CMS.md) § Formulaires — **Stratégie** : § 5.3 de
[PUBLIC_SITE_REDESIGN_STRATEGY.md](../../developer/in-progress/PUBLIC_SITE_REDESIGN_STRATEGY.md)

## 1. Objectif

Permettre aux organisateurs d'ouvrir les inscriptions à un tournoi (ou un stage, une formation d'arbitres) sans
WordPress : un formulaire simple, des e-mails de confirmation, une liste publique des inscrits si souhaité, un
export. Le paiement, quand il existe, reste chez **HelloAsso** (aucune donnée bancaire chez KPI) : le formulaire
indique seulement le lien de paiement ; l'inscription reste **en attente** jusqu'à ce que l'organisateur confirme la
réception du paiement.

## 2. Constructeur (app4)

Rubrique **Admin › Éditorial › Formulaires** d'app4, réservée aux **profils 1–2 et aux rédacteurs** (✅ Q-P4-6).

| Réglage | Détail |
|---|---|
| Identité | titre FR / EN, slug, introduction (texte riche, FEATURE_CMS.md § 3), rattachement optionnel à un événement ou une compétition (le formulaire s'affiche aussi sur leur page) |
| Champs | types : texte court, texte long, e-mail, téléphone, nombre, date, liste déroulante, choix unique, cases à cocher, **club** (liste des clubs KPI), **compétition** (compétitions de la **saison active et de la saison suivante**,
**non terminées** seulement : statut ≠ `END`) ; libellés FR / EN, obligatoire, aide, valeur min / max ; ordre par glisser-déposer |
| Ouverture | dates d'ouverture et de clôture, nombre maximal d'inscriptions (places **confirmées**), **liste d'attente** au-delà (oui / non) |
| Confirmation | **automatique** (dans la limite des places) ou **manuelle** : toute inscription arrive en attente et l'organisateur la confirme (ex. paiement reçu) |
| Notifications | e-mail de confirmation au déclarant (texte personnalisable avec les réponses), notification aux organisateurs (adresses) |
| Liste publique | désactivée par défaut ; colonnes publiées choisies parmi les champs **non personnels** proposés (club, équipe, catégorie, nombre) — jamais e-mail ni téléphone |
| Paiement | **lien de paiement** optionnel (HelloAsso ou autre), affiché sur la page de confirmation et dans l'e-mail ; aucune intégration (widget, import, synchronisation : à étudier ensuite, Q-P4-9) |
| RGPD | finalité, responsable, durée de conservation (défaut : 3 mois après la date de l'événement ou la clôture), mention affichée sous le formulaire |

Les inscriptions sont consultables, modifiables (statut : **confirmée**, **en attente** — liste d'attente ou paiement non
reçu —, **annulée**) et **exportables**
(CSV / Excel via OpenSpout, déjà utilisé) dans app4.

## 3. Page publique `/forms/{slug}`

1. `<h1>` titre, introduction, état : « Ouvert jusqu'au … », « Complet — liste d'attente », « Clos », « Ouvre le … ».
2. Formulaire HTML accessible (libellés, aides, erreurs liées par `aria-describedby`, récapitulatif des erreurs
   en tête au focus), fonctionnel **sans JavaScript** (POST classique, rendu serveur des erreurs).
3. Envoi → page de confirmation (« Votre inscription est confirmée » / « Votre inscription est en attente » avec
   la raison : liste d'attente ou confirmation par l'organisateur) + e-mail ; lien de paiement s'il existe (nouvel
   onglet, aucune requête vers le prestataire avant le clic).
4. Confirmation par l'organisateur (app4) → e-mail « Votre inscription est confirmée ».
5. Liste publique `/forms/{slug}/entries` si activée : **deux tableaux**, « Inscriptions confirmées » puis « En
   attente » (liste d'attente ou paiement non reçu), colonnes publiées, effectifs, ordre d'inscription ; cache
   5 minutes. Les annulées n'apparaissent pas.
6. Formulaire inconnu ou non publié → 404 ; clos → page visible, formulaire masqué.

## 4. api2

| Endpoint | Rôle |
|---|---|
| `GET /forms/{slug}` | définition publique (champs, état, textes) — public |
| `POST /forms/{slug}/submissions` | validation serveur des champs (types, obligatoires, bornes, listes), contrôle d'ouverture et de capacité **en transaction** (pas de dépassement en cas d'envois simultanés), enregistrement, e-mails ; réponse `{ status: 'confirmed'\|'waiting', reason: null\|'full'\|'manual', payment_url }` ; erreurs `422 { errors: { champ: code } }` |
| `GET /forms/{slug}/entries` | colonnes publiées uniquement, si la liste est publique — public |
| `GET /forms/options/competitions` | compétitions proposables (saison active et suivante, non terminées) — public |
| `/admin/cms/forms…` | CRUD formulaires, inscriptions (changement de statut), export, journal des e-mails — authentifié |

Tables `kp_form`, `kp_form_field`, `kp_form_entry` (réponses en JSON validé), `kp_mail_log` (destinataire, sujet,
statut d'envoi, date — sans le corps au-delà de 30 jours). Envoi par `symfony/mailer` (déjà installé et configuré :
`MAILER_FROM`), **synchrone** après l'enregistrement : un échec d'envoi n'annule pas l'inscription, il est journalisé
(`kp_mail_log.status = failed`) et renvoyable depuis app4. Pas de Messenger (pas de worker à exploiter) tant que
les volumes restent faibles.

## 5. Anti-spam et sécurité

- **Pot de miel** (champ caché) + **délai minimal** de remplissage (3 s, horodatage signé dans le formulaire).
- **Limitation de débit** : 5 envois / 10 min / IP / formulaire (même mécanisme que `SearchThrottle`, IP relayée
  par app3 en `X-Forwarded-For`).
- **Pas de captcha** au lancement (✅ Q-P4-7) ; jamais reCAPTCHA (appel tiers). ALTCHA auto-hébergé envisageable
  si le spam persiste.
- Les e-mails n'incluent jamais de contenu HTML saisi par l'utilisateur (texte échappé).

## 6. RGPD

- Minimisation : le constructeur rappelle de ne demander que le nécessaire ; e-mail du déclarant obligatoire
  (confirmation), le reste au choix de l'organisateur.
- **Purge automatique** (commande planifiée quotidienne `app:forms:purge`) à l'échéance de conservation :
  inscriptions supprimées, statistiques agrégées conservées (nombre d'inscrits).
- Liste publique : jamais de coordonnées ; un inscrit peut demander son retrait ou une modification en
  **répondant à l'e-mail de confirmation** (réponse adressée aux organisateurs, `Reply-To`), qui changent le statut
  dans app4.

## 7. Critères d'acceptation

- **FRM-01** — `/forms/{slug}` affiche le formulaire ouvert et fonctionne sans JavaScript (POST, erreurs rendues
  par le serveur) ; inconnu → 404 ; clos ou pas encore ouvert → message, sans formulaire.
- **FRM-02** — La validation serveur refuse les champs obligatoires vides, les types et bornes invalides et les
  valeurs hors liste (`422` avec un code par champ).
- **FRM-03** — Au-delà du maximum, l'inscription passe en attente (si la liste d'attente est activée) ou est
  refusée ; en confirmation manuelle, toute inscription arrive en attente ; deux envois simultanés ne dépassent
  jamais la capacité.
- **FRM-11** — Le champ « compétition » ne propose que les compétitions non terminées de la saison active et de la
  suivante ; une autre valeur est refusée.
- **FRM-04** — Chaque inscription envoie la confirmation au déclarant et la notification aux organisateurs ; les
  envois sont journalisés.
- **FRM-05** — Pot de miel, délai minimal et limitation de débit bloquent les envois automatisés (429 au-delà).
- **FRM-06** — La liste publique n'expose que les colonnes publiées, jamais e-mail ni téléphone ; elle sépare les
  inscriptions confirmées et en attente, sans les annulées.
- **FRM-07** — Le lien de paiement apparaît sur la confirmation et dans l'e-mail ; confirmer une inscription en
  attente (app4) envoie l'e-mail de confirmation.
- **FRM-08** — L'export CSV / Excel contient toutes les inscriptions du formulaire (app4, droits vérifiés).
- **FRM-09** — `app:forms:purge` supprime les inscriptions échues et conserve le nombre d'inscrits.
- **FRM-10** — Le formulaire rattaché à un événement ou une compétition est proposé sur leur page.

## 8. Décisions (11/10/2026)

- **Q-P4-6 — Qui crée les formulaires ?** ✅ Profils 1–2 et rédacteurs, pour l'instant.
- **Q-P4-7 — Captcha.** ✅ Aucun au lancement.
- **Q-P4-8 — Formulaires Ninja Forms.** ✅ Seul le formulaire Ninja Forms **id 19** est repris, comme exemple,
  **non publié** (définition et champs, sans les inscriptions) : `app:import-wordpress --ninja-form=19`.
- **Q-P4-9 — HelloAsso.** ✅ À étudier ensuite : pas de widget, d'import ni de synchronisation ; simple lien de
  paiement et confirmation manuelle.
