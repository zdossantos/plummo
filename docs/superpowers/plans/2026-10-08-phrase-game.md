# Phrase à compléter

**Goal:** livrer le quatrième mini-jeu selon les règles validées, sans nouvelle dépendance.
**Architecture:** service PhraseGame sous verrou RoomService, état JSON Game et projections privées ; moteur commun pour pause/reprise/arrêt, historique et classements. Vue réutilise GameSetup/GamePlay et ajoute PhrasePlay. Aucun nouveau schéma.
**Autorisation:** poursuite continue de la V1 et fusion après contrôles, confirmée le 8 octobre.

## Interfaces partagées

| Interface | Décision |
| --- | --- |
| POST phrases/{draft,submit,vote} | game_id + round obligatoires ; suffix (150 caractères Unicode) ou choice (identifiant opaque) |
| État public | writing/presenting/voting/reveal/results ; auteurs et votes uniquement à la révélation |
| État privé | brouillon propre, validation définitive, identifiant propre pour empêcher auto-vote ; aucune autre identité |
| Temps et scores | serveur ; présentation min(12,5+.05*max(0,L-40)), vote 30s, 65 points/vote ; attribution unique à la fin du vote |
| Participants | figés à chaque tour, minimum trois au lancement ; exclus jusqu'au tour suivant après départ/retour |

## Task 1: Moteur et contrat HTTP

Écrire les tests métier avant le service : minimum, paramètres, brouillon privé, validation, Unicode, délais, vote sans auto-vote, score unique, pause, départs, objectifs et épuisement. Constater RED puis implémenter PhraseGame et son contrôleur ; intégrer GameEngine et les routes. Vérifier les tests métier.

## Task 2: Parcours écran et téléphones

Ajouter les types, traductions FR/EN, configuration et PhrasePlay. Sauvegarder chaque texte dans une file sérialisée, sans remplacer le texte en cours de frappe par un ancien état ; validation attend les sauvegardes. Présenter anonymement, désactiver auto-vote, révéler auteurs/Plummos/votes/points. Test navigateur avec écran et trois téléphones. Vérifier types/build et tests.

## Task 3: Validation et livraison

Mettre à jour README et progression V1. Exécuter composer ci:check. Revue indépendante complète, corriger les défauts importants avec régression. Créer PR, attendre contrôles du dernier commit puis Squash & Merge. Mettre main à jour en préservant compose.yaml local.
