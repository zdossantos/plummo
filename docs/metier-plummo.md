# Plummo — premier cadrage métier

Date : 7 octobre 2026.

Ce document rassemble les décisions prises avec le porteur du projet. Il décrit le métier de la première version, sans choix de stack technique. Les suites de conception et les essais à effectuer sont regroupés à la fin.

## 1. Concept et public

Plummo est un site de mini-jeux pour les amis et les familles réunis dans une même pièce.

Un grand écran, télé ou ordinateur, affiche le salon et le déroulement du jeu. Chaque joueur utilise son téléphone comme manette. Même en solo, deux appareils sont nécessaires : un grand écran et un téléphone.

Après l'ouverture de Plummo sur le grand écran, toutes les commandes de partie passent par le téléphone du chef. Le grand écran sert à l'affichage et au son ; aucune souris ou télécommande n'est nécessaire pour piloter les mini-jeux, leurs réglages, les pauses ou leurs résultats.

Plummo est aussi le nom des personnages personnalisables représentant les joueurs. La mascotte fournie sert de référence à cet univers.

Référence des premières maquettes : https://www.figma.com/design/6kioGGc1qTiFtyAjvsHSqw/Projet-jeu-tel---pc

## 2. Salon et arrivée des joueurs

- Ouvrir Plummo sur le grand écran prépare directement un salon.
- Le grand écran affiche un QR code et un code de salon.
- Un joueur peut scanner le QR code ou saisir le code ; le scan est facultatif.
- Aucun compte n'est nécessaire pour jouer.
- Sur son téléphone, le joueur choisit un prénom ou pseudo, la couleur de son Plummo et des accessoires combinables, avec un choix par emplacement (tête, visage, cou et main), puis rejoint le salon.
- Les pseudos et les apparences peuvent être identiques. Aucune vérification d'unicité n'est demandée.
- Dans le salon, chacun peut modifier son pseudo et son apparence sans perdre ses points ou son rôle.
- Le salon accueille au maximum huit joueurs présents. Les données d'anciens participants peuvent rester conservées.

## 3. Chef de partie

Le premier joueur connecté devient chef de partie. Il participe aux jeux comme les autres et ne connaît pas les réponses à l'avance.

Depuis son téléphone, il choisit le mini-jeu, les packs et les réglages, lance la partie, met en pause et reprend. Il peut transmettre volontairement son rôle à un autre joueur.

S'il se déconnecte, le joueur connecté présent depuis le plus longtemps assure le relais. Le chef initial récupère son rôle à son retour. Un transfert volontaire est distinct de ce relais : le retour de l'ancien chef n'annule pas un transfert volontaire.

Si le chef temporaire se déconnecte à son tour, le relais est transmis au joueur connecté présent depuis le plus longtemps. Cette règle s'applique aux relais successifs : le chef initial conserve la priorité de récupération à son retour, sauf transfert volontaire.

Le chef peut arrêter un mini-jeu uniquement après l'avoir mis en pause, depuis son écran de pause. Le groupe revient alors au salon. Les points des manches terminées sont conservés ; les points de la manche en cours sont annulés.

Seul le chef peut fermer définitivement le salon, depuis le salon ou l'écran de pause, avec confirmation. Cette fermeture met fin à la session et efface ses données et son historique.

## 4. Enchaînement et scores

Le salon reste ouvert entre les mini-jeux. À la fin d'un mini-jeu, le groupe retrouve les mêmes joueurs et peut en lancer un autre.

Deux classements existent :

- Le classement du mini-jeu, fondé sur les points gagnés pendant celui-ci.
- Le classement global, qui cumule les points des mini-jeux du salon.

Les scores bruts sont cumulés, avec des barèmes à équilibrer entre les jeux. Une partie avec davantage de manches peut peser davantage au classement global. Le temps écoulé ne rapporte pas directement de points.

### Base commune des barèmes

La base validée est une valeur comparable par manche, adaptée au nombre de participants. Une manche représente une question, un extrait, un dessin ou une phrase à compléter. Un tour de dessin avec huit joueurs représente huit manches.

