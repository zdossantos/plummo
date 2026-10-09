# Plummos : éléments SVG et microanimations

## Intention et contraintes validées

Rendre les Plummos vivants dans l’interface de jeu sans changer leur dessin, leurs couleurs ni leurs accessoires. Les bras doivent pouvoir passer devant le visage ; les objets tenus suivent la main. La TV reste un affichage passif, sans pagination ni commande. Aucun mouvement ne bloque une action et aucun nouvel élément ne provoque de scroll.

## État actuel

`public/plummo/base.svg` contient déjà les pieds, les plumes, le corps et le visage, mais les deux mains et leurs reflets sont regroupés. `composePlummo` superpose tous les accessoires arrière, la base complète, puis tous les accessoires avant. Un objet tenu n’appartient donc pas au même groupe de transformation que son bras. Les fichiers source sont des SVG éditables : aucun redessin ni génération d’image n’est nécessaire.

`PlayerDock` déclenche déjà une célébration par manche via `roundCelebration`, avec une clé empêchant les relectures du salon de rejouer le gain de points. Ce déclencheur doit être réutilisé.

## Approche validée et précision du 9 octobre

Découper les tracés existants en groupes SVG nommés plutôt que multiplier les dessins par combinaison ou ajouter un moteur d’animation. Les coordonnées et les attributs graphiques restent ceux des sources. Le composant `PlummoAvatar` conserve son contrat actuel et reçoit un état de mouvement facultatif ; sans cet état, il reste immobile.

Le découpage concerne toutes les parties visibles, et pas seulement les mains : corps et ombrage, pied gauche et pied droit, chaque bras et sa main, chacune des trois plumes et ses reflets, sourcil gauche et sourcil droit, chaque œil (blanc, iris et reflet), joue gauche et joue droite, bouche et langue. Les tracés qui dessinent actuellement un membre d’un seul tenant restent disponibles comme un ensemble articulé ; leur segmentation ne doit pas inventer une nouvelle silhouette. Le visage constitue aussi un groupe parent pour coordonner naturellement ses détails.

Les accessoires de tête, de visage et de cou restent indépendants et suivent le groupe auquel ils sont attachés. Le bras droit contient, dans cet ordre, le segment arrière de l’objet, la main, puis le segment avant : les huit objets de main suivent ainsi exactement la même rotation et translation que la main. Les identifiants de dégradés, groupes et masques restent uniques par instance.

Chaque élément possède un pivot explicite et peut être animé indépendamment. Cette possibilité ne signifie pas que tout bouge simultanément : les gestes coordonnent les parents et leurs détails, avec de petites amplitudes, des accélérations progressives, un léger décalage entre anticipation et retour, et de brèves pauses. Les reflets suivent leur surface, les accessoires leur attache, les yeux clignent ensemble avec une légère variation de rythme. Le corps accompagne un signe du bras ou une victoire sans se déformer excessivement. Aucun mouvement aléatoire rapide ou tremblement permanent.

Au repos, conserver les recouvrements du rendu actuel. Pendant un geste, rendre le groupe du bras et de son objet après le corps, le visage et les accessoires portés pour qu’il passe réellement devant eux. Utiliser la même géométrie dans les deux ordres de rendu, sans dupliquer visuellement un bras. Revenir à l’ordre de repos à la fin du geste. Les pivots se situent à la jonction du bras et du corps et sont explicités dans le cadre commun de 512 × 512.

Les animations utilisent des transformations locales CSS, sans nouvelle dépendance, stockage ou règle serveur. La gestion des changements de phase et des minuteries reste dans les composants Vue existants. Un rafraîchissement de présence ne doit pas interrompre ni redémarrer un geste.

## Première intégration

- Attente : respiration légère du corps, à rythme lent, seulement pour les personnages de la scène de jeu.
- Réponse enregistrée : bref signe du bras du joueur, une fois par réponse confirmée par le serveur.
- Points gagnés : geste des bras associé à la célébration existante et à son affichage de points.
- Reprise : bref signe pendant le compte à rebours, une fois par reprise.
- Podium : geste de victoire à l’apparition du classement, sans masquer les pseudos, les scores ou les autres personnages.

Les aperçus du sélecteur et les listes de classement restent statiques. Les mouvements ont une amplitude bornée au cadre du personnage. Avec `prefers-reduced-motion: reduce`, tous ces mouvements sont désactivés ; les messages, points et états restent visibles. Les animations s’arrêtent à la destruction du composant et ne retardent jamais la prochaine manche.

## Vérifications requises

Comparer visuellement le rendu au repos avant/après pour toutes les couleurs et tous les accessoires, y compris les combinaisons couvrant les plumes. Tester les deux bras séparés, les huit objets solidaires de leur main et leur passage devant le visage. Vérifier l’unicité des références SVG avec huit personnages affichés ensemble.

Tester que chaque geste événementiel ne se joue qu’une fois malgré les rafraîchissements du salon et qu’un changement de manche ou une reconnexion ne rejoue pas une ancienne célébration. Vérifier le mode de réduction des mouvements dans le navigateur, l’absence de scroll sur téléphone et TV, ainsi que les contrôles du README. Conserver des captures de repos et de geste pour la revue.

## Livraison

Branche `feature/plummo-svg-motion`, créée depuis `main` après la fusion de la PR #25. Ce document décrit le travail à réaliser ; le découpage et les animations ne sont pas encore implémentés. Les essais sur téléphone physique et TV restent distincts des tests automatisés.
