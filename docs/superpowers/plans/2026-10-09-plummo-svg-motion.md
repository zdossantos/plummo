# Découpage complet des Plummos et microanimations

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** animer indépendamment toutes les parties visibles des Plummos, avec des gestes naturels et une apparence au repos conservée.

**Architecture:** groupes SVG issus exclusivement des tracés existants, pivots dans le cadre de 512 × 512 et hiérarchie de transformations. Le compositeur assemble les accessoires avec leur membre ; Vue déclenche les gestes depuis les états confirmés du jeu, CSS anime les éléments sans agir sur les règles serveur.

**Tech Stack:** Vue 3, TypeScript, SVG, CSS, PHP 8.4 pour la génération des données des calques ; Bun 1.3.14 et Pest Browser pour les vérifications. Aucune nouvelle dépendance.

**Spec:** [Conception validée et précision du découpage complet](../specs/2026-10-09-plummo-svg-motion-design.md).

## Global Constraints

- Conserver les tracés, couleurs et accessoires actuels ; aucun redessin de mascotte.
- TV passive sans commande ni pagination ; aucun nouveau scroll sur téléphone ou TV.
- Les objets tenus suivent exactement leur main et leur bras, devant le visage pendant les gestes.
- Les mouvements n’empêchent aucune interaction et sont désactivés avec `prefers-reduced-motion: reduce`.
- Les aperçus du sélecteur et les listes de classement restent statiques.
- Tests exclusivement sur `plummo_testing` ; ne pas modifier la modification locale de `compose.yaml`.

## Review Focus

- Chapeaux couvrant les plumes : les groupes animés ne doivent pas faire réapparaître les plumes cachées.
- Objet à deux calques : ses segments ne se séparent pas pendant un geste et la main garde sa prise.
- Huit instances : chaque référence SVG résout le dégradé ou masque de son propre personnage.
- Rafraîchissement, reconnexion, nouvelle manche : un ancien geste ne redémarre pas.
- Réduction des mouvements et petit écran : aucune animation active, état lisible, aucune zone interactive masquée.

## Tâche 1 — Toutes les pièces et leur assemblage

**Files:** modifier `public/plummo/base.svg`, `resources/js/lib/plummo.ts`, `resources/js/components/PlummoAvatar.vue`, `public/plummo/README.md` ; créer `scripts/build-plummo-rig.php` et `public/plummo/rig.json` ; tester dans `tests/Frontend/plummo.test.ts` et `tests/Browser/PlummoMotionTest.php`.

**Interfaces:** conserver `composePlummo(colorId, selected, catalog, parts, prefix): string` pour les usages statiques. Ajouter `composeAnimatedPlummo(colorId, selected, catalog, parts, rig, prefix, foreground): string`, où `rig` contient les fragments SVG nommés, leurs parents et pivots ; `foreground` choisit l’ordre des bras au repos ou en geste. Les fragments proviennent de fichiers du dépôt, jamais d’une saisie utilisateur.

- [ ] Écrire les tests : toutes les parties de la spec sont adressables ; la recoloration et les chapeaux fonctionnent ; les huit objets de main appartiennent au bras droit avec leurs deux segments ; aucune référence ne croise deux instances.
- [ ] Exécuter `bun test tests/Frontend/plummo.test.ts` et vérifier l’échec pertinent sur les groupes ou l’assemblage encore absents.
- [ ] Nommer les éléments SVG et les groupes parents sans changer les tracés. Séparer les deux pieds, les trois plumes, les reflets, les deux membres, chaque sourcil, œil, iris, reflet d’œil, joue, bouche et langue. Conserver l’ordre original entre surfaces qui se recouvrent.
- [ ] Générer les fragments et pivots depuis le XML avec `php scripts/build-plummo-rig.php` : pas d’extraction de groupes imbriqués par une expression régulière. Les parents coordonnent les détails ; l’ombrage et les reflets suivent leurs surfaces. Le bras et la main restent ensemble lorsque le tracé actuel dessine un membre continu.
- [ ] Implémenter le compositeur animé, avec ordre de repos identique à l’actuel et ordre de geste au premier plan. Conserver l’unicité des identifiants et leurs références ; réutiliser la sélection par zone et la suppression des plumes sous les chapeaux.
- [ ] Vérifier les tests et les images de repos pour six couleurs et les 28 accessoires. Comparer ancien compositeur statique et nouveau compositeur au repos, y compris une combinaison complète ; vérifier aussi huit instances simultanées.
- [ ] Commit `feat: split Plummo SVG into independently animated parts`.