- Quiz et blind test : les récompenses vont de 100 à 30 points selon l'ordre des bonnes réponses et le nombre de participants. À deux joueurs : 100 et 30 ; à quatre : 100, 77, 53 et 30 ; à huit : 100, 90, 80, 70, 60, 50, 40 et 30. Quand tous réussissent, la moyenne est de 65 points par participant. Les places non récompensées par une bonne réponse rapportent zéro.
- Dessin : les récompenses des joueurs qui devinent ont une moyenne de 65 points quand tous trouvent, avec un avantage selon l'ordre d'arrivée. Le dessinateur reçoit jusqu'à 65 points selon la proportion de joueurs ayant trouvé. À deux joueurs, celui qui devine peut gagner 65 points et le dessinateur également.
- Phrases : les points proviennent uniquement des votes reçus, sans bonus de podium. Chaque vote envoyé rapporte 65 points à l'auteur de la phrase choisie. Quand tous votent, la moyenne distribuée est de 65 points par participant ; les scores individuels dépendent du nombre de votes reçus. Une abstention ne distribue pas de points.

La moyenne de référence validée est de 65 points par participant et par manche quand tous réussissent ou votent. Elle concerne la quantité totale distribuée, et non une garantie individuelle de 65 points. Les échecs et abstentions peuvent réduire la moyenne réelle.

Les points attribués et affichés sont toujours des nombres entiers : les résultats des calculs sont arrondis à l'entier le plus proche, y compris le partage de points en cas d'égalité. Aucun score décimal n'est utilisé. De petits écarts à la moyenne de référence sont acceptés en raison des arrondis.

Le solo utilise la même formule que les autres effectifs : aucun barème ni branche de calcul spécifique au mode solo n'est prévu. Sa valeur découle de la formule commune.

La formule commune validée pour les points de rapidité est : arrondi(65 + 35 × (N + 1 − 2R) / max(1, N − 1)), où N est le nombre de participants classables fixé au début de la manche et R le rang de la bonne réponse. Pour le quiz et le blind test, N comprend tous les joueurs participants ; pour le dessin, N comprend les joueurs qui devinent, sans le dessinateur. Les erreurs et absences de réponse rapportent zéro. À un participant classable, la formule donne directement 65 points.

Le score du dessinateur est arrondi(65 × nombre de joueurs ayant trouvé / nombre de joueurs devant deviner), avec l'effectif fixé au début du dessin. Une absence de joueurs devant deviner rend la manche non jouable et ne doit pas entraîner de division par zéro.

Les arrondis se font à l'entier le plus proche ; une valeur exactement à mi-chemin est arrondie vers l'entier supérieur.

Pour les bonnes réponses reçues exactement au même instant, les joueurs ex æquo reçoivent chacun la moyenne des points des places qu'ils occupent. Le rang suivant tient compte de toutes les places occupées. Exemple à huit joueurs : deux premiers ex æquo reçoivent (100 + 90) / 2 = 95 points chacun ; le suivant reçoit les 80 points de la troisième place. Cette règle s'applique aux récompenses fondées sur la rapidité.

La vérification arithmétique a été effectuée pour les effectifs de un à huit joueurs, avec toutes les réponses correctes et tous les votes envoyés. Les jeux disponibles à deux, quatre et huit joueurs distribuent exactement 65 × nombre de participants points par manche. À cinq joueurs, le quiz ou blind test distribue 326 points au lieu de 325 ; à six joueurs, le dessin distribue 391 points au lieu de 390. Ces écarts d'un point au total sont acceptés.

Des simulations théoriques ont également été effectuées : 50 000 manches par scénario, à deux, quatre et huit joueurs, avec des probabilités indépendantes de bonne réponse de 40 %, 70 % et 100 %. À 70 % de réussite, la moyenne de points par participant est d'environ 52,8 au quiz contre 45,5 au dessin à deux joueurs ; 52,9 contre 51,1 à quatre ; 52,9 contre 52,0 à huit. Le blind test partage le calcul du quiz. Un mélange de manches réparties à parts égales entre quiz et dessin a pour moyenne la moyenne de ces deux valeurs.

