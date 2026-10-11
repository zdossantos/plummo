# Mini jeux Implementation Plan

> Exécution native dans cette session avec superpowers:executing-plans ; revue indépendante finale.

**Goal:** livrer les cinq épreuves de #52 dans un mode Mini jeux.
**Architecture:** service MiniGames sous GameEngine et verrou Room ; générateur MiniGameScenes ; scènes Vue synchronisées à l'heure serveur. Réutiliser réponses, scores, contrôle, bonus, mascottes et drawers.
**Tech Stack:** Laravel 13/PHP 8.4, Vue 3, Bun 1.3.14.
**Spec:** docs/superpowers/specs/2026-10-11-mini-games-design.md, validée avec toutes les épreuves cochées par défaut à la place des packs ; gobelets rapides et suivables.

## Contraintes
Télé passive, aucun scroll, textes FR/EN, aucun nouveau média ni migration, tests plummo_testing, aucune modification locale étrangère incluse.

## Review Focus
Secrets sur téléphone ; reprise au milieu d'un échange ; perte de joueurs avant une manche auteur ; double réponse à l'échéance ; dette de bonus entre observation et réponse.

## Tâche 1 : moteur et générateur
- [x] Tests Feature MiniGamesTest : lancement sans packs, sélection valide, rotation, projections privées, observation/reprise, auteur, score unique et effets compatibles ; constater l'échec.
- [x] Créer MiniGameScenes::generate(kind) et MiniGames::{start,tick,view,write,answer}; raccorder GameEngine/GameController/RoomChat et route d'écriture.
- [x] Vérifier les tests métier, corriger les cas limites.

## Tâche 2 : scènes et configuration
- [x] Types MiniGameState, choix des épreuves via drawer (toutes cochées, auteur filtré si <3 joueurs), traductions.
- [x] MiniGamePlay/MiniGameScene : mémoire, foule numérotée, maison, gobelets (six échanges de 700 ms séparés de 180 ms), auteur ; conserver animations pendant pause et réduire les mouvements selon préférence.
- [x] Intégrer bonus et classement commun ; tests frontend de trajectoires et temps de scène.

## Tâche 3 : validation et livraison
- [x] Tests navigateur télé/mobile et reprise vérifiée dans les tests métier ; contrôle lisibilité/no scroll.
- [x] Tests README, revue indépendante et corrections ; livraison par commit Conventional Commit.
- [ ] PR liée #52 et épreuves implémentées, CI verte puis Squash & Merge.

## Résultats de validation

- Tests métier ajoutés constatés en échec avant implémentation ; régressions retrait/retour et liste de participants reproduites avant correction.
- Suite complète : 399 tests, 5930 assertions ; après le dernier correctif, 16 tests métier ciblés, 1354 assertions.
- Tests frontend : 38 tests ; navigateur : cinq épreuves sur TV 1280×720 et téléphone 390×844, plus lancement réel des gobelets.
- Pint, PHPStan, lint, format, types, build et image Docker runtime vérifiés.
- Revue indépendante : exclusions réinitialisées entre manches, numéros pour homonymes et listes de participants normalisées après départs ; aucun blocage restant.
- Les modifications locales de Compose et du plan de suite V1 ne font pas partie de cette livraison.
