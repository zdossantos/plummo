# Catalogue bonus #56 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Intégrer les 30 nouveaux objets retenus dans #56, avec protections, redistribution et animations lisibles.

**Architecture:** Laravel possède un catalogue compatible par jeu, applique les cibles/protections et borne les changements temporels. Les snapshots privés exposent les effets pertinents ; Vue applique uniquement les effets de présentation et les transformations du lecteur/audio/dessin. Les identifiants de manche et événements empêchent les doubles usages et rejeux à la reconnexion.

**Tech Stack:** Laravel / PHP 8.4, Vue / TypeScript, Bun 1.3.14, MySQL plummo_testing.

**Spec:** https://github.com/zdossantos/plummo/issues/56 (catalogue et exclusions validés).

## Global Constraints
- Deux emplacements et un troisième objet en attente ; clic immédiat sans ciblage manuel.
- TV passive sans pagination ni scroll ; les bulles interactives sont sur téléphone uniquement.
- Textes FR/EN partagés via Inertia ; aucun changement de saisie avant validation.
- Pas de malus permanent, animations réduites accessibles ; pas de modification des traits existants.
- Tester uniquement sur plummo_testing ; aucun secret, conserver les locks.

## Review Focus
- Un bouclier/reflet ne protège qu'une fois ; aucun cycle de boomerang.
- Une défense reste utilisable après une attaque, sous cadenas ou grève.
- Pause/reconnexion : effets et chrono cohérents, aucun événement rejoué.
- Temps emprunté remboursé une fois ; dernier souffle déclenché une fois ; plancher et plafond cumulatifs.
- Effet sur le dessinateur protégé : le serveur et son aperçu montrent la même géométrie.

### Task 1: Catalogue, protections et poches
**Files:** app/Services/BonusCatalog.php, GameBonuses.php ; tests/Feature/Rooms/BonusGameTest.php ; types/rooms.ts.
**Interfaces:** kinds(type): list<string>, defensive(kind): bool ; effets target/actor/start/expiry et canUse par objet.
- [x] Écrire tests bouclier à usage unique, reflet sans chaîne, savon, défense après attaque, cadenas, piñata et redistribution.
- [x] Exécuter en échec puis implémenter catalogue et distribution : un objet pour tous à chaque manche, un objet supplémentaire aux retardataires si pas d'objet en attente ; ne jamais écraser le troisième.
- [x] Vérifier API, confidentialité, incompatibilités et pause.

### Task 2: Temps et transformations métier
**Files:** BonusClock.php, GameEngine.php, GameBonuses.php, DrawingGame.php ; tests Feature Rooms.
**Interfaces:** clock tick(game): state ; delta = min(20,max(6,round(duration×0.2))), cumul +/- min(30,duration×0.5), plancher 8 s, pas d'action sur résultats/présentation.
- [x] Tester gel, prolongations, dette prochaine manche, dernier souffle à 2 s, pause, toutes les fins anticipées et phrases transformées sans toucher aux brouillons.
- [x] Implémenter horloge serveur et transformations nouveaux traits ; exclure l'emprunt à la dernière manche.
- [x] Vérifier avant de passer aux vues.

### Task 3: Présentation, animations et sons
**Files:** BonusEffects.vue, BonusPocket.vue, BonusIcon.vue, GamePlay.vue, DrawingBoard.vue, DrawingPlay.vue, AudioClip.vue, lib/bonuses.ts, lib/soundscape.ts, css, lang/fr/rooms.php, lang/en/rooms.php.
**Interfaces:** effets privés pour téléphones, effets partagés audio/miroir/horloge pour TV ; inventory canUse par item, masqué/caché temporairement sans perte.
- [x] Tester les transformations frontend et la restauration audio ; implémenter bulles éclatables, photobomb, gelée sur contenu sans hitbox mobile, rideau en deux clics, mot mangé, alphabet et chrono masqué/gelé.
- [x] Donner aux objets des icônes distinctes ; anims bouclier cassé/reflet/savon, badges et anneaux de durée, launch/receipt sans rejeu, sons discrets.
- [x] Tester mouvements réduits, téléphones/TV, pause, expiration et confort de lecture.

### Task 4: Validation et livraison
- [x] Mettre à jour docs métier/compatibilité et issue.
- [x] Exécuter les validations README (tests métier/frontend, Pint, PHPStan, lint, format, types, build).
- [x] Revue indépendante et corrections.
- Livraison : PR puis CI verte et squash merge.