Ces résultats reposent sur une hypothèse de réussites indépendantes et ne sont pas une mesure de parties réelles. Ils montrent que l'enveloppe maximale comparable ne garantit pas une moyenne identique avec des erreurs, particulièrement à deux joueurs où le dessin repose sur une réussite commune du dessinateur et du devineur.

Pour les phrases, tous les votes envoyés distribuent exactement 65 points par participant au total, quelle que soit leur répartition. Une participation au vote de 70 % correspond à une moyenne attendue de 45,5 points par participant. La concentration des votes peut cependant créer des écarts individuels plus grands que dans le quiz : à huit joueurs, sept votes pour une phrase rapportent 455 points à son auteur. C'est la conséquence du barème par vote validé, sans bonus de podium.

Les barèmes restent ceux validés. Les essais de jeu devront vérifier les différences de difficulté, de niveau et de concentration des votes avant d'affirmer un équilibre définitif.

À la fin d'un mini-jeu, une animation annonce le gagnant avec son pseudo et son Plummo, puis les classements sont présentés. Les gagnants ex æquo sont célébrés ensemble.

### Objectif global facultatif

Dès la première version, une session peut être libre ou comporter un objectif de points fixé au départ.

Les nouveaux salons sont sans limite par défaut. Le chef peut choisir explicitement un objectif de points. Les objectifs des salons existants sont conservés.

Si le seuil est atteint, seule la manche en cours se termine avant la célébration du ou des gagnants globaux. Les manches restantes du mini-jeu ne sont pas jouées. L'objectif n'est pas augmenté pour absorber un dépassement : un seuil de 1 000 points reste fixé à 1 000 même si le meilleur score termine la manche à 1 180. Le groupe revient au salon avec ses scores conservés. Le chef peut :

- Prolonger en ajoutant un nombre de points au meilleur score actuel. Par exemple, si le premier termine à 1 180 et que le chef demande encore 500 points, le nouvel objectif est de 1 680. Ce nouvel objectif reste fixe pendant la session prolongée.
- Continuer sans limite avec les scores existants.
- Recommencer une session avec les scores remis à zéro et un objectif facultatif.

Recommencer ne supprime ni les joueurs ni leurs Plummos ni l'historique des contenus joués dans le salon.

## 5. Mini-jeux de la première version

La première version fonctionne en chacun pour soi. Le mode équipes est une évolution envisagée.

| Mini-jeu | Minimum de joueurs | Longueur de partie | Temps de réponse ou d'action |
| --- | --- | --- | --- |
| Quiz | 1 | 10 questions par défaut, de 5 à 30 | 30 s par défaut, de 10 à 150 s |
| Blind test | 1 | 10 extraits par défaut, de 5 à 30 | 30 s par défaut, de 10 à 150 s |
| Dessin à deviner | 2 | 1 tour par défaut, de 1 à 5 | 90 s par dessin par défaut, de 30 à 150 s |
| Phrase à compléter | 3 | 1 tour par défaut, de 1 à 5 | 60 s d'écriture par défaut, de 30 à 150 s |

Les durées du quiz et du blind test sont réglables séparément. Les réglages communs sont harmonisés entre les jeux, avec leurs valeurs par défaut propres.

Le paramétrage indique la quantité réelle de manches et une estimation de durée. Les quantités de questions ou d'extraits sont limitées par les contenus disponibles.

### Quiz

- La question et les propositions sont affichées sur le grand écran ; le téléphone permet de répondre.
- Quatre choix sont proposés.
- Chaque joueur valide un seul choix définitif.
- Les bonnes réponses rapportent des points décroissants selon leur ordre d'arrivée.
- Une mauvaise réponse ou l'absence de réponse rapporte zéro point. Les mauvaises réponses ne prennent pas de place dans l'ordre des bonnes réponses.
- La manche se termine à l'expiration du délai ou dès que tous les joueurs ont répondu.
- La bonne réponse est brièvement révélée et la question suivante démarre automatiquement.

