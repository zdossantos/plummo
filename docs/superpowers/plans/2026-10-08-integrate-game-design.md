# Intégration de la DA jeu — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Intégrer les maquettes validées dans les parcours Vue réels, sans défilement.
**Architecture:** Conserver les contrats Laravel et les mutations existantes. Partager coque de viewport, pagination et lecteur de texte ; utiliser les sources officielles Shadcn Vue Drawer et le catalogue SVG existant.
**Tech Stack:** Laravel 13, Vue 3, Inertia 3, Tailwind 4, Reka/shadcn-vue.
**Spec:** docs/design/maquettes-html.md et DESIGN.md ; validation utilisateur du 8 octobre.

## Global Constraints
- Textes FR/EN via Inertia ; mascotte et assets inchangés.
- Quatre accessoires combinables, un par emplacement ; aucune règle métier déplacée vers Vue.
- PHP 8.4, Bun 1.3.14, lockfiles conservés ; tests exclusivement plummo_testing.
- Aucun scroll, pagination/étapes, huit réponses simultanées au blind test.
- Ne pas modifier ni inclure compose.yaml déjà modifié par l’utilisateur.

## Review Focus
- Clavier mobile réduisant le viewport : commandes accessibles.
- Texte long et listes sans limite : intégralité consultable par pages.
- Autosave phrase et dessin : aucune perte liée au changement de vue.
- Audio TV en pause/classement : élément conservé.
- Droits chef, erreurs serveur et reconnexion : contrats existants conservés.

### Task 1: Socle et vestiaire
Files: resources/css/app.css, resources/js/components/ui/drawer/*, PlummoPicker.vue, lib/plummo.ts, tests/Frontend/plummo.test.ts, lang/*/interface.php.
Interfaces: randomAppearance(Catalog, random) -> { color, accessories }; ViewportShell et PageControls partagés.
- [x] Ajouter test aléatoire quatre zones, constater échec, implémenter et vérifier.
- [x] Intégrer sources officielles Drawer ; vestiaire silhouette et pagination.
- [x] Coque responsive et tokens validés ; types/lint/build.