## Tâche 2 — Gestes coordonnés et déclencheurs

**Files:** créer `resources/js/lib/plummo-motion.ts`, `resources/js/composables/usePlummoMotion.ts`, `resources/css/plummo-motion.css` ; modifier `resources/js/components/PlummoAvatar.vue`, `PlayerDock.vue`, `GameWinners.vue`, `resources/js/pages/rooms/Join.vue` et `resources/css/app.css` ; tester dans `tests/Frontend/plummo-motion.test.ts` et `tests/Browser/PlummoMotionTest.php`.

**Interfaces:** `PlummoMotion = 'idle' | 'answer' | 'points' | 'resume' | 'podium'` ; `PlummoAvatar` reçoit `motion?: PlummoMotion` et `motionKey?: string`. Une clé représente un événement confirmé, pas la date d’une relecture. `usePlummoMotion` conserve les clés consommées pendant sa vie, annule ses minuteries à la destruction et rend les bras au repos à la fin du geste. L’absence de `motion` laisse l’avatar statique.

- [ ] Écrire les tests de déduplication : une même réponse confirmée ou révélation relue ne rejoue pas le geste ; un nouvel événement le joue ; une ancienne révélation après reconnexion n’est pas célébrée ; un changement de manche annule le geste précédent.
- [ ] Exécuter `bun test tests/Frontend/plummo-motion.test.ts` et constater l’échec sur la gestion des événements absente.
- [ ] Implémenter les états et gestes : respiration du corps avec clignement des yeux en attente ; signe du bras après réponse enregistrée ; geste des bras au gain de points ; signe à la reprise ; victoire au podium. Réutiliser `roundCelebration` pour les gains, sans modifier leurs règles ni leur affichage.
- [ ] Employer des courbes progressives, petites amplitudes et légers décalages entre parties liées. Les sourcils et la bouche peuvent accompagner les gestes ; les pieds soutiennent le mouvement, les plumes amortissent le retour. Ne pas animer tous les éléments en permanence.
- [ ] Relier les gestes aux projections serveur existantes : réponse de quiz/blind test, dessin trouvé, phrase envoyée ou vote confirmé. Ne jamais déduire une réussite privée sur le grand écran avant sa révélation. Garder les listes et l’atelier statiques.
- [ ] Ajouter la règle de réduction des mouvements, arrêter toutes les minuteries et conserver les indications textuelles. Vérifier que les événements ne dépendent pas de la fin d’une animation pour continuer le jeu.
- [ ] Vérifier les tests unitaires et navigateur, puis commit `feat: animate Plummo gestures from confirmed game events`.

## Tâche 3 — Revue visuelle et livraison

**Files:** compléter `tests/Browser/PlummoMotionTest.php`, `docs/validation-v1.md` et la tâche du backlog dans `docs/superpowers/plans/2026-10-07-suite-v1.md`.

**Interfaces:** scènes existantes de téléphone, grand écran, reprise et podium ; aucune nouvelle commande utilisateur.

- [ ] Capturer repos et gestes : bras avec chacun des huit objets, visage expressif, pieds et plumes ; vérifier les recouvrements et l’absence de déformation ou de discontinuité visuelle.
- [ ] Tester sur téléphone 390 × 844 et grand écran 1366 × 768, avec huit Plummos et bulles ; vérifier que les gestes ne masquent aucune commande et n’ajoutent pas de scroll. Vérifier `prefers-reduced-motion` dans Chromium et WebKit.
- [ ] Exécuter `composer ci:check`, la suite d’interface avec `vendor/bin/pest tests/Browser/PlummoMotionTest.php --browser safari`, et la matrice de viewport du dépôt. Consigner uniquement les sorties fraîches et distinguer clavier simulé et matériel réel.
- [ ] Mettre à jour le kit et le backlog selon ce qui est effectivement livré, puis commit `docs: record Plummo motion validation`.
- [ ] Pousser la branche, ouvrir une PR et reconstruire Docker pour la revue locale. Ne pas fusionner avant les contrôles et la revue du rendu final.

## État

Conception approuvée le 9 octobre avec la précision « toutes les parties » et mouvements naturels. Plan préparé pour revue ; aucune implémentation de ce découpage n’a encore été engagée.
