---
name: 'Plummo — interface de jeu'
description: 'Direction validée le 8 octobre 2026, intégrée aux parcours Vue et conservée dans public/maquettes.'
colors:
    world: '#302143'
    paper: '#fff7e6'
    lime: '#efe681'
    purple: '#aa83ec'
    mint: '#b7e1c3'
    pink: '#f1b5c8'
    ink: '#21182f'
    edge: '#180f26'
    muted: '#d0c0de'
    secondary-surface: '#4b365f'
typography:
    display:
        fontFamily: "Fredoka, 'Trebuchet MS', sans-serif"
        fontSize: 'clamp(32px, 4.2vw, 64px)'
        fontWeight: 600
        lineHeight: 1.08
        letterSpacing: '-0.02em'
    title:
        fontFamily: "Fredoka, 'Trebuchet MS', sans-serif"
        fontSize: '28px'
        fontWeight: 600
        lineHeight: 1.12
    body:
        fontFamily: "'Trebuchet MS', ui-rounded, system-ui, sans-serif"
        fontSize: '16px'
    prose:
        fontFamily: "'Trebuchet MS', ui-rounded, system-ui, sans-serif"
        fontSize: '18px'
        lineHeight: 1.5
    supporting:
        fontFamily: "'Trebuchet MS', ui-rounded, system-ui, sans-serif"
        fontSize: '16px'
        lineHeight: 1.4
    action:
        fontFamily: "'Trebuchet MS', ui-rounded, system-ui, sans-serif"
        fontSize: '16px'
        fontWeight: 800
rounded:
    field: '12px'
    answer: '16px'
    answer-mobile: '14px'
    circle: '50%'
spacing:
    small: '8px'
    compact: '10px'
    medium: '12px'
    regular: '16px'
    roomy: '20px'
    large: '24px'
components:
    button-primary:
        backgroundColor: '{colors.lime}'
        textColor: '{colors.ink}'
        typography: '{typography.action}'
        rounded: '{rounded.field}'
        padding: '13px 22px'
    button-secondary:
        backgroundColor: '{colors.secondary-surface}'
        textColor: '{colors.paper}'
        typography: '{typography.action}'
        rounded: '{rounded.field}'
        padding: '13px 22px'
    button-danger:
        backgroundColor: '{colors.pink}'
        textColor: '{colors.ink}'
        typography: '{typography.action}'
        rounded: '{rounded.field}'
        padding: '13px 22px'
    answer:
        backgroundColor: '{colors.purple}'
        textColor: '{colors.ink}'
        rounded: '{rounded.answer}'
        padding: '20px 54px 20px 20px'
    input:
        backgroundColor: '{colors.paper}'
        textColor: '{colors.ink}'
        typography: '{typography.body}'
        rounded: '{rounded.field}'
        padding: '14px 16px'
    reader:
        backgroundColor: '{colors.paper}'
        textColor: '{colors.ink}'
        rounded: '{rounded.answer}'
        padding: '24px'
---

# Design System: Plummo — interface de jeu

## Overview

**Creative North Star: "Le jeu de salon habité"**

Système issu des maquettes HTML de public/maquettes, validé le 8 octobre 2026 et intégré aux composants Vue. La demande de jeu, le rôle PC/scène et téléphone/manette, les Plummos existants libres en bas à droite et l’absence de scroll sont des décisions utilisateur. La palette, Fredoka, les silhouettes des commandes et leur relief sont la direction retenue pour les salons, jeux et formulaires d’administration.

Le monde construit associe un fond profond, des surfaces claires et des commandes tactiles à contours sombres. Les ombres pleines appartiennent ici au matériau de jeu proposé : elles soutiennent les réponses et les actions. Les personnages restent les assets existants ; la nouvelle direction porte leur environnement et leur composition.

**Key Characteristics:**

- Scène de jeu aubergine et commandes pastel contrastées.
- Titres ronds, commandes épaisses et chiffres faciles à repérer.
- Personnages existants réutilisés sans refonte de leurs SVG ni de leurs palettes catalogue.

## Colors

Une scène aubergine accueille des accents pastel et des textes crème ; les valeurs normatives sont dans le frontmatter.

### Primary

- **Citron** : actions principales, code d’entrée, chrono et score du personnage.
- **Aubergine** : fond continu de la scène.

### Secondary

- **Lilas** : première réponse et progression.
- **Menthe** : troisième réponse et statut.
- **Rose** : quatrième réponse et action de danger.
- **Surface secondaire** : commandes secondaires sombres.

### Neutral

- **Crème** : texte sur la scène, champs et lecteur.
- **Encre** : texte sur les surfaces pastel.
- **Bord profond** : contours et ombres structurelles.
- **Lavande atténuée** : indications secondaires sur la scène.

