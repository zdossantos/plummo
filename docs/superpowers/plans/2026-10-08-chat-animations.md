# Chat et animations — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task.

**Goal:** Permettre aux joueurs en attente de discuter et célébrer les points/gagnants sans ralentir les jeux.
**Architecture:** Une bulle et sa date sur RoomPlayer, sous verrou du salon. Laravel déduit canChat de la projection privée actualisée ; Vue affiche le formulaire et expire les bulles avec l’horloge serveur. Le dock grand écran garde huit places en bas à droite ; les gains et gagnants utilisent les projections publiques existantes.
**Tech Stack:** Laravel 13 / PHP 8.4, Vue 3, Bun 1.3.14, Pest Browser.
**Spec:** docs/metier-plummo.md §§4 et 7 ; PRODUCT.md ; docs/technical-architecture.md.

## Global Constraints
- 80 caractères Unicode, cinq secondes de visibilité, trois secondes entre deux envois.
- Aucune action attendue : lobby/pause/reprise/révélation/résultats, réponse envoyée, mot trouvé, phrase validée, vote effectué, participant en attente. Un vote pour passer un dessinateur absent reste une action attendue jusqu’à validation.
- Auteur connecté uniquement ; contexte partie/manche obligatoire pour refuser les requêtes retardées. Aucun message ne passe dans les événements Reverb.
- Vue ne décide pas des permissions ; textes FR/EN, aucun service/dependency supplémentaire, verrou et requêtes sérialisées réutilisés.
- Animation non bloquante, réduction des mouvements respectée ; scores des quiz restent secrets avant révélation.
- Tests uniquement plummo_testing, migrations explicites ; aucun changement compose.yaml ni lockfiles.

## Review Focus
- Message retardé quand une nouvelle action commence : refus serveur même si formulaire ancien encore visible.
- Unicode, HTML et espaces : longueur correcte, texte rendu échappé, champ vide refusé.
- Présence perdue et retour : interdiction si déconnecté, participant attendant peut discuter.
- Seule phrase personnelle disponible : aucun vote possible, chat autorisé.
- Huit bulles simultanées et ex æquo : pas de chevauchement, tous les gagnants célébrés, aucune animation répétée par polling.

### Task 1: Autorisation et bulles serveur
**Files:** migration room_players, RoomPlayer, RoomService, ChatService, ChatController, routes, traductions, tests/Feature/Rooms/ChatTest.php.
**Interfaces:** Snapshot.canChat bool ; Player.chat {message:string,expiresAt:number}|null ; POST chat {message,game_id:int|null,round:int|null}.
- [x] Écrire tests lobby/limites/auth/expiration/cooldown et matrice des jeux/actions.
- [x] Vérifier RED via php artisan test tests/Feature/Rooms/ChatTest.php.
- [x] Implémenter et appliquer explicitement la migration sur plummo_testing.
- [x] Vérifier GREEN et commit feat: add waiting-time chat rules and expiring bubbles.

### Task 2: Téléphones et grand écran
**Files:** ChatComposer, PlayerDock, GameWinners, useRoom, types, Join, Screen, GamePlay, tests/Browser/ChatTest.php.
**Interfaces:** horloge serveur reactive, canChat réactif, sender sérialisé existant ; gain affiché une fois par révélation et gagnants ex æquo selon classement mini-jeu/global.
- [ ] Écrire test navigateur envoi, remplacement/expiration, verrou action et retour au formulaire, gagnants ; vérifier RED.
- [ ] Implémenter composants et styles reduced-motion ; tester front/build/navigateur.
- [ ] Mettre à jour README et suite-v1 puis commit feat: show chat bubbles and celebrate Plummo winners.

### Task 3: Vérification et livraison
- [ ] composer ci:check complet ; review indépendante selon skill requesting-code-review.
- [ ] Corriger tout défaut important avec RED/GREEN, refaire les contrôles affectés.
- [ ] Push branche, PR, attachement, contrôles GitHub, squash merge ; main à jour.
