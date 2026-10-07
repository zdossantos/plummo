# Plummo — kit vectoriel et revue des accessoires

Le kit se trouve dans [public/plummo](../public/plummo/README.md). Il contient une base, six palettes et 22 accessoires indépendants répartis sur quatre zones. Aucun dessin de combinaison n'est stocké. Les objets de main et l'écharpe ont des calques avant/arrière pour gérer les recouvrements.

Le porteur du projet a validé le corps crème avec recoloration des zones violettes, ainsi que le principe de pièces composables. La proposition de deux accessoires maximum, un par zone, est matérialisée dans la planche. Les visuels sont proposés pour revue avant intégration au parcours joueur.

La base est une réinterprétation vectorielle dessinée à partir de la mascotte fournie, et non une conversion pixel à pixel. Elle conserve les plumes, la silhouette duveteuse, les grands yeux, le sourire et les contours violets.

L'atelier autonome est accessible à `/plummo/index.html` sur un serveur du dossier public. Il fonctionne sans service externe ; les messages FR/EN viennent des fichiers de langue Laravel, embarqués lors de la génération. Les exports sont des SVG avec couleurs résolues et calques assemblés. Le template et le constructeur sont `resources/plummo-preview.html` et `scripts/build-plummo-preview.php`.

Vérifications : syntaxe XML des 29 SVG, absence d'image incorporée et de variables CSS, 22 identifiants distincts, affichage sur ordinateur et téléphone, changement de couleur, limite de sélection, remplacement au sein d'une zone, export SVG et changement de langue. Aucun schéma de données ni connexion joueur n'est ajouté dans ce lot.