Les couleurs des personnages viennent du catalogue existant : ces accents d’interface ne remplacent pas leurs palettes.

## Typography

**Display Font:** Fredoka locale, avec Trebuchet MS et sans-serif en repli.
**Body Font:** Trebuchet MS, avec ui-rounded, system-ui et sans-serif en repli.

Les titres, logo, code de salon, grand score et choix de mini-jeu utilisent Fredoka (600). Les textes, réponses ordinaires et formulaires utilisent la pile de corps. La police locale est dans `public/maquettes/fonts/Fredoka.ttf` ; sa licence OFL accompagne l’asset.

### Hierarchy

Le frontmatter décrit les rôles observés sur PC. Le titre principal passe à 32px sur téléphone ; les titres secondaires à 24px et les paragraphes à 16px. Les petites hauteurs ont des réductions spécifiques documentées dans le CSS. Les chiffres de chrono et scores utilisent `tabular-nums`. Le code et le grand score utilisent les tailles fluides respectives `clamp(44px, 8vw, 104px)` et `clamp(72px, 11vw, 148px)`.

## Layout

Périmètre : `public/maquettes/`. L’application de démonstration occupe la hauteur visuelle ; document et panneaux ne défilent pas. La barre de galerie réserve 44px. Sur PC, la scène réserve 70px pour l’en-tête et 145px pour le pied, autour d’un centre flexible ; ses marges sont `clamp(20px, 5vw, 80px)`.

À 760px et moins, la scène réserve 60px et 94px, avec des marges de 18px. Une manette consultée sur PC est contenue dans 520px. Les media queries de hauteur (850px, 720px, 650px et 450px) compactent les scènes et masquent les éléments facultatifs. Les réponses gardent une grille de deux colonnes. Les étapes, pages et lecteurs dédiés préservent le contenu long sans ascenseur.

Les Plummos sont positionnés en groupe libre dans le pied, alignés à droite et en bas, avec pseudo et score ; aucune carte ne les entoure. Le personnage solo reprend la même logique. Les dimensions précises dépendent du viewport, sans transformation des SVG sources.

## Elevation & Depth

Les ombres pleines donnent aux commandes une épaisseur de pièce de jeu. Le contour sombre reste net. Elles ne constituent pas une règle applicable aux autres surfaces de Plummo.

### Shadow Vocabulary

- **Action** : `0 5px 0 var(--edge)`.
- **Réponse et dessin** : `0 6px 0 var(--edge)`.
- **Réponse mobile** : `0 4px 0 var(--edge)`.
- **Palette et action compacte** : `0 3px 0 var(--edge)`.
- **Personnage** : `drop-shadow(0 4px 0 #180f2644)`.

Une pression déplace le bouton de 3px et supprime son ombre ; le survol applique une luminosité de 1.06. Le focus porte un contour citron de 3px décalé de 4px. Une célébration anime les personnages une fois en 0.7s avec `ease-out` ; la préférence de mouvement réduit supprime animations et transitions.

## Shapes

Commandes et champs ont des coins arrondis ; réponses et surfaces de lecture sont plus larges et plus rondes. Les lettres de choix et le chrono sont circulaires. Les contours des boutons et champs sont de 2px ; la zone de dessin porte 3px. La bulle est asymétrique (`14px 14px 2px 14px`). Ces formes encadrent les interactions ; elles n’encadrent pas les Plummos.

## Components

### Buttons

Commandes épaisses et lisibles, citron pour l’action principale, sombre pour le secondaire, rose pour le danger. Hauteur minimale de 48px sur le style de base. Le disabled réduit l’opacité à 0.55, supprime l’ombre et neutralise le curseur de commande. Les états hover, active et focus sont décrits dans Elevation & Depth.

### Answers

Les réponses ont une seule face colorée, un texte centré et une ombre pleine. La séquence jaune, rose, vert, violet se décale sur les réponses suivantes : les huit réponses répartissent les quatre couleurs dans chaque colonne. Quatre compositions SVG occupent les bords : vagues, diagonales, courbes et arcs. Des aplats papier à 55 %, des zones sombres et des points contrastés donnent une identité visible à chaque réponse ; le centre reste dégagé pour la lecture. La lettre apparaît dans une petite bulle crème en haut à gauche ; sa taille diminue en faible hauteur. La commande « Lire », avec une icône Lucide, apparaît uniquement lorsque le texte est tronqué.

### Inputs / Fields

Champs crème, texte encre et contour sombre. Labels au-dessus, espacés ; focus commun aux commandes. La saisie longue se fait en portions conservées dans le brouillon, sans défilement interne.

### Cards / Containers

Le lecteur crème est une surface fonctionnelle paginée, avec commandes persistantes. Le canvas partage son matériau clair et un contour plus épais. Les surfaces ne servent pas de cages aux personnages.

