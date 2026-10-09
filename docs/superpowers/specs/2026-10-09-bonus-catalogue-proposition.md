# Bonus Plummo — sélection pour la maquette

Catalogue retenu pour exploration visuelle : Éclair, Dé farceur, Plummo squatteur, La grosse sieste, Artiste incognito, Voyelles volées, Accent Plummo, Éternuement, Signature surprise, Tampon Plummo, Pot renversé, Main gauche.

Première sélection proposée : Éclair, Dé farceur, Plummo squatteur, Artiste incognito, Accent Plummo, Éternuement, Tampon Plummo, Pot renversé.

Maquette : `public/maquettes/bonus.html`. Deux objets près du personnage. Un appui lance immédiatement le bonus contre tous les adversaires, sans choix de cible ni confirmation. Un petit bouton d’information au-dessus de chaque objet affiche une explication non bloquante, sans consommer l’objet. L’emplacement est consommé au lancement ; annonce automatique sur télé. Pour le dessin, les effets agissent sur la toile commune, sans sélection individuelle. Pour les phrases, le sabotage est préparé pendant l’écriture et appliqué après la validation ; conserver le texte original et présenter la version transformée anonymement. Pas de modification du champ en cours de saisie.

À prévoir lors de l’attribution d’un bonus : sur le grand écran, une petite animation fait glisser l’icône du bonus juste derrière le Plummo du joueur qui le reçoit, avec un ordre de calques permettant au personnage de rester au premier plan. Sur le téléphone du destinataire, un petit toast annonce le bonus reçu, puis son icône glisse vers l’emplacement de l’inventaire qui l’accueille. Ces animations accompagnent la réception, sans bloquer le jeu ni déplacer l’interface ; prévoir une variante sobre avec la réduction des mouvements.

Cette maquette HTML simule les actions et n’implémente aucune règle serveur. Les durées, la distribution favorisant le rattrapage, la capacité de deux objets et la limite d’usage par manche restent des propositions à confirmer avant implémentation. Le lancement direct et les explications légères remplacent le drawer de la première proposition.
