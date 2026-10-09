# Bonus — intégration proposée

## Parcours validé

Option activable par le chef dans la préparation de chaque mini-jeu, désactivée par défaut. Deux emplacements près du Plummo ; appui sur un objet pour déclencher immédiatement une farce collective, sans cible ni confirmation. Le bouton d’information au-dessus explique sans consommer. Grand écran passif, aucune pagination ni scroll.

À la réception : icône glissant derrière le Plummo sur le grand écran ; toast sur son téléphone et icône rejoignant l’emplacement libre. Animations non bloquantes et variante à mouvements réduits.

## Règles proposées à valider

- Inventaire limité à deux objets, conservé entre les manches du même mini-jeu et vidé à sa fin. Aucun objet incompatible à reporter dans un autre jeu.
- Un objet au début du mini-jeu pour chaque participant. Après chaque manche, un objet supplémentaire pour les joueurs strictement derrière le meilleur score du mini-jeu, seulement si leur poche a une place libre. Pas d’attribution après la dernière manche ; aucun remplacement si la poche est pleine.
- Un lancement par joueur et par manche. Pas de lancement en pause, pendant une reprise, une révélation ou les résultats. Deux joueurs peuvent lancer dans la même manche ; leurs effets distincts coexistent, un même effet ne cumule pas sa durée.
- Tous les adversaires participants sont touchés ; le lanceur est épargné. Le dessin utilise la toile commune. Pas de bonus en solo.
- Catalogue initial : les huit objets de la maquette. Les quatre autres objets restent au catalogue d’exploration pour une livraison ultérieure.

## Effets initiaux

| Objet | Jeu | Effet |
|---|---|---|
| Éclair | Quiz, blind test | Assombrissement des manettes adverses pendant 3 secondes ; réponses toujours utilisables. |
| Dé farceur | Quiz, blind test | Trois mélanges de propositions, à 0, 2 et 4 secondes, uniquement sur les manettes adverses. Les identifiants des réponses restent stables. |
| Plummo squatteur | Quiz, blind test | Plummo du lanceur passant devant les propositions adverses pendant 4 secondes, sans intercepter les appuis. |
| Artiste incognito | Blind test | Masquer les artistes pendant 4 secondes sur les manettes adverses ; titres conservés. |
| Accent Plummo | Phrases | Remplacer r/R par w/W dans les contributions adverses après validation. |
| Éternuement | Phrases | Insérer « ATCHOUM ! » au milieu des contributions adverses après validation. |
| Tampon Plummo | Dessin | Petite empreinte temporaire sur la toile commune pendant 4 secondes ; traits originaux conservés. |
| Pot renversé | Dessin | Couleur du crayon imposée pendant 4 secondes au dessinateur, puis retour à sa couleur choisie. |

Les phrases déjà validées sont également concernées tant que la phase d’écriture est ouverte. L’original reste privé côté serveur ; le prompt n’est pas transformé, les drafts ne sont pas modifiés. Accent puis éternuement dans un ordre stable. La version transformée seule est présentée anonymement au vote, sans indice sur son auteur.

## Intégration technique

Laravel conserve inventaires, attributions et effets dans une partie dédiée de `Game.state`. Un service `GameBonuses` porte catalogue, attribution, consommation et projection. Les mutations passent par le verrou de salon existant et vérifient identité, connexion, participation, partie, manche, phase, objet possédé et limite d’usage. Les répétitions et requêtes périmées sont refusées sans consommer un second objet.

Les débuts et fins d’effets utilisent l’horloge serveur. Les effets expirent au changement de manche ; la pause conserve leur durée restante. Les invalidations Reverb existantes déclenchent la relecture, avec le polling déjà disponible en secours. Les projections ne publient aucun original de phrase, bonne réponse ou inventaire adverse au téléphone ; le grand écran reçoit les événements nécessaires aux animations. Des identifiants stables évitent de rejouer une attribution à chaque polling ou reconnexion.

Vue affiche la poche, les effets et les animations. Les choix conservent leurs identifiants même quand leur ordre change ; les animations n’interceptent jamais les interactions. Tous les libellés produits passent par `lang/fr` et `lang/en`. Réutiliser les Plummos et composants existants, sans modifier leur dessin ni ajouter de dépendance.

## Vérifications prévues

Tests métier RED/GREEN sur `plummo_testing` : option désactivée, solo, compatibilité, rattrapage/ex æquo, poche pleine, double lancement, autre salon, joueur absent, requête périmée, pause/reprise, expiration et anonymat des phrases. Tests d’interaction : mélange sans changer le choix envoyé, lancement direct, aide non consommatrice, attribution animée une fois, reconnexion et absence de scroll mobile/TV. Validation selon README avant PR.