### Navigation

L’en-tête de jeu associe logo citron, libellé secondaire et code du salon. La barre technique supérieure et le menu « Écrans » relèvent de la galerie de démonstration, pas de la navigation du produit.

### Chrono et troupe

Le chrono est un anneau citron portant un chiffre central. La troupe utilise les SVG du catalogue, quelques inclinaisons de composition et une célébration ponctuelle, sans altérer l’identité des personnages.

## Do's and Don'ts

### Do:

- **Do** appliquer les tokens communs aux interfaces Vue et conserver la galerie comme référence.
- **Do** conserver les SVG et palettes du catalogue des Plummos ; composer leur placement sans contenant individuel.
- **Do** préserver l’accès au texte intégral par pagination et une commande de lecture dédiée.
- **Do** respecter prefers-reduced-motion et conserver un focus visible.

### Don't:

- **Don’t** réintroduire une barre d’onglets en bas du téléphone : les commandes sont accessibles depuis le bouton de manette du HUD.
- **Don’t** introduire de scroll de document ou de panneau dans ces maquettes.
- **Don’t** placer les personnages dans des cartes, cases ou bordures individuelles.
- **Don’t** étendre les contrôles de galerie au produit.

## Retouches après essai sur téléphone

Les champs utilisent le fond crème et une encre sombre, y compris les champs portant une classe de fond Tailwind. Tous les boutons ont une bordure et une ombre pleine de 6 px (4 px en faible hauteur), avec un état enfoncé au toucher. Salon et Session regroupent leurs actions sans pagination ; la prolongation ouvre un drawer shadcn Vue. La connexion rassemble ses champs lorsque la hauteur disponible le permet. La pagination reste réservée aux listes et aux contenus qui dépassent la place disponible.

Les contrôles de formulaire partagent une hauteur minimale de 44 px, une police de 16 px, une bordure de 2 px et un rayon de 14 px. En faible hauteur, le minimum commun passe à 32 px. Les libellés longs peuvent prendre deux lignes ; les zones de texte et commandes illustrées gardent la place nécessaire à leur contenu.

La préparation du mini-jeu rassemble type, packs, manches, durée, répétitions et lancement sur un écran. Le choix des packs ouvre un drawer shadcn Vue ; la liste seule est paginée si nécessaire. Les quatre contrôles partagent une grille et une hauteur commune, malgré les libellés sur deux lignes. Lorsque le lancement est impossible, le message utile prend la place de l’estimation de partie.

Les champs et sélecteurs reprennent le relief des commandes de jeu : base pleine, face crème et typographie Fredoka. Le sélecteur possède une commande violette avec chevron dessiné. Les cases à cocher ont le même contour, une base pleine et une coche sur fond jaune ; le focus clavier garde un halo jaune visible. Les hauteurs et l’alignement restent communs, avec un relief réduit en faible hauteur.

Sur téléphone, les scènes abandonnent le grand panneau arrondi. Un fond profond, quelques points lumineux et un sol en diagonale constituent le décor commun. Les titres de scène portent un bandeau découpé ; les contrôles restent directement dans cet univers. Les drawers gardent leur structure. Le grand écran partage la scène ouverte : aucun panneau en fond, un sol diagonal et des motifs SVG géométriques aux bords. Leur dérive lente reste hors des zones de lecture et respecte la réduction des animations.


## Réponses, reprise et podium

La présence en arrière-plan ne désactive pas les commandes. Une action du joueur remplace la requête de présence en cours ; une ancienne réponse ne remplace jamais son nouvel état. Les réponses verrouillées conservent leur couleur ; leur sélection et les messages de confirmation expliquent l’état.

La reprise dispose d’un grand compte à rebours citron au centre de la scène. Le classement affiche une estrade continue à trois hauteurs dans l’ordre 2–1–3, avec les Plummos posés sur les marches et leur identité sur la face. Les rangs ex æquo sont conservés ; les marches vides restent visibles. Les joueurs suivants ont une liste paginée seulement si elle dépasse la place disponible. Aucun écran intermédiaire n’est ajouté pour les résultats.

Une commande Bulle avec icône et petit libellé reste visible dans le HUD du téléphone. Elle donne accès aux messages ou explique qu’une action de jeu doit d’abord être terminée ; les autorisations Laravel restent appliquées.

Le grand écran est strictement passif : aucun contrôle, lien interactif, sélecteur de thème, lecteur de texte ou pagination. Les résultats affichent uniquement le podium des trois premiers ; les détails et le classement complet restent sur les téléphones. La lecture audio démarre automatiquement. Si le navigateur interdit l’autoplay, un statut explique le réglage à autoriser sans ajouter une commande de jeu au grand écran.
