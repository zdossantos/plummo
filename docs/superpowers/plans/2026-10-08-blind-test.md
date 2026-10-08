# Blind test — livraison V1

Autorisation : exécution native continue, PR dédiée puis Squash & Merge après contrôles verts. Métier : docs/metier-plummo.md §§5–6. Consommateur du moteur quiz existant ; ni reconnaissance de texte ni téléchargement externe de chansons.

## Design et interfaces

`GameEngine` conserve le cycle actuel pour les deux jeux de choix. Le type détermine le catalogue, les limites des choix (4/8) et la projection. `BlindChoices::prepare(Content $correct): array` produit `choices` (liste de chaînes titre/artiste) et `correct` (index), sans exposer identifiants sources. Sept faux choix publiés et distincts, partageant au moins un tag d’abord, puis complétion globale. Dédupliquer les couples titre/artiste y compris les doublons de la bonne réponse. Les distracteurs n’entrent pas dans l’historique.

La partie copie chaque audio engagé sous un chemin privé `games/{game-id}/...` ; le contenu admin peut ensuite être modifié ou supprimé sans casser la manche. Une route audio de la partie/manche vérifie l’identité du grand écran propriétaire ; aucun chemin de stockage n’est projeté. Le salon supprimé nettoie les copies privées. Seul le grand écran reçoit l’URL audio. Audio suspendu hors réponse et en boucle pendant réponse ; bouton local de démarrage si le navigateur bloque l’autoplay.

GameSetup propose Quiz/Blind test et garde durées/quantités séparées. L’aperçu inclut disponibilité du pack et nombre de couples publiés distincts (minimum huit globalement). Les réglages de récupération conservent le type. GamePlay conserve les réponses/classifications actuelles et adapte son titre ainsi que l’audio TV. Textes lang/fr et lang/en.

## Pré-vol

| Producteur → consommateur | Contrat | Décision |
| --- | --- | --- |
| Catalogue → BlindChoices | Contenus publiés, tags et payload title/artist/audio | Réutiliser query ; proximité OR de tous les tags de la chanson indépendamment des packs sélectionnés. |
| Quiz → Blind test | Game JSON, answer game_id/round, scoring, pause, scheduler | Même orchestration ; types quiz/blind_test uniquement jusqu’à leurs successeurs. |
| Administration → copie audio | Suppression/remplacement nettoie le fichier source | Copie privée avant reveal ; éviter toute dépendance à la source pour une manche engagée. |

## Tâche 1 — Distracteurs et catalogue

- Écrire tests `BlindChoicesTest` : 8 couples distincts, priorité tags communs même hors packs choisis, repli global, doublons du correct exclus, brouillons exclus, catalogue <8 refusé.
- Exécuter `php artisan test tests/Feature/Rooms/BlindChoicesTest.php` : échec pertinent service absent.
- Implémenter `app/Services/BlindChoices.php` : `count(): int` et `prepare(Content $correct): array{choices:list<string>,correct:int}`.
- Rejouer : tous tests passent. Commit Conventional.

## Tâche 2 — Moteur et audio privé

- Tests `BlindGameTest` : lancement validation 5–30/10–150, huit choix cachant correct, scores identiques quiz, faux choix réutilisables et non vus, audio TV seulement/ancienne manche refusée, source supprimée sans perte, suppression salon nettoie copie, pause/reprise/récupération restent communes.
- Exécuter `php artisan test tests/Feature/Rooms/BlindGameTest.php` : échecs de type et route absente.
- Étendre GameController/GameEngine selon type ; `GameAudio` copie et supprime le dossier privé par salon. Autoriser seulement le screen_session existant sur route GET `rooms/{code}/games/{game}/rounds/{round}/audio`.
- Rejouer tests blind et quiz : tous passent. Commit Conventional.

## Tâche 3 — Écrans et validation

- Test navigateur : écran propriétaire, téléphone chef, choix Blind test, huit réponses initiales, audio loop, pause→audio suspendu, reprise cinq secondes, résultat puis retour salon conservant score.
- Étendre GameSetup/GamePlay/types/projections/traductions sans changer les parcours quiz.
- `composer ci:check` : tous tests, lint, analyse, types et builds passent ; image runtime construite. Revue indépendante de branche puis corrections RED→GREEN, suite complète.
- Documenter README/architecture/journaux, PR attachée ; contrôle du dernier SHA puis Squash & Merge.

## Review Focus

Vérifier concurrence suppression source/copie, absence de chemins et d’URL audio sur téléphone, requêtes audio périmées, catalogue global insuffisant et couples dupliqués, reprise audio sans nouvelle lecture du début et répétitions qui ne marquent pas les distracteurs.
