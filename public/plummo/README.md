# Kit vectoriel Plummo

Première interprétation vectorielle de la mascotte fournie : corps crème, plumes/mains/pieds personnalisables, grands yeux et contours violets. Les formes ont été redessinées ; le SVG ne contient aucune image matricielle.

- `base.svg` : mascotte neutre, avec groupes nommés pour les plumes, les pieds, les mains, le corps et le visage.
- `accessories/` : 29 accessoires, tous dans le même cadre `0 0 512 512`.
- `catalog.json` : couleurs, zones, fichiers avant/arrière et cadrages de présentation.
- `index.html` : atelier autonome en français/anglais, essais de couleurs et assemblages, vue des pièces seules, export du personnage choisi.

## Assemblage

1. Calques `back` des accessoires sélectionnés.
2. Mascotte de base.
3. Calques `front` des accessoires sélectionnés.

Conserver le même cadre et la même taille pour tous les calques. Aucun calcul de placement selon la combinaison n'est nécessaire. L'écharpe et les huit objets de main utilisent un calque arrière distinct. Le manche d'un objet passe derrière la main ; le prolongement inférieur est devant. Les SVG d'un accessoire à deux calques doivent être utilisés ensemble.

Deux accessoires au maximum, un par zone : tête, visage, cou, mains. La collection comprend dix pièces de tête, six de visage, cinq de cou et huit objets de main.

## Couleurs

Le corps reste crème. Dans `base.svg`, remplacer les trois teintes de référence par celles du catalogue : `#b99aef` (clair), `#9672dc` (principal), `#6950ac` (foncé). Les SVG exportés par l'atelier contiennent déjà les couleurs résolues : aucun filtre, variable CSS ou ressource externe n'est nécessaire.

Lors de l'intégration de plusieurs personnages sur une même page, rendre uniques les identifiants de dégradés et leurs références `url(#...)`. L'atelier le fait automatiquement.

## Ajouter un accessoire

Dessiner la pièce dans un document SVG de 512 × 512 aux mêmes positions que la mascotte. Conserver des tracés/formes éditables, sans image incorporée, texte, script ou référence externe. Ajouter ses fichiers avant/arrière, puis une entrée au catalogue avec un identifiant stable, sa zone et son cadrage `[gauche, haut, droite, bas]`. Ajouter les libellés à `lang/fr/plummo.php` et `lang/en/plummo.php`, puis reconstruire l'atelier :

```sh
php scripts/build-plummo-preview.php
```

La planche constitue un outil de revue des visuels, pas encore le parcours d'arrivée dans un salon. Les choix restent locaux à la planche ; aucune identité joueur n'est créée ou enregistrée.

Les couvre-chefs fermés déclarent `coversPlumes: true` dans le catalogue. À la composition, retirer le groupe `plummo-plumes` de la base avant de superposer le chapeau : les plumes sont contenues sous le tissu. La couronne, la fleur et le casque audio conservent les plumes. Cette règle est aussi appliquée aux exports, sans créer de variantes de la mascotte.
