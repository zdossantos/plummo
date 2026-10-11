# Mini jeux — proposition de règles pour l’issue #52

Cadre validé par l’utilisateur le 11 octobre 2026, avec sélection des épreuves à la place des packs, toutes cochées par défaut et échanges de gobelets rapides mais suivables.

## Parcours commun

Une seule entrée « Mini jeux » apparaît dans le choix du jeu. Le chef sélectionne les épreuves autorisées dans un drawer, le nombre de manches (10 par défaut, de 5 à 30), le temps de réponse (20 secondes par défaut, de 10 à 60) et les bonus optionnels. Le choix des épreuves remplace celui des packs et toutes sont cochées par défaut, sauf celles incompatibles avec le nombre de joueurs. Aucun pack de contenus n’est nécessaire pour les scènes générées.

Le jeu mélange les épreuves sélectionnées par cycles : chacune passe une fois avant le cycle suivant, sans répéter immédiatement la précédente lorsque plusieurs sont disponibles. Une seule épreuve cochée peut se répéter. Les épreuves devenues incompatibles avec les joueurs connectés sont écartées avant la prochaine manche. S’il ne reste aucune épreuve jouable, le jeu se met en pause et explique pourquoi sur les téléphones.

La télé reste passive, sans scroll ni pagination. Elle présente la consigne, la scène, le chrono, les réponses à la révélation et le classement commun. Les téléphones présentent uniquement les commandes utiles. Les Plummos conservent leurs dessins et accessoires existants.

## Épreuves

### Mémoire de Plummo (#48)

La télé montre quatre Plummos pendant huit secondes, puis les masque. Une question porte sur un détail évident : couleur, accessoire ou position. Quatre choix sont proposés sur téléphone ; la question est annoncée après la disparition de la scène. Le générateur garantit une seule bonne réponse et quatre choix distincts. La révélation remet la scène et souligne le détail demandé.

### Cache-cache Plummo (#49)

Une grille de douze Plummos numérotés apparaît sur la télé avec une consigne décrivant un seul personnage par sa couleur et un accessoire. Les téléphones affichent douze boutons numérotés. Aucun viseur ni déplacement à piloter. La génération garantit que la combinaison recherchée est unique. À la révélation, le bon personnage célèbre sa découverte.

### Le compte est bon (#50)

Pendant douze secondes, des Plummos entrent et sortent d’une maison, un mouvement à la fois. Le nombre à l’intérieur reste toujours entre zéro et huit. Les téléphones invitent à observer ; après la séquence, chacun choisit un nombre entre zéro et huit. La révélation ouvre la maison et montre les occupants.

### Les gobelets farceurs (#51)

La télé montre un Plummo sous l’un des trois gobelets, puis six échanges animés de 700 ms, séparés de 180 ms, après 2,5 secondes de présentation et avant 1 seconde de stabilisation. Les téléphones invitent à observer, puis proposent les trois positions finales. Les trajectoires sont préparées côté serveur, sans physique. La révélation soulève le bon gobelet et fait sortir le Plummo.

### Qui a écrit ça ? (référence #47 indisponible sur GitHub)

Au moins trois joueurs. Tous répondent à la même courte question personnelle et légère, en une phrase de 80 caractères maximum ; trente secondes d’écriture, avec validation anticipée lorsque tous ont terminé. Une contribution non vide est ensuite tirée au sort et présentée anonymement sur la télé. Les joueurs choisissent son auteur sur leur téléphone ; l’auteur attend et ne marque pas de points pour sa propre contribution. Si aucune contribution n’a été envoyée, la manche est passée sans attribuer de points. Aucun texte privé ni auteur n’est révélé avant la phase correspondante.

## Points et transitions

Le barème de rapidité existant est conservé : uniquement les bonnes réponses marquent, selon leur ordre serveur ; les ex æquo partagent le rang. Pour « Qui a écrit ça ? », l’auteur n’entre pas dans l’effectif des répondants. Une réponse est définitive. L’absence de réponse vaut zéro.

La manche finit lorsque tous les participants encore attendus ont répondu ou à l’expiration. Révélation automatique pendant quatre secondes, puis prochaine manche. Le classement de Mini jeux et le score global du salon sont mis à jour une seule fois. Le seuil de session est vérifié à la fin de la manche. Les arrivants attendent la prochaine manche ; les départs ne bloquent pas la clôture.

## Bonus

La poche, la distribution selon le retard, les protections, les cadeaux, les animations de réception et de lancement ainsi que les sons existants sont réutilisés. Les farces génériques sur le téléphone et le chrono s’appliquent pendant la réponse. L’éclair, le squatteur et les déformations de boutons compatibles sont également disponibles. Les objets liés à l’audio musical, au pinceau ou aux transformations de phrases sont exclus.

Les phases d’observation et d’écriture de cette première version interdisent les lancements : la scène commune ne peut pas être masquée pour tout le monde par une attaque destinée aux adversaires. Les bonus sont attribués au début de la manche, mais utilisables uniquement au passage en réponse. La dette temporelle éventuelle est appliquée à cette phase, pas à l’observation.

## Intégration et garanties techniques

Un service `MiniGames` orchestre les épreuves et leur projection sous le verrou de salon déjà utilisé par `GameEngine`. Le type `mini_games` reste distinct des types de contenus administrables ; aucune migration de catalogue ni de média n’est nécessaire. Un générateur dédié produit les scènes et vérifie leurs invariants.

Les phases communes pause/reprise et résultats sont réutilisées ; observation, écriture et réponse conservent leur échéance. Les animations de scène utilisent l’heure serveur et l’avancement conservé lors d’une pause : elles ne repartent pas du début lors d’une relecture ou d’une reconnexion. Le serveur conserve le déroulé nécessaire à la TV ; les téléphones ne reçoivent pas les solutions, la scène de mémoire masquée ni les contributions privées des autres.

Les réponses indiquent partie et manche. Laravel refuse les réponses périmées, hors phase, doubles, non admissibles et les commandes d’un joueur non autorisé. Tous les textes visibles sont partagés via `lang/fr` et `lang/en`.

## Vérifications prévues

Tests métier d’abord sur `plummo_testing` : validation des réglages, rotation des épreuves, génération sans ambiguïté, secret des projections, autorisations, réponses définitives, scores sans double attribution, échéances, départs, arrivées, pause/reprise et bonus compatibles. Vérifications navigateur sur télé et téléphone : observation, réponse, révélation, classement, dessin des mascottes conservé et absence de scroll. Contrôles README et CI avant PR et fusion.