### Blind test

- L'extrait musical est joué sur le grand écran.
- L'extrait tourne en boucle jusqu'à la fin de la manche : expiration du délai ou réponse de tous les joueurs encore attendus. La diffusion est suspendue pendant une pause générale.
- Huit choix apparaissent sur les téléphones dès le début de l'extrait, en même temps pour tous.
- Chaque choix présente le titre de la chanson et le nom de l'artiste, sous un seul bouton.
- Les sept mauvaises réponses sont tirées automatiquement parmi des chansons partageant au moins un tag avec la chanson jouée. Tous les tags de cette chanson peuvent servir à chercher des faux choix, et les propositions peuvent provenir de plusieurs de ces catégories. Exemple : une chanson portant « Années 90 » et « Années 2000 » peut recevoir des propositions des deux catégories. Une catégorie d'un autre pack sélectionné ne suffit pas à rendre une proposition admissible si la chanson jouée ne porte pas ce tag. Cette recherche de faux choix est distincte de la règle des packs, qui exige tous leurs tags pour sélectionner le contenu joué.
- Si les catégories de la chanson ne fournissent pas sept faux choix distincts, les propositions manquantes sont complétées au hasard avec des chansons d'autres packs. Ce complément est une exception à la proximité de tags et évite d'exclure la chanson du tirage. Les huit choix restent distincts et la bonne réponse n'est jamais reprise comme faux choix.
- Seule la chanson réellement jouée (la bonne réponse) est inscrite dans l'historique des extraits joués. Les chansons affichées uniquement comme faux choix restent disponibles pour devenir de bonnes réponses dans les manches suivantes.
- Un seul choix définitif par joueur est autorisé.
- Le principe de score est le même que pour le quiz : ordre des bonnes réponses, zéro point pour une erreur ou une absence de réponse.
- La manche se termine à l'expiration du délai ou dès que tous ont répondu, puis le résultat est brièvement révélé avant l'extrait suivant.

La réponse écrite, la reconnaissance approximative des titres, le buzz et les mises de points ne sont pas retenus pour ce jeu.

### Dessin à deviner

Un tour signifie que chaque joueur dessine une fois. À huit joueurs, deux tours représentent donc seize dessins ; ce total doit être explicite dans le paramétrage.

Un nouveau joueur arrivé pendant un tour peut deviner dès le prochain dessin et est ajouté à la fin de la liste des dessinateurs du tour en cours.

Un joueur qui part volontairement ou se déconnecte avant son passage est retiré de la liste des dessinateurs, même pendant les deux minutes de réservation de sa place. S'il revient pendant le même tour sans avoir encore dessiné, il est replacé en fin de liste ; s'il a déjà dessiné, il ne repasse pas.

- Le dessinateur reçoit trois mots sur son téléphone et en choisit un.
- Il dispose de 15 secondes maximum pour choisir. Son choix démarre immédiatement la manche ; à l'expiration du délai, Plummo sélectionne au hasard l'un des trois mots et lance la manche.
- Aucun système de difficulté n'est prévu sur les mots.
- Le chrono de dessin démarre après le choix.
- Le joueur dessine sur son téléphone et le dessin apparaît en direct sur le grand écran.
- Les autres écrivent leurs propositions sur leur téléphone et peuvent faire plusieurs tentatives.
- Les différences de casse, d’accents, d’espaces, de tirets et de ponctuation sont ignorées pour valider une réponse ; une faute de lettre reste incorrecte.
- Une proposition proche par son écriture peut déclencher un retour privé « Presque ! Vérifie l'orthographe. », sans accorder de points.
- Les joueurs qui trouvent gagnent des points selon leur ordre d'arrivée.
- Le dessinateur gagne des points selon la proportion des joueurs ayant trouvé. Si tous trouvent, il reçoit le maximum prévu pour ce dessin.
- Le dessin se termine dès que tous les joueurs qui devinent ont trouvé, ou quand le temps expire.
- À chaque fin de dessin, le mot est brièvement révélé, même si tous l'ont trouvé, avant le passage au suivant.