### Task 2: Salon et jeux
Files: pages/rooms/*, components/PlayerDock.vue, GamePlay.vue, GameSetup.vue, SessionSettings.vue, RoomRanking.vue, PhrasePlay.vue, DrawingPlay.vue.
Interfaces: mêmes props/événements/actions serveur ; vues explicites et pagination locale.
- [x] Remplacer le flux vertical par scènes, navigation et étapes.
- [x] Conserver huit choix blind simultanés, texte intégral via lecteur ; scores historiques paginés.
- [x] Vérifier jeux, configuration, reconnexion, mobile et TV avec données longues.

### Task 3: Administration et validation
Files: components/AdminLayout.vue, pages/admin/*, tests/Browser/*, docs/design/maquettes-html.md.
Interfaces: formulaires Inertia et URLs conservés ; listes et formulaires paginés.
- [x] Adapter listes et formulaires aux étapes sans scroll.
- [x] Exécuter contrôles README, vérifications navigateur FR/EN et géométrie.
- [x] Revue indépendante, corrections, commit et mise à jour PR 25.

## Résultat et décisions

Intégration terminée le 8 octobre 2026. Suite complète : 157 tests, 1 677 assertions, 120,69 s ; frontend : 8 tests, 34 assertions. TypeScript, ESLint, Prettier, Pint, PHPStan et builds Vite/Docker réussis. ESLint conserve l’avertissement existant de PlummoAvatar sur v-html.

Revue indépendante réalisée ; les quatre corrections importantes sont vérifiées : curseur de saisie paginée, lecture intégrale des textes larges, annulation des éditions admin, connexion en faible hauteur. Aucun point mineur différé consigné. Les matrices FR/EN couvrent PC, téléphone, paysage et hauteur réduite. Le clavier sur appareil réel reste à essayer.

Décisions prises pendant l’exécution :
- Conserver la branche et la PR existantes, avec un commit commun pour les composants partagés. Si cette organisation convient mal à la revue, le coût est de découper ce commit. compose.yaml appartient à l’utilisateur et est exclu.
- Utiliser les quatre emplacements permis par Laravel dans le vestiaire réel, malgré la limite historique de deux accessoires dans la galerie. Si cette interprétation devait changer, ajuster uniquement la sélection UI.
- Séparer les builds Vite des tests navigateur : un serveur de test peut conserver les anciens noms de bundles. Le coût est une exécution séquentielle plus longue.

Les fixtures temporaires ont été retirées par identifiants exacts et vérifications de leurs relations. Le helper de vérification refuse toute base autre que plummo_testing et vérifie le serveur par un compte temporaire exclusivement présent dans cette base.

## Ajustements après essais téléphone

Les champs partagent un fond papier lisible, des dimensions de 44 px (32 px en hauteur réduite), une police de 16 px et les mêmes espacements que les boutons. Tous les boutons ont une ombre pleine et un état enfoncé. La navigation par onglets est remplacée par une commande dans le HUD ouvrant le Drawer Shadcn Vue. Salon et Session présentent leurs actions ensemble ; prolonger ouvre un drawer dédié. La connexion regroupe les champs lorsque la place suffit et conserve le champ actif lorsque le viewport rétrécit.

Validation finale : 158 tests Laravel/Pest, 1 682 assertions, 126,89 s ; frontend : 8 tests, 34 assertions. Types, ESLint (avertissement PlummoAvatar préexistant), Prettier, Pint, PHPStan et builds passent. Matrices de géométrie : 143 contrôles joueurs FR sur quatre formats et 144 contrôles administration FR/EN sur six formats, sans débordement ; contraste des champs et relief des boutons contrôlés. Le défaut du champ actif a été reproduit par un test en échec, puis corrigé et vérifié dans la suite finale. Docker reconstruit, champs et bouton de connexion mesurés à 44 px et réduction de hauteur vérifiée sur le runtime.

Aucun changement au catalogue ni aux assets des Plummos. compose.yaml reste une modification utilisateur exclue du commit.

## Préparation regroupée et scène mobile

La préparation réunit les quatre contrôles dans une grille commune : deux colonnes sur téléphone, quatre sur PC. Les packs se choisissent dans le Drawer Shadcn Vue. Le formulaire transmet les mêmes réglages Laravel et conserve les contraintes de lancement. Les libellés sur deux lignes ne décalent plus les champs ; le type et le nombre de packs restent lisibles. Si le lancement est impossible, le message d’attente remplace l’estimation.

Les champs et sélecteurs ont une base pleine, une face crème et la police Fredoka commune aux boutons. Le chevron occupe une commande violette ; les cases à cocher reprennent les mêmes contours et une coche jaune. Sur mobile, le grand panneau arrondi disparaît au profit d’un décor commun avec points lumineux, sol en diagonale et bandeaux de titre.

Le nouveau test a d’abord échoué sur les quatre formats car les réglages étaient masqués par les étapes. Les contrôles de géométrie vérifient maintenant l’alignement des champs, les régions titre/réglages/lancement et les débordements internes. Le salon en attente de joueurs a également reproduit un débordement en hauteur réduite, corrigé par l’affichage du message utile à la place de l’estimation.

Validation des retouches : 13 tests navigateur réussis, 253 assertions, 77,24 s. Les matrices FR/EN comptent 358 contrôles après la refonte des champs sur quatre formats, 186 contrôles après le décor mobile et 42 contrôles supplémentaires de préparation/attente en 320 × 400. Captures des petits écrans, du téléphone et du PC inspectées visuellement. Types, ESLint (avertissement existant PlummoAvatar), Prettier, Pint, Vite et Docker passent. Les sources finales du formulaire, du CSS et des traductions françaises ont les mêmes empreintes dans Docker et dans le workspace ; le runtime répond HTTP 200.


## Réponses et affichage passif après essais

Les propositions ont une seule face colorée avec des compositions SVG, une lettre dans une bulle en coin et une séquence jaune, rose, vert, violet. Sur huit réponses, les colonnes contiennent les quatre couleurs. Le podium possède trois hauteurs réelles et une base commune ; les Plummos restent inchangés. Le compte à rebours de reprise est central et agrandi. Bulle est toujours accessible dans le HUD téléphone. Les lecteurs utilisent une icône SVG et un libellé court, uniquement lorsqu’un texte déborde.

La présence en arrière-plan ne verrouille plus les boutons. Un test a reproduit le clignotement ; les tests de concurrence vérifient maintenant le remplacement des requêtes et le rejet des réponses périmées. Frontend : 10 tests, 47 assertions.

Le grand écran ne contient aucune commande, aucun lien interactif ni pagination. Il affiche le podium des trois premiers et laisse les détails au téléphone. Le panneau violet disparaît au profit du décor commun, avec arcs, losanges et points SVG aux bords, en mouvement lent respectant la réduction des animations. L’audio est automatique ; son autorisation reste soumise aux réglages du navigateur.

Validation navigateur : huit scénarios admin, messages et jeux ont réussi lors du contrôle général ; les douze scénarios restants ont réussi après adaptation des tests au grand écran passif (194 assertions). Les matrices FR/EN ont passé 99 et 92 contrôles sur téléphone, hauteur réduite, paysage et PC ; le grand écran passif a passé 44 contrôles supplémentaires. Les assertions vérifient la palette, les trois hauteurs du podium, l’absence de clignotement des boutons et l’absence de commandes sur le grand écran. Types, ESLint (avertissement PlummoAvatar existant), Prettier et Pint passent. Builds Vite et Docker réussis. compose.yaml reste exclu.

Décor télé final : 90 contrôles supplémentaires réussis en français sur 1440 × 900 et 390 × 844, avec vérification du fond transparent et des motifs SVG. Capture télé inspectée ; sources CSS, shell et Screen identiques dans Docker et le workspace, runtime HTTP 200.
