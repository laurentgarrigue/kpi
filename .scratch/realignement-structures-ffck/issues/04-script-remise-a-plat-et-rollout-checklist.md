# 04: Script de remise à plat, requête de contrôle et rollout checklist

**What to build:** un script SQL daté, à exécuter une seule fois par environnement après un import PCE fait avec l'import corrigé, qui réaligne la base sur le PCE et sur l'ADR 0001 ; une requête de contrôle avant/après ; et la rollout checklist qui détaille chaque action humaine jusqu'à la prod. Voir la spec, sections « Script de remise à plat » et « Déroulé par environnement ». Pas de test automatisé : la vérification se fait par la requête de contrôle, en dev.

**Blocked by:** 02 (Import aligné sur le PCE)

**Status:** ready-for-agent

Script :
- [ ] Il tient en une seule transaction ; un échec laisse la base intacte
- [ ] Il est rejouable : une seconde exécution ne modifie rien
- [ ] Le garde-fou arrête le script sans rien modifier et liste les codes si un pseudo-club d'Ancienne ligue ou un code `REGxx*`/`T-Rxx*` n'est pas couvert par la table de correspondance
- [ ] CR, CD et Clubs : libellés et rattachements recalculés depuis les licenciés de la saison courante ; CD non référencés supprimés (dont les CD au format brut) ; structures référencées et structures `98` conservées
- [ ] Une Sélection régionale par CR concernée (pseudo-CD et pseudo-club au code et au libellé de la CR) ; équipes et limitations utilisateur des Anciennes ligues réaffectées ; pseudo-clubs et pseudo-CD des Anciennes ligues supprimés
- [ ] Codes `REGxx*`/`T-Rxx*` renommés sur toutes les saisons selon la table de l'ADR 0001 (suffixe conservé, exceptions 2018 et `REG19J` incluses), avec mise à jour de toutes les références listées dans la spec ; collisions résolues par l'ordonnancement
- [ ] Codes de référence des autres compétitions régionales alignés sur la table

Contrôle et exécution en dev :
- [ ] Requête de contrôle livrée : écarts CR/CD/Clubs vs PCE, Clubs du PCE absents, pseudo-clubs d'Anciennes ligues restants, codes `REGxx`/`T-Rxx` hors CR actuelles
- [ ] Exécuté en dev (après un import PCE corrigé) : contrôle avant → script → contrôle après à zéro écart ; seconde exécution sans effet
- [ ] Vérification visuelle en dev d'une Compétition régionale renommée et d'une Sélection régionale (app4)

Rollout checklist :
- [ ] Rédigée dans `DOC/developer/in-progress/` au format de la checklist du site public (tableaux « commande / action | résultat attendu | ☑ », icônes ⌨️ 🖥 🌐 du runbook), référencée dans l'index de `DOC/`
- [ ] Sections : validation dev, PR et CI, préprod (import PCE, dump, contrôle, script, contrôle, vérifications visuelles), message aux responsables régionaux (texte prêt à envoyer), prod (même déroulé, jour sans compétition), rollback (restauration du dump), archivage (spec, checklist, `.scratch/`)
- [ ] Chaque étape donne la commande exacte, l'endroit où la lancer et le résultat attendu (chiffres relevés en dev quand ils servent de repère)