#### Déconnexion du dessinateur

Les mêmes règles s'appliquent si le dessinateur quitte volontairement le salon. Sa place est alors libérée immédiatement, mais le dessin et les points acquis restent conservés ; les autres peuvent continuer à deviner et voter pour passer.

Ce cas diffère d'une pause générale :

- Le chrono s'arrête et le dessin reste visible.
- Les autres joueurs peuvent continuer à proposer des réponses sur le dessin figé.
- Les bonnes réponses continuent de rapporter des points aux joueurs et au dessinateur.
- Les autres joueurs connectés disposent d'un vote « Passer ce dessin ». L'unanimité de ces joueurs termine le dessin.
- Si tous trouvent, le dessin se termine automatiquement.
- Si le dessinateur revient avant la fin du dessin, le vote est annulé et le jeu reprend après cinq secondes de compte à rebours.
- Si le dessin est passé, les points acquis sont conservés et le mot est révélé avant le suivant.

### Phrase à compléter

Un tour comprend un début de phrase commun, l'écriture des suites, leur présentation, le vote et les résultats.

- Le même début de phrase est présenté à tous.
- Chacun écrit sa suite en secret sur son téléphone.
- La suite écrite par le joueur est limitée à 150 caractères, sans compter le début de phrase fourni.
- L'écriture se termine à l'expiration du délai ou dès que tous ont validé.
- À l'expiration du délai, un texte saisi mais non validé est soumis automatiquement. Un champ vide ne crée aucune proposition : le joueur gagne zéro point pour cette phrase, mais peut voter pour celles des autres.
- Les phrases complètes sont présentées anonymement, une par une sur le grand écran.
- Le temps d'affichage s'adapte à la longueur de la phrase complète.
- Pour une phrase complète de longueur L (début fourni compris), la durée est de 5 secondes jusqu'à 40 caractères, puis augmente de 0,05 seconde par caractère supplémentaire, avec un maximum de 12 secondes : min(12, 5 + 0,05 × max(0, L − 40)). Exemples : 80 caractères = 7 secondes ; 120 = 9 secondes ; 160 = 11 secondes ; 180 ou plus = 12 secondes.
- Une fois toutes les phrases présentées, elles sont affichées ensemble sur le grand écran et sur les téléphones pour voter.
- Chacun choisit sa phrase préférée et ne peut pas voter pour la sienne.
- Le vote dure au maximum 30 secondes et se termine plus tôt si tous ont voté.
- Un vote non envoyé à la fin du délai compte comme une abstention, sans vote automatique. Le joueur conserve les points des votes reçus par sa propre phrase.
- Après le vote, les auteurs sont révélés avec leur pseudo et leur Plummo, les votes reçus et les points gagnés.
- Chaque vote reçu rapporte 65 points. Aucun bonus de podium n'est attribué ; le classement et la célébration des gagnants restent présents.
- Une phrase sans vote peut rester à zéro point.

## 6. Contenus et administration

La première version propose des contenus prêts à jouer, gérés par les administrateurs. La création de contenus par les joueurs n'est pas prévue à ce stade.

L'administration doit permettre d'ajouter, modifier et publier facilement les contenus de chaque mini-jeu : questions et réponses, extraits musicaux, mots à dessiner et débuts de phrases.

Dès la première version, deux modes d'ajout sont disponibles : des formulaires pour ajouter ou modifier un contenu individuellement, et un import par lot depuis un tableau Excel ou CSV pour préparer plusieurs contenus à la fois.

L'import présente un aperçu des contenus reconnus et des éventuelles erreurs avant publication. L'administrateur valide leur ajout ; il peut importer les lignes valides et corriger les autres ensuite.

