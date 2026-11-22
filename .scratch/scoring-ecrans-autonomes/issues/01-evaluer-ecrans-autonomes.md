# Évaluer des écrans de score et de shot-clock autonomes, reliés par Internet

Status: needs-triage
Type: research
Blocked by: —

## Besoin

Aujourd'hui, le tableau de score et le chronomètre de tir sont des fenêtres secondaires du PC de scoring, ce qui
impose des câbles vidéo vers chaque écran. Évaluer une solution où ces écrans sont **autonomes** (un navigateur, un
boîtier ou une TV connectée par filaire ou Wi-Fi) et suivent le match par Internet, comme les incrustations
(Mercure, état canonique `scoring_live_*`).

## Points à évaluer

- **Latence et précision du chrono** : le shot-clock tolère mal un décalage ; synchronisation d'horloge, état
  transmis (heure de départ et non secondes restantes), comportement en cas de coupure réseau.
- **Réseau du site** : Wi-Fi saturé ou absent, 4G, réseau local sans Internet (hub Mercure local ?).
- **Matériel** : navigateur plein écran, mini-PC, clé HDMI, mode kiosque ; appairage simple d'un écran à un terrain.
- **Sécurité** : lien d'affichage signé par terrain, en lecture seule.
- Réutilisation de la page d'incrustation paramétrée ([PAGE_INCRUSTATION.md](../../../DOC/specs/PAGE_INCRUSTATION.md)).

Attention : CLAUDE.md demande de ne pas ouvrir de nouveau lot scoring avant la validation R1/R2 de la checklist.

## Comments
