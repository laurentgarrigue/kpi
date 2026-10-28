# 01: Import PCE depuis un fichier local

**What to build:** l'exploitant (et les tests) peuvent lancer l'import PCE sur un fichier PCE local au lieu du téléchargement depuis le serveur fédéral ; hors la source du fichier, le traitement est strictement identique. Ce ticket pose le point d'entrée des tests de l'import : un petit fichier PCE de fixture et un premier test d'intégration de la commande, sur le modèle du test de la reprise WordPress (commande lancée par le testeur de commandes Symfony, état de la base vérifié dans une transaction annulée).

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] La commande d'import accepte une option de fichier local ; sans l'option, elle télécharge le PCE comme aujourd'hui
- [ ] Un fichier inexistant ou vide fait échouer la commande avec un message explicite, sans rien écrire en base
- [ ] Un fichier PCE de fixture (quelques licenciés, Clubs, CD et CR) est ajouté aux fixtures de test
- [ ] Un test d'intégration importe ce fichier et vérifie que ses licenciés sont présents en base pour la saison du fichier
- [ ] La suite de tests api2 et PHPStan passent
