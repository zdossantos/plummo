# Tribunal des Plummos — proposition pour #54

Statut : défense orale validée ; règles ci-dessous proposées à validation.

## But et parcours

Faire rire un groupe réuni devant la télé en défendant des positions absurdes. Jeu autonome, distinct de Mini jeux, à partir de trois joueurs. Deux débatteurs, l'un pour et l'autre contre ; les autres forment le jury. Aucun enregistrement audio ni saisie de discours.

Une manche affiche un sujet tel que « Les chaussettes devraient-elles être interdites ? ». Quinze secondes permettent de préparer son argument. Le premier débatteur parle, puis le second ; chacun dispose de vingt secondes par défaut, réglables de dix à soixante secondes. Seul le débatteur courant peut terminer sa prise de parole plus tôt depuis son téléphone. Le jury vote ensuite pendant vingt secondes maximum. Quand tous les jurés connectés ont voté, le verdict apparaît six secondes, puis la manche suivante démarre automatiquement.

Le chef choisit un à cinq tours, un par défaut. Un tour contient autant de débats que de joueurs présents au lancement : une liste mélangée forme un cercle, chaque joueur affronte le suivant. Chacun défend donc deux fois, une fois chaque camp. Le premier camp à parler alterne entre manches. Un nouveau mélange à chaque tour diversifie les rencontres. L'estimation de durée et le nombre de débats apparaissent dans les réglages.

## Jury et points

Les débatteurs ne votent pas. Chaque juré choisit définitivement le débatteur le plus convaincant. Les votes restent privés jusqu'au verdict ; l'abstention ne distribue rien. Un vote rapporte 65 points à son destinataire, comme dans Phrases. Les deux débatteurs peuvent marquer des points ; le vainqueur reçoit une célébration sans prime supplémentaire. En cas d'égalité, tous deux sont célébrés. Sans vote, le verdict annonce une absence de décision et aucun point n'est attribué. Ce jeu distribue moins de points par manche que Phrases, puisque deux joueurs ne votent pas ; leur rotation répartit les occasions de marquer.

Les gains sont engagés une fois, dans les scores du jeu et de la session. Le podium commun clôt la partie. L'objectif global, s'il existe, est vérifié après le verdict.

## Variété et contenus

Cinquante sujets absurdes, familiaux et traduits en français/anglais sont livrés avec le jeu : objets quotidiens, animaux, nourriture, école, travail, habitudes. Aucun sujet politique, religieux ou visant un participant. Les sujets sont mélangés sans répétition dans une partie ; aucun pack à sélectionner ni nouveau type d'import dans cette première version. Les camps pour/contre suffisent ; pas de contrainte supplémentaire à retenir.

## Affichage

La télé est une scène intégrée au fond existant : grand sujet en haut, deux Plummos face à face, camps lisibles, spot sur le débatteur qui parle et chrono central. Pendant le vote, les deux personnages restent visibles, sans révéler les choix. Le verdict montre votes et gains, puis la célébration et la transition. Pas de pagination, de scroll ou d'action sur la télé.

Le téléphone affiche le sujet, le camp et le rôle du joueur. Le débatteur courant possède un bouton « J'ai terminé » ; le jury possède deux cartes de vote pendant le vote. Les phases d'attente affichent le débatteur courant. Réutiliser la poche, les notifications de points, les pauses et le podium. Tous les textes visibles utilisent lang/fr et lang/en. Animations non bloquantes, sons existants, mouvements réduits respectés.

## Départs, pause et arrivée

Le moteur Laravel gère échéances et permissions sous verrou du salon. Les actions indiquent partie et manche ; une action périmée ou répétée est refusée. La pause fige la phase courante ; la reprise conserve le temps restant après le compte à rebours commun de cinq secondes.

Le départ d'un juré ne bloque pas le vote ; son vote déjà enregistré demeure. Si un débatteur manque au début d'un débat, cette rencontre est sautée sans points. S'il part en cours de préparation, de parole ou de vote avant clôture, le débat est annulé sans attribuer de points ; un écran bref annonce son absence avant la rencontre suivante. Une reconnexion ne rejoue pas une rencontre annulée. Le jeu attend si moins de trois joueurs restent connectés.

Les nouveaux joueurs attendent le prochain tour pour entrer dans la rotation. Un joueur revenu peut participer à une rencontre future prévue pour lui. La liste des participants à chaque débat fixe le jury ; aucun arrivant en cours de débat ne vote. En l'absence de rencontre encore jouable dans le tour, passer au suivant si possible, sinon terminer avec les scores acquis.

## Bonus et malus

L'option reste désactivée par défaut. Intégrer le catalogue commun et les effets de vote pertinents : éclair, dé, squatteur et rideau, pendant le vote uniquement. Tous les participants peuvent lancer un objet ; seuls les jurés votent. Les défenses et la poche gardent leurs règles habituelles. Le dé mélange les deux choix sur les téléphones, jamais sur la télé ; le choix reste lié au débatteur. Exclure les effets d'écriture, de dessin, de musique et les déformations de mots, qui n'ont pas d'interaction utile ici.

Le système de gains, la pondération selon le retard, les remplacements, animations et sons restent communs. Les effets temporels et leur dette portent sur le vote, jamais sur les prises de parole. Le catalogue indique la phase voting pour ce mode ; tester explicitement les transitions qui accordent les objets et démarrent les dettes.

## Architecture et vérifications prévues

Service TribunalGame raccordé à GameEngine et aux contrôleurs existants ; catalogue de sujets local sans migration ni dépendance supplémentaire. État JSON : calendrier, sujet, débatteurs, participants/jurés, phase, échéance, votes privés et gains. Projection publique sans votes avant verdict ; projection privée avec droit de parler/voter.

Tests métier en échec avant implémentation : minimum de joueurs, rotation/camps, phases et échéances, parole autorisée, votes privés/définitifs, égalités/abstentions, score idempotent, objectif, pause/reprise, départs/reconnexion, arrivants et bonus compatibles. Tests navigateur télé et téléphone : parcours complet, lisibilité sans scroll, refus des commandes côté télé et vote. Contrôles du README, revue, PR liée #54, CI puis squash merge.