Pour le blind test, l'administrateur importe un extrait audio déjà préparé et renseigne le titre de la chanson et le nom de l'artiste. Aucun outil de découpage d'une chanson complète n'est prévu pour la première version.

Pour l'import audio par lot, le tableau contient le titre, l'artiste, les tags et le nom du fichier audio. L'administrateur fournit les extraits correspondants ; l'association se fait par le nom du fichier. L'aperçu signale les fichiers manquants avant publication.

Le catalogue jouable du blind test doit contenir au moins huit propositions distinctes (titre et artiste), afin de fournir une bonne réponse et sept faux choix même en complétant depuis d'autres packs. Cette contrainte porte sur le catalogue global, et non sur chaque pack.

### Tags et packs dynamiques

Un contenu peut porter plusieurs tags. Un pack possède un nom et un ou plusieurs tags requis. Pour être admissible dans ce pack, un contenu doit posséder tous les tags requis ; il peut en porter d'autres.

Exemple : une question avec les tags « cinéma » et « années 2000 » appartient aux packs « Cinéma », « Années 2000 » et « Cinéma des années 2000 », si ces packs demandent respectivement le premier tag, le second ou les deux.

Les administrateurs gèrent les tags et les définitions des packs. Ajouter un contenu avec les bons tags enrichit automatiquement les packs correspondants.

### Sélection et répétitions

- Le chef choisit de un à trois packs pour le mini-jeu.
- Les contenus sont tirés au hasard dans l'ensemble des contenus admissibles de ces packs.
- Un contenu présent dans plusieurs packs n'est pas compté plusieurs fois.
- Le salon mémorise les contenus déjà joués, y compris quand le groupe change de pack ou remet les scores à zéro.
- Les contenus déjà joués sont évités tant que des contenus admissibles non joués sont disponibles.
- Un contenu est considéré comme vu dès qu'il est révélé à un joueur, même si la manche est ensuite annulée. Pour le dessin, les trois mots proposés au dessinateur sont marqués comme vus, y compris les deux non choisis.
- Exception pour les faux choix du blind test : afficher leur titre et leur artiste ne marque pas ces chansons comme jouées. Seule la bonne réponse, dont l'extrait est diffusé, est inscrite dans l'historique.
- En cas d'épuisement, le chef est averti et peut autoriser les répétitions ou changer de pack.

## 7. Rythme, animations et chat

Le jeu doit s'enchaîner rapidement, sans attendre inutilement la fin des chronos lorsque tous ont terminé.

Les Plummos des joueurs restent visibles en bas à droite du grand écran. Après une question ou un extrait, la bonne réponse apparaît brièvement et les Plummos ayant répondu correctement font une petite animation avec les points gagnés. Ces animations n'exigent aucune action du chef et ne bloquent pas l'enchaînement.

Pour le quiz et le blind test, les choix restent privés pendant la réponse. À la révélation, le grand écran et les téléphones affichent les Plummos sur les propositions choisies. La révélation des auteurs est conservée pour le jeu de phrases, où elle participe à l'intérêt du jeu.

### Bulles de chat

- Un joueur peut écrire quand il n'a plus d'action de jeu à effectuer, y compris pendant que les autres terminent leur action.
- Exemples : réponse définitive envoyée, mot trouvé, phrase validée, vote effectué, attente dans le salon ou pause.
- Le message contient au maximum 80 caractères.
- Il apparaît temporairement dans une bulle au-dessus du Plummo du joueur sur le grand écran.
- Une seule bulle est visible par joueur à la fois.
- Une bulle reste affichée cinq secondes. Chaque joueur doit attendre au moins trois secondes entre deux envois ; un nouveau message remplace sa bulle précédente et démarre une nouvelle durée d'affichage.
- Dès qu'une nouvelle action est attendue, le téléphone revient aux commandes du jeu.

## 8. Connexions, pauses et départs

### Arrivée ou reconnexion pendant un mini-jeu

