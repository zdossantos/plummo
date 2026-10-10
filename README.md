# Plummo

Jeux entre amis et en famille : un grand écran commun et un téléphone par joueur.

Le dépôt contient le socle technique, les salons sans compte, la personnalisation des Plummos et les réglages de session. Le catalogue administrateur, les quatre mini-jeux, les bulles de chat et les animations sont disponibles.

## Installation

PHP 8.4, Composer 2, Node 22 (outils navigateur), Bun **1.3.14** et Docker Compose sont nécessaires.

```sh
composer install
cp .env.example .env
php artisan key:generate
bun install --frozen-lockfile
bunx playwright install chromium
docker compose up --build -d
```

Ouvrir http://localhost:8090. MySQL 8.4 et Redis 7.4 sont disponibles uniquement sur la machine locale (ports 3309 et 6380). Les identifiants de `.env.example` servent exclusivement au développement.

Les migrations restent explicites :

```sh
docker compose exec web php artisan migrate
```

Pour développer avec rechargement : garder MySQL/Redis en Docker, puis lancer `composer dev` sur la machine. Après une modification, reconstruire l’image pour actualiser la version Docker.

## Vérifications

```sh
composer lint:check
composer analyse
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run test:unit
bun run build
php artisan test
docker build --target runtime -t plummo:ci .
```

Les tests métier et navigateur utilisent exclusivement MySQL `plummo_testing`. Démarrer MySQL puis migrer explicitement cette base avant les tests :

```sh
APP_ENV=testing DB_DATABASE=plummo_testing php artisan migrate --force
```

Aucun compte n’est créé par les seeders.

## Références

