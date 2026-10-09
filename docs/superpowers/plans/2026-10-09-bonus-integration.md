# Bonus Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Intégrer les huit bonus collectifs validés, leur poche et leur réception animée.
**Architecture:** GameBonuses porte l’état métier dans Game.state, sous verrou Room. Vue consomme une projection privée et des événements publics bornés ; les identifiants des réponses ne changent jamais.
**Tech Stack:** Laravel 13 / PHP 8.4, Vue 3 / TypeScript, Bun 1.3.14.
**Spec:** ../specs/2026-10-09-bonus-integration-design.md

## Global Constraints

Deux objets, un lancement par manche, option désactivée par défaut, aucun bonus solo. Attribution aux retardataires stricts entre manches. FR/EN, aucun scroll, grand écran passif, aucun secret ni nouvelle dépendance. Tests exclusivement plummo_testing ; aucune migration implicite.

## Review Focus

Double appui et requête périmée ; pause/reprise et expiration ; arrivant tardif et joueur exclu ; anonymat des textes ; choix stables après mélange.

### Task 1: État serveur et attribution

Files: app/Services/GameBonuses.php, GameEngine.php, PhraseGame.php, DrawingGame.php ; tests/Feature/Rooms/BonusGameTest.php.
Interfaces: GameBonuses::transition(Game,array,string): array initialise/porte l’état à la manche suivante ; GameBonuses::view(Game,?RoomPlayer,bool): array projette les objets privés et événements utiles.
- [x] Tests RED : option, solo, poche initiale, rattrapage strict, poche pleine, fin.
- [x] Implémenter catalogue et transition dans GameEngine::save, sans changer les règles de score.
- [x] Tests GREEN et régressions des quatre jeux.

### Task 2: Lancement et effets

Files: app/Http/Controllers/Rooms/BonusController.php, routes/web.php, GameBonuses.php, PhraseGame.php ; même fichier de tests.
Interfaces: GameBonuses::use(Room,RoomPlayer,int,int,string): void, POST /rooms/{code}/bonuses (game_id,round,item_id).
- [x] Tests RED : consommation unique, adversaires, périmé, pause, inéligible, expiration ; phrases originales privées et effets cumulés stables.
- [x] Implémenter validation serveur et transformations post-validation.
- [x] Tests GREEN, projection sans fuite, compatibilité des modes.

### Task 3: Poche et scènes Vue

Files: resources/js/components/BonusPocket.vue, BonusEffects.vue, BonusReception.vue, GamePlay.vue, GameSetup.vue, DrawingPlay.vue, DrawingBoard.vue, pages/rooms/Join.vue, Screen.vue, types/rooms.ts, lang/{fr,en}/rooms.php, resources/css/app.css.
- [x] Tests unitaires RED du mélange stable et de la sélection des effets.
- [x] Option, poche, info non consommatrice et lancement direct ; effets locaux fondés sur l’heure serveur ; réception unique TV/téléphone.
- [x] Tests GREEN, types, format/lint, build ; vérification navigateur petit téléphone et TV, mouvements réduits.

### Task 4: Livraison

- [x] Exécuter les commandes README, corriger les régressions et relire le diff complet.
- [x] Actualiser documentation, commits Conventional ; préparer une PR reviewable. Ne pas fusionner sans demande de livraison.

### Ajout validé pendant l’intégration : sons des malus

Signatures Web Audio courtes pour les huit objets, diffusées par le grand écran avec le volume existant et son atténuation pendant le blind test. Événements publics uniques, bornés huit secondes ; déduplication et reconnexion silencieuse. Tests du catalogue sonore, du contrat serveur et de la lecture réelle dans le navigateur.

### Ajout validé : poche pleine et nouveaux jeux

Une troisième attribution reste privée en attente. Drawer de remplacement d’un objet, refus ou choix différé ; promotion automatique si un lancement libère une place. Validation sous verrou du joueur, jeu, manche et des objets. Chaque nouveau mini-jeu doit intégrer les bonus/malus existants compatibles, règle inscrite dans AGENTS.md et la documentation métier.

### Vérification finale

188 tests PHP/métier/navigateur réussis (2094 assertions), puis test ciblé du drawer à 390 × 360 réussi (32 assertions). 21 tests frontend réussis ; Pint, PHPStan, ESLint, Prettier, TypeScript, Wayfinder, build Vite et Docker runtime vérifiés.
