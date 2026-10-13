# Le bluff des Plummos — proposition pour l’issue #53

Statut : proposition à valider avant implémentation. Base : main fcab70d (0.9.2).

## Intention

Un jeu autonome, dès trois joueurs, dans lequel chacun invente une réponse crédible pour tromper les autres. La télé présente la question, les propositions anonymes, puis révèle la vérité et leurs auteurs avec leurs Plummos. Les téléphones servent à écrire et voter. Réutiliser le salon, les classements, les packs administrables et le système de bonus existants.

## Approche proposée

Créer un moteur BluffGame séparé, orchestré par GameEngine comme PhraseGame et TribunalGame. Réutiliser les composants de rédaction, les cartes de choix et le podium, sans transformer le jeu Phrases ni dupliquer son catalogue.

Deux alternatives écartées : convertir Phrases en variantes (mélange les règles et les contenus), ou générer la vérité automatiquement (qualité et vérifiabilité insuffisantes). Chaque sujet contient une vérité renseignée dans l’administration.

## Réglages et déroulement

- Trois à huit joueurs présents au lancement.
- Cinq manches par défaut, réglables de cinq à trente, dans la limite du catalogue.
- Écriture : 60 secondes par défaut, réglable de 30 à 150 secondes ; réponse de 120 caractères maximum.
- Lecture : chaque proposition bénéficie de trois secondes de présentation sur la télé ; elle n’est plus modifiable. Aucun clic sur la télé.
- Vote : 25 secondes, un choix définitif. Fin anticipée lorsque tous les votants attendus ont choisi.
- Révélation : huit secondes ; vérité verte, auteurs et votes dévoilés, gains individuels affichés, puis manche suivante automatique.
- Bonus optionnels, désactivés initialement comme au Tribunal.
- Un à trois packs, contenus inédits prioritaires, répétition seulement si le chef l’autorise. Catalogue épuisé : pause avec ajustement des packs/répétitions depuis le téléphone du chef.

L’écriture se termine lorsque tous les joueurs encore attendus ont validé ou lorsque le délai expire. Les brouillons non validés ne deviennent pas des bluffs. Le téléphone confirme la validation sans dévoiler les autres réponses ni la vérité.

## Contenus administrables

Nouveau type `bluff`, avec question (500 caractères maximum), réponse vraie (120 caractères maximum) et format : définition, expression ou anecdote. Les trois formats utilisent la même mécanique et peuvent cohabiter dans un pack.

Exemples de structure : « Que signifie ce mot ? », « Comment se termine cette expression ? », « Quel détail manque à cette anecdote ? ». La formulation doit rendre les propositions courtes et comparables. Pas de sujet codé en dur ; les administrateurs ajoutent, modifient, publient et importent les contenus comme pour les autres jeux.

Import CSV/XLSX : `question,reponse,format,tags`. Les tags restent des catégories simples et génériques ; les packs suivent les règles communes. Le contenu est copié dans la manche pour résister à une modification ou suppression administrative. La réponse vraie ne doit jamais figurer dans la projection d’un appareil avant la révélation, sauf retour privé décrit ci-dessous.

## Vote et points

Chaque vote distribue 65 points : au votant qui choisit la vérité, ou à l’auteur du bluff choisi. On peut donc gagner en trouvant la vérité et en trompant plusieurs adversaires. Aucun bonus de rapidité ni prime de podium : l’enveloppe reste 65 points par votant, comme la base commune.

Interdiction de voter pour sa propre proposition, y compris si elle a plusieurs auteurs. L’interdiction est serveur ; les cartes ne révèlent jamais les auteurs adverses. Un joueur sans contribution peut voter normalement.

Doublons : comparaison insensible à la casse, aux accents, aux espaces répétés et à la ponctuation. Pas de rapprochement sémantique ni de correction automatique. Deux bluffs identiques deviennent une seule carte, avec plusieurs auteurs révélés à la fin. Les points des votes reçus sont partagés entre eux et arrondis une seule fois par auteur à la fin de la manche ; les petits écarts d’arrondi suivent les règles communes.

Une contribution identique à la vérité ne crée pas de seconde carte vraie. À la fin de l’écriture, son auteur est informé uniquement sur son téléphone qu’il a trouvé la vérité ; il ne vote pas et reçoit 65 points à la révélation. Il ne touche pas les votes des autres pour la vérité. Aucun indice n’est envoyé pendant l’écriture et aucune réécriture n’est autorisée après validation.