Les participants et le barème sont fixés au début de chaque manche, y compris les effectifs utilisés pour les scores proportionnels. Un départ ou une déconnexion ne recalcule pas le barème en cours de manche. Pour la fin anticipée, seuls les joueurs encore connectés ayant une action à effectuer sont attendus. Les nouveaux arrivants participent à partir de la manche suivante.

Un joueur rejoint le salon immédiatement, mais attend la prochaine manche pour participer : question, extrait, dessin ou phrase. La manche en cours continue sans lui.

Un nouveau joueur commence avec zéro point. Un joueur qui revient depuis le même téléphone retrouve sa place si elle est disponible, son pseudo, son Plummo et ses points. Les manches manquées ne lui rapportent aucun point.

### Départ et disponibilité des places

- Un départ volontaire libère immédiatement une place.
- Une déconnexion réserve la place pendant deux minutes, puis la libère.
- Les données du joueur restent conservées dans le salon jusqu'à sa fermeture.
- Un joueur qui revient peut retrouver ses données si une place est disponible ; sinon, il attend qu'une place se libère.
- Un nouvel arrivant occupe sa propre place avec ses propres données et ne reprend pas les points de l'ancien participant.

### Absence de tous les joueurs

Quand aucun joueur n'est connecté, le jeu est mis en pause et le salon est conservé pendant 30 minutes. Le retour d'un joueur avant l'expiration annule ce délai ; une manche en pause reprend après le compte à rebours de cinq secondes, sous réserve que le grand écran soit connecté et que la manche soit jouable. Sans retour, le salon est fermé et ses données et son historique sont effacés.

Ce délai concerne exclusivement l'absence de joueurs connectés. Il ne s'applique pas aux joueurs connectés qui attendent dans le salon, sont en pause ou n'ont aucune action à effectuer. La connexion du grand écran seul ne compte pas comme la présence d'un joueur.

### Pause générale

La perte de connexion du grand écran déclenche une pause générale. Le chef peut aussi mettre en pause manuellement depuis son téléphone.

Pendant une pause générale, le chrono et les actions de jeu sont suspendus. Les réponses validées, le dessin et les points sont conservés. Les téléphones indiquent l'état de pause ; le chat reste disponible selon la règle des temps d'attente.

Une reprise est précédée d'un compte à rebours de cinq secondes. Le cas de déconnexion du dessinateur autorise expressément les propositions pendant l'arrêt du chrono, contrairement à la pause générale.

## 9. Prochaines validations

Les questions métier ouvertes lors de la première synthèse ont été traitées. Les travaux suivants concernent les maquettes et les essais, sans choix de stack à ce stade :

- Les essais d'équilibrage en parties réelles avec différentes difficultés et différents niveaux. La formule commune, les arrondis et les totaux sont validés ; les premières simulations théoriques sont documentées dans la section des scores.
- Les détails des écrans d'administration et les colonnes des modèles d'import pour chaque mini-jeu, à préciser lors des maquettes. Les formats Excel et CSV et l'association des extraits audio par nom de fichier sont validés.
- La disposition et la lisibilité du grand écran sur télé et ordinateur, à préciser lors des maquettes. Les commandes de partie sur le téléphone du chef sont validées.

La prochaine étape consiste à relire ce cadrage consolidé, puis concevoir les parcours et les maquettes et vérifier les barèmes en jouant. Aucune implémentation ni décision de stack n'est engagée par ce document.

### Ambiance et propositions (octobre 2026)

Le grand écran diffuse une musique originale en boucle et de petits sons discrets des Plummos, après activation du son par le navigateur. Le volume commun et la coupure du son sont accessibles dans son en-tête. Pendant les extraits du blind test et leur révélation, l’ambiance reste audible à volume réduit ; les extraits démarrent progressivement et leur fondu de fin commence après l’annonce des résultats. Les téléphones ne diffusent pas ces sons pour éviter les échos.

Les propositions récentes du dessin apparaissent sur le grand écran. Une bonne réponse est remplacée par « Trouvé ! » avant la révélation pour préserver le secret. La comparaison ignore accents, casse, espaces et ponctuation, sans accepter une véritable faute de lettre comme bonne réponse. Le formulaire propose seulement les jeux compatibles avec le nombre de joueurs connectés et les packs contenant du contenu publié pour le mode choisi.

