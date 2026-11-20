# 🛡️ Double authentification

La double authentification ajoute une seconde preuve à votre mot de passe : un code à 6 chiffres. Même si votre mot de
passe est volé ou deviné, personne ne peut entrer dans l'Administration (app4) sans ce code.

## Qui est concerné ?

- **Obligatoire** pour les comptes qui ont un profil 1 à 4 (super-admin, bureau CNAKP, responsables de division et de
  compétition), **y compris par un mandat**. Elle devient obligatoire à une date annoncée par l'équipe KPI.
- **Facultative** (mais recommandée) pour tous les autres comptes : activez-la quand vous voulez depuis
  **Sécurité du compte**.

## Deux méthodes au choix

| Méthode | Comment ça marche | Pour qui |
|---|---|---|
| **Application d'authentification** (recommandée) | Google Authenticator, Authy, 1Password, Microsoft Authenticator… affiche un code qui change toutes les 30 secondes. Fonctionne sans réseau. | Si vous avez un smartphone |
| **Code par e-mail** | Un code est envoyé à l'adresse e-mail de votre fiche à chaque connexion. Valable 10 minutes. | Si vous préférez ne pas installer d'application |

Le code par e-mail est moins sûr : une personne qui accède à votre boîte mail peut aussi réinitialiser votre mot de
passe. Préférez l'application si vous le pouvez.

## Activer la double authentification

1. Menu utilisateur (en haut à droite) → **Sécurité du compte** → **Activer la double authentification**.
2. Choisissez la méthode :
   - **Application** : scannez le QR code avec l'application (ou recopiez la clé affichée), puis saisissez le code
     qu'elle affiche ;
   - **E-mail** : saisissez le code reçu.
3. **Conservez vos 10 codes de récupération** (copiez-les dans un gestionnaire de mots de passe, téléchargez-les ou
   imprimez-les). Ils ne seront plus affichés. Chacun permet une connexion si vous perdez votre téléphone ou l'accès à
   votre boîte mail.

## Quand c'est obligatoire : 7 jours pour s'équiper

À partir de la date annoncée, à votre première connexion, un bandeau orange vous rappelle d'activer la double
authentification et indique le nombre de jours restants. Vous gardez l'accès normal pendant **7 jours**. Passé ce délai,
l'écran **« Activez la double authentification »** s'affiche après votre mot de passe et aucune page n'est accessible
avant l'activation.

## Se connecter

1. Identifiant et mot de passe, comme d'habitude (ou « Continuer avec Google »).
2. Saisissez le code de l'application ou le code reçu par e-mail (lien « Renvoyer un code » au besoin).
3. Sur votre ordinateur personnel, cochez **« Faire confiance à cet appareil pendant 30 jours »** : le code ne vous sera
   plus demandé sur cet appareil pendant 30 jours. **Ne la cochez pas sur un ordinateur partagé** (table de marque,
   salle de réunion).

## Questions fréquentes

**J'ai perdu mon téléphone / je n'ai plus accès à ma boîte mail.** Saisissez un de vos codes de récupération à la place
du code. Une fois connecté, changez de méthode depuis **Sécurité du compte**. Plus de codes ? Contactez un
super-admin : il réinitialise votre double authentification, et vous en configurez une nouvelle à la connexion suivante.

**Je change de téléphone.** Avant de quitter l'ancien : **Sécurité du compte** → **Changer de méthode** → saisissez un
code de l'ancien téléphone, puis scannez le nouveau QR code avec le nouveau téléphone.

**Le code de l'application est refusé.** Vérifiez que l'heure de votre téléphone est réglée automatiquement : un écart
de plus d'une minute rend les codes invalides.

**Je n'ai pas reçu le code par e-mail.** Regardez dans les indésirables, puis « Renvoyer un code » (une fois par minute,
5 fois par heure au plus). Vérifiez avec un administrateur que l'adresse de votre fiche est correcte.

**Trop d'essais.** Après 5 codes faux, le compte est bloqué 15 minutes, comme pour un mauvais mot de passe.

**Je peux la désactiver ?** Oui si votre compte n'a aucun profil de 1 à 4 (mandats compris), depuis **Sécurité du
compte**, avec un code. Non sinon : elle est obligatoire.

**Un appareil de confiance ne l'est plus.** **Sécurité du compte** → **Appareils de confiance** → **Révoquer**. Tous vos
appareils de confiance sont aussi retirés quand vous changez de mot de passe ou de méthode.

Spécification technique : [AUTH_HARDENING.md § 5](../developer/in-progress/AUTH_HARDENING.md).