Si aucun bluff distinct de la vérité n’est disponible, la manche passe directement à la révélation : seuls les auteurs ayant réellement écrit la vérité reçoivent leurs 65 points, aucun vote automatique n’est inventé. Une abstention vaut zéro. Les scores sont engagés une seule fois sous verrou du salon ; l’objectif de session est vérifié après cette attribution.

## Télé et manette

Grand écran passif, sans scroll ni pagination, QR/code toujours accessibles dans l’en-tête. Question en grand. Lecture automatique des propositions, puis tableau de vote avec jusqu’à neuf cartes (huit bluffs et une vérité), en grille 3 × 3. Les textes complets restent visibles ; chaque réponse garde sa lettre, sa couleur et son motif lorsqu’elle se déplace. Prévoir une neuvième identité visuelle distincte des huit déjà disponibles.

Téléphone : question compacte avec lecteur complet, saisie pendant l’écriture ; lettres dans les cartes pendant le vote, avec lecteur pour leur texte. Jusqu’à neuf choix en grille 3 × 3, sans masquer les commandes. Conserver le HUD validé : options en bas à gauche, bonus en bas à droite, objet en attente plus petit. Aucun nom ni code de salon réintroduit dans ce HUD. Le clavier ne doit pas recouvrir la saisie ou la validation.

À la révélation, les Plummos des auteurs apparaissent sur leurs propositions et ceux des votants montrent qui a été piégé ; les gains sont distincts du score cumulé. Réutiliser les animations et respecter les mouvements réduits.

## Bonus/malus

Première intégration : catalogue commun, éclair, dé, squatteur, alphabet retourné, mot mangé et rideau, pendant le vote uniquement. Le dé déplace les cartes entières en conservant leur identité. Les effets de texte compatibles concernent aussi le grand écran selon les règles communes ; la vérité ne doit jamais être signalée par un traitement différent avant la révélation.

Défenses, répartition selon classement, troisième objet en attente, événements visuels, sons, pause et déduplication restent communs. Les durées et dettes temporelles portent sur les 25 secondes de vote, jamais sur la lecture.

Exclure les modifications de phrases publiées : une transformation pourrait fabriquer un doublon ou faire disparaître la seule vérité. Exclure les effets spécifiques au dessin et au lecteur blind test. Aucun malus n’altère le brouillon de rédaction.

## Présence, sécurité et pause

Participants figés au début de chaque manche ; arrivants et retours exclus de la manche en cours attendent la suivante. Un départ retire le joueur des actions attendues mais conserve sa contribution validée et son vote déjà reçu. Une absence ne bloque jamais la transition. Aucun participant actif : suspension automatique selon le moteur commun. Moins de trois présents avant la manche suivante : attente du retour des joueurs, sans consommer un nouveau contenu.

Pause et reprise conservent temps et état, avec le compte à rebours commun de reprise. Reconnexion : retrouver brouillon, validation, vote et poche sans rejouer les événements ni dévoiler les auteurs. Arrêt depuis une pause : conserver les manches terminées, annuler la manche courante.

Laravel valide toutes les actions, le périmètre partie/manche, la phase, les délais, la présence et les votes. Les identifiants des choix sont opaques ; aucune clé ou position ne révèle la vérité. Les projections publiques et privées sont testées séparément, y compris pendant pause, lecture et reconnexion. Textes visibles dans lang/fr et lang/en.

## Vérification prévue

Tests métier d’abord, échec pertinent avant implémentation : seuil de joueurs, packs et historique, confidentialité, transitions, doublons, vérité écrite, auto-vote, abstentions, arrondis, idempotence des gains, objectif global, départs/retours, pause/reprise et actions périmées. Tests d’import et d’administration pour le nouveau type.

Tests bonus : compatibilité, chaque phase autorisée/refusée, temporisation, dette, défenses et identité des cartes sous dé. Tests de projection pour les effets sur la télé.

Tests UI sur les dix formats existants, avec trois et huit joueurs, neuf propositions de longueur maximale, clavier, poche pleine et objet en attente, lecture, vote, révélation et pause. Vérifier absence de scroll, visibilité des actions et stabilité des cartes pendant les animations. Exécuter les commandes de validation du README et attendre la CI verte avant fusion de la PR.