## Bonus farceurs optionnels

Le chef peut activer les bonus pour chaque mini-jeu, uniquement avec plusieurs participants. L’option reste désactivée par défaut. Chaque joueur reçoit un objet compatible au départ et possède deux emplacements. Entre les manches, les joueurs strictement derrière le meilleur score du mini-jeu reçoivent un objet, avec un choix de remplacement si leur poche est pleine. Aucune attribution après la dernière manche ; les objets disparaissent à la fin du mini-jeu.

Un appui déclenche immédiatement le bonus sur tous les adversaires participants, sans sélection de cible ni confirmation. Le bouton d’information explique l’effet sans le consommer. Un lancement par joueur et par manche ; pause, reprise, révélation et résultats interdisent les lancements. Les effets distincts coexistent ; un même effet ne prolonge pas sa durée. La pause conserve le temps restant et la manche suivante efface les effets.

- Quiz et blind test : éclair (écran sombre, 3 s), dé (trois déplacements des réponses, 6 s), Plummo squatteur (4 s). Le blind test propose aussi artiste incognito (artistes masqués, 4 s).
- Phrases : accent Plummo (r/R devient w/W) et éternuement (« ATCHOUM ! »). Seules les contributions publiées sont transformées, y compris celles déjà validées pendant l’écriture. Le brouillon et le prompt restent intacts ; accent puis éternuement dans un ordre fixe. Aucun auteur n’est révélé lors du vote.
- Dessin : tampon temporaire (4 s, traits conservés) et pot renversé (couleur imposée, 4 s). La toile est commune ; le crayon revient à la couleur choisie après l’effet. Un trait maintenu est segmenté au changement de couleur pour conserver les échanges et les retries des traits existants.

La réception est signalée par une icône glissant derrière le Plummo sur le grand écran et par un toast sur le téléphone. Les événements sont dédupliqués et une reconnexion ne rejoue pas les anciennes réceptions. La poche reste utilisable avec le clavier ouvert. Les animations ne capturent pas les appuis et respectent les mouvements réduits ; le grand écran reste passif.

Chaque malus accepté diffuse un événement sonore public distinct, conservé huit secondes. Le grand écran joue une courte signature propre à l’objet via son système audio existant (activation, volume, atténuation blind test). Les snapshots répétés et les reconnexions ne rejouent pas les sons ; les lancements refusés restent silencieux.

### Extension à un nouveau mini-jeu

Tout nouveau mini-jeu doit intégrer les bonus/malus existants compatibles avec ses interactions : catalogue, attribution, lancement collectif, effets, pause/reprise, réception et sons. Réutiliser le système commun, expliquer les incompatibilités et tester les règles du nouveau mode.

La poche conserve deux objets utilisables. Lorsque l’attribution suivante arrive dans une poche pleine, un troisième objet reste en attente, privé au joueur. Il peut remplacer un des deux objets ou refuser le nouveau. Un seul objet attend : tant que le choix reste ouvert, il n’est pas écrasé par les attributions suivantes. Il disparaît à la fin du mini-jeu. Si un lancement libère une place, le nouveau rejoint la poche.

### Affichage de la toile de dessin

La toile apparaît uniquement sur le grand écran et sur le téléphone du dessinateur courant, y compris pendant une pause ou une révélation. Les autres téléphones conservent leur saisie et les informations utiles sans copie du dessin. Les projections serveur ne leur transmettent pas les traits ; le changement de dessinateur et la reconnexion réévaluent ce droit.

Les gestes de dessin ne sélectionnent pas le texte du plateau ou de ses outils. Le zoom par double tap, pincement, molette avec Ctrl/Cmd et raccourcis clavier est bloqué dans l’application. Les champs de texte conservent leur sélection. Les réglages de zoom imposés par le navigateur ou le système restent hors du contrôle de l’application.
