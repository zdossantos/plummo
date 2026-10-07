# Salons Plummo

Périmètre autorisé dans la conversation : ouverture du grand écran, code/QR, huit places, entrée sans compte, prénom et Plummo composable, chef transférable, identité et points conservés au retour. Les règles détaillées restent dans `docs/metier-plummo.md`.

Laravel conserve salons et participants dans MySQL. Les mutations verrouillent le salon dans une transaction, notamment pour attribuer la dernière place et le rôle de chef. Une identité opaque conservée dans un cookie chiffré HttpOnly permet le retour depuis le même navigateur ; elle ne dépend jamais du pseudo et n'est pas exposée dans les listes publiques.

Le salon grand écran et le téléphone se synchronisent par requêtes toutes les cinq secondes. Un téléphone sans signe de vie pendant quinze secondes est déconnecté ; sa place reste réservée deux minutes. Un départ volontaire libère immédiatement la place. Le chef initial revient à son rôle après un relais automatique, sauf transfert volontaire. Un retour dans un salon plein attend une place sans perdre ses données. L'absence de tous les joueurs ferme le salon après trente minutes ; un ordonnanceur assure également le nettoyage des salons non consultés. La fermeture par le chef exige une confirmation et supprime les données.

Le catalogue SVG existant fournit six couleurs et 28 accessoires, deux maximum et un par zone. Laravel valide ces choix. Vue compose les calques arrière, base recolorée et calques avant, en retirant les plumes sous les chapeaux fermés et en isolant les identifiants SVG.

Le QR est produit localement avec BaconQrCode (SVG), sans service tiers. Son adresse provient d'APP_URL, qui doit être joignable par les téléphones. Le grand écran affiche aussi l'adresse de saisie manuelle.

Les mini-jeux, leurs pauses, les classements détaillés et le chat sont exclus de cette PR. Le score global est conservé et affiché, sans mécanisme de gain tant qu'aucun mini-jeu n'existe. Reverb/Echo reste une étape des interactions de jeu ; le salon fonctionne dès cette feature sans processus WebSocket supplémentaire.