- [Produit](PRODUCT.md) et [règles métier](docs/metier-plummo.md)
- [Parcours et écrans](docs/parcours-et-ecrans-plummo.md)
- [Maquettes Figma](https://www.figma.com/design/6kioGGc1qTiFtyAjvsHSqw/Projet-jeu-tel---pc)
- [Architecture technique](docs/technical-architecture.md)
- [Contribuer](CONTRIBUTING.md)
- [Validation V1 et essai sur appareils](docs/validation-v1.md)

Le [déploiement Coolify](docs/operations.md) est préparé pour `plummo.zdossantos.fr`, avec MySQL et Redis dédiés. Sa mise en service et ses vérifications restent à effectuer. Les sources publiques ne constituent pas une autorisation de réutilisation des créations graphiques.

## Kit vectoriel Plummo

La [planche de personnalisation](public/plummo/index.html) présente la mascotte et 28 accessoires indépendants. Sur un serveur du dossier public, ouvrir `/plummo/index.html`. Les [conventions du kit](public/plummo/README.md) expliquent les calques, les couleurs et l’ajout de pièces. Reconstruire la planche après modification des SVG ou des libellés : `php scripts/build-plummo-preview.php`.

## Salons

Le grand écran conserve le QR de connexion en haut à droite pendant le jeu, les pauses et les résultats. Dans le salon, le QR, le code et l’adresse de saisie sont agrandis pour être lisibles à distance ; les formats de faible hauteur utilisent une invitation plus compacte. L’affichage reste sans scroll ni pagination, et les arrivées pendant une manche suivent les règles d’attente existantes.

Ouvrir `/` sur le grand écran crée un salon (une actualisation retrouve le même salon). Les téléphones entrent via le QR ou `/join` avec le code de six caractères. Chaque joueur choisit son prénom, sa couleur et un accessoire par emplacement (tête, visage, cou et main). Huit places maximum ; le premier arrivé devient chef et peut transférer son rôle ou fermer le salon avec confirmation.

Le navigateur du téléphone conserve une identité privée dans un cookie chiffré HttpOnly : revenir avec ce même navigateur retrouve le Plummo et les points. Effacer les cookies ou utiliser un autre navigateur crée une autre identité. Un départ volontaire libère la place immédiatement ; une déconnexion détectée après quinze secondes réserve la place deux minutes. Un retour dans un salon plein attend une place disponible.

Les états se synchronisent toutes les deux secondes et immédiatement à réception des événements Reverb lorsque celui-ci est activé. Chaque téléphone relit son état privé ; aucune réponse secrète ne passe par les événements.

Pour tester avec de vrais téléphones sur le même réseau, définir `APP_URL=http://ADRESSE_LOCALE_DU_PC:8000` dans `.env`, exécuter les migrations de développement puis lancer `composer dev`. Ouvrir cette adresse sur le grand écran et les téléphones. Les liens QR et de saisie utilisent `APP_URL` ; `localhost` n’est joignable que depuis le PC. Les ports Compose restent accessibles uniquement depuis le PC.

Un salon sans joueur connecté expire après trente minutes, même si le grand écran reste ouvert. Le nettoyage est planifié chaque minute : Compose lance le service `scheduler`, et `composer dev` lance `schedule:work`. Les migrations doivent être effectuées explicitement avant de démarrer ces processus. Nettoyage manuel : `php artisan rooms:prune`. En production, prévoir l’exécution régulière de `schedule:run` lors de la configuration de l’hébergement.

## Scores et session

Le chef règle un objectif entier (1 000 points par défaut) ou choisit le mode sans limite depuis son téléphone. Prolonger fixe le nouvel objectif au meilleur score actuel, augmenté des points demandés. Recommencer remet tous les scores à zéro après confirmation, en conservant les identités et les Plummos. Le classement global inclut les joueurs partis et partage les rangs en cas d’égalité.

Le service serveur `Scoring` centralise les barèmes validés et leurs arrondis. Aucun téléphone ne peut attribuer des points. Le quiz applique ce calcul côté serveur. La manche qui atteint l’objectif se termine, puis le salon conserve les scores ; le chef choisit une prolongation, le mode sans limite ou une remise à zéro avant de rejouer. Les réglages de session sont verrouillés pendant un mini-jeu.

## Administration

L’administration est accessible sur `/admin` avec un compte dédié. Aucune inscription publique ni compte joueur n’est ajouté. Après migration explicite de la base souhaitée, créer un administrateur avec `php artisan admin:create` : nom, adresse e-mail et mot de passe confirmé de 12 caractères minimum sont saisis interactivement ; le mot de passe est masqué. La commande refuse les adresses déjà utilisées et ne transforme aucun compte existant. Aucun administrateur n’est créé automatiquement.

La connexion utilise Fortify, limitée à cinq tentatives par minute et combinaison e-mail/adresse IP. Toutes les pages d’administration exigent la permission serveur `administer`. Le catalogue permet de créer et publier les questions, morceaux, mots à dessiner et débuts de phrases. Les brouillons incomplets restent privés. Les tags sont réutilisables et chaque pack exige tous ses tags ; les sélections de plusieurs packs forment une union sans doublons. Un tag requis par un pack ne peut pas être supprimé.

Les extraits préparés (MP3, WAV, OGG ou M4A, 20 Mio maximum) sont stockés sur le disque privé et écoutables uniquement par un administrateur. Les fichiers remplacés ou supprimés sont nettoyés. Aucun contenu ni audio de démonstration n’est fourni. L’onglet Imports accepte CSV UTF-8 (virgule ou point-virgule) et Excel XLSX (première feuille), avec modèles CSV par jeu. Les tags doivent exister et sont séparés par `|` ; une bonne réponse quiz utilise A/B/C/D. L’aperçu indique les erreurs par ligne, les doublons potentiels et les noms audio absents, ambigus ou inutilisés. Après vérification, ajouter les lignes valides en brouillon ou les publier explicitement. Aucune ligne ne remplace un contenu existant. Les erreurs restent exportables jusqu’à expiration du lot.

Limites V1 : 500 lignes, tableau 5 Mio, 50 extraits de 20 Mio. Les lots sont privés à leur administrateur, expirent après une heure et refusent une seconde confirmation. Le serveur revalide les lignes avant ajout. `imports:prune` nettoie les fichiers temporaires via le scheduler ; aucune migration automatique. L’image runtime fixe les limites PHP compatibles avec ces uploads ; configurer aussi la limite du proxy et l’espace temporaire lors du déploiement. En développement hors Docker, ajuster les limites PHP locales en conséquence.

## Quiz et temps réel

Le chef connecté choisit jusqu’à trois packs, 5 à 30 questions et une durée de 10 à 150 secondes (30 par défaut). Quatre choix sont affichés dès le début ; une réponse est définitive et porte l’identifiant de partie et de manche pour refuser les requêtes retardées. La manche finit quand les participants encore disponibles ont répondu, ou à l’échéance ; la bonne réponse reste affichée trois secondes. Les points sont validés une seule fois. Les résultats distinguent le classement du mini-jeu et celui de la session.

Les arrivants et joueurs reconnectés attendent la prochaine question. La perte du grand écran ou de tous les joueurs met automatiquement en pause ; le chef peut aussi mettre en pause puis arrêter, en conservant les points des manches terminées. Toute reprise laisse cinq secondes pour se préparer. Si le catalogue est épuisé après une modification administrative, le chef choisit d’autres packs ou autorise explicitement la répétition ; l’historique reste conservé.

Compose fournit `scheduler`, `worker` et `reverb` avec stockage privé partagé. Le scheduler traite les échéances chaque seconde, même si aucun téléphone ne fait de requête. Pour activer Reverb, définir `BROADCAST_CONNECTION=reverb`, renseigner `REVERB_APP_ID`, une clé publique `REVERB_APP_KEY` et un secret serveur `REVERB_APP_SECRET` dans `.env`. Choisir `REVERB_ALLOWED_ORIGINS` comme liste de noms d’hôtes séparés par des virgules. Le navigateur utilise `REVERB_PUBLIC_HOST/PORT/SCHEME` (localhost:8081 en Compose) ; le backend utilise l’adresse interne `reverb:8080`. Aucune valeur `VITE_*` secrète n’est nécessaire. La relecture HTTP reste disponible si le WebSocket est absent.

Hors Docker, lancer également `php artisan queue:work` et `php artisan reverb:start --port=8081` avec `REVERB_HOST=127.0.0.1` et `REVERB_PORT=8081`. Sur réseau local, remplacer le nom d’hôte public par celui joignable depuis les téléphones et autoriser cette origine. La CI vérifie le démarrage réel du serveur et la négociation WebSocket.

## Blind test

Le chef choisit le blind test depuis le même formulaire, avec ses propres quantité et durée. Chaque extrait propose immédiatement huit couples titre/artiste. Les sept faux choix partagent en priorité un tag de la chanson ; le catalogue publié global complète les choix si nécessaire. Il doit contenir au moins huit couples distincts. Seules les chansons réellement jouées entrent dans l’historique du salon.

Le grand écran lit l’extrait préparé en boucle pendant la réponse. Si le navigateur bloque la lecture automatique, cliquer sur « Activer le son ». La pause et les cinq secondes de reprise suspendent le son en conservant la position. Une copie privée garde l’extrait jouable même après suppression de sa source dans l’administration ; elle est nettoyée après la révélation au passage suivant, à la fin ou à l’arrêt du mini-jeu. La consultation du classement conserve le lecteur. Une coupure de connexion suspend le son localement ; un échec de chargement permet un nouvel essai. L’URL audio exige la session propriétaire du grand écran et n’est pas envoyée aux téléphones.
## Dessin à deviner

Le chef peut lancer le dessin à deviner pour deux à huit joueurs, de un à cinq tours et de 30 à 150 secondes par dessin (90 secondes par défaut). Chaque tour fait passer tous les joueurs connectés qui n’ont pas encore dessiné ; le dessinateur choisit un mot parmi trois propositions privées sur son téléphone et trace en direct sur le grand écran.

Les autres joueurs écrivent leur proposition sur leur téléphone. La comparaison ignore la casse, les accents, les espaces, les tirets et la ponctuation ; une réponse proche reçoit un indice privé. Les bonnes réponses donnent davantage de points aux premiers joueurs, tandis que le dessinateur reçoit une part proportionnelle aux joueurs qui ont trouvé. Le tour passe immédiatement à la révélation quand tout le monde a trouvé. Les points acquis sont conservés pendant une pause ou une déconnexion. Un arrêt depuis la pause annule les points du dessin en cours et conserve ceux des dessins terminés ou passés.

Le dessin est transmis par traits SVG normalisés avec révision et identifiants idempotents. Une reconnexion reprend le joueur à sa place ; un dessinateur absent gèle le tour et les joueurs connectés peuvent voter unanimement pour le passer. Le mot choisi reste privé jusqu’à la révélation.

## Phrase à compléter

Le chef lance de un à cinq tours pour au moins trois joueurs connectés, avec une durée d’écriture de 30 à 150 secondes (60 par défaut). Tous reçoivent le même début de phrase ; chaque suite privée est limitée à 150 caractères Unicode. Le brouillon est sauvegardé pendant la frappe. La validation le verrouille ; l’échéance soumet les brouillons sauvegardés non vides, sans créer de proposition pour un champ vide.

Les phrases complètes sont présentées anonymement une à une : cinq secondes jusqu’à 40 caractères, puis 0,05 seconde par caractère, au maximum douze secondes. Les téléphones et le grand écran affichent ensuite toutes les propositions pendant le vote (30 secondes maximum). Chaque joueur peut envoyer un vote définitif pour une autre phrase ; aucune abstention n’est remplacée par un vote automatique. Les auteurs, leurs Plummos, les votes et les points sont révélés ensemble. Chaque vote reçu rapporte exactement 65 points, y compris à un auteur ayant quitté le salon ou n’ayant pas voté.

Les nouveaux arrivants et les joueurs de retour participent au prochain tour. La pause conserve les brouillons et gèle les délais ; la reprise laisse cinq secondes. L’arrêt depuis la pause conserve uniquement les scores des tours terminés. L’objectif de session et la récupération après épuisement des packs utilisent le moteur commun.

## Chat et célébrations

Le chat apparaît sur le téléphone lorsqu’aucune action de jeu n’est attendue : dans le salon, pendant une pause ou après une réponse, un mot trouvé, une phrase validée ou un vote. Le serveur vérifie cette disponibilité à chaque envoi et refuse les messages d’une ancienne partie ou manche. Les messages sont limités à 80 caractères Unicode, avec trois secondes entre deux envois ; une bulle reste visible cinq secondes au-dessus du Plummo sur le grand écran. Un nouvel envoi remplace la bulle précédente.

Les gains sont célébrés une fois à la révélation et les gagnants du mini-jeu sont affichés avec leurs Plummos, y compris les ex æquo. Les animations respectent la préférence de réduction des mouvements. Appliquer explicitement les migrations sur la base souhaitée avant de démarrer cette version.
