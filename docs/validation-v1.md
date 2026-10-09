# Validation de la V1

Le 8 octobre 2026, les quatre jeux et l’administration sont disponibles. La validation automatisée utilise exclusivement `plummo_testing`, Chromium et plusieurs contextes de téléphone indépendants. Chaque téléphone possède sa propre session.

## Parcours couverts

| Parcours | Vérification |
| --- | --- |
| QR/code, personnalisation, retour avec même identité | [Navigateur salons](../tests/Browser/RoomTest.php), [règles des salons](../tests/Feature/Rooms/RoomTest.php) |
| Huit places, déconnexion, réservation, transfert du chef et attente si plein | [Règles des salons](../tests/Feature/Rooms/RoomTest.php) |
| Quiz sur TV et deux téléphones ; objectif atteint ; rechargement avec score conservé ; prolongation et préparation suivante | [Navigateur quiz](../tests/Browser/QuizGameTest.php), [session](../tests/Feature/Rooms/SessionTest.php) |
| Blind test, huit choix, lecture audio et pause | [Navigateur blind test](../tests/Browser/BlindGameTest.php), [moteur](../tests/Feature/Rooms/BlindGameTest.php) |
| Dessin, rotations, mots privés, réponse exacte, dessinateur absent et votes unanimes | [Navigateur dessin](../tests/Browser/DrawingGameTest.php), [moteur](../tests/Feature/Rooms/DrawingGameTest.php) |
| Phrases sur trois téléphones, anonymat, présentation, vote, auteurs et scores | [Navigateur phrases](../tests/Browser/PhraseGameTest.php), [moteur](../tests/Feature/Rooms/PhraseGameTest.php) |
| Chat selon actions, limites Unicode, remplacement, expiration, envois retardés et ex æquo | [Règles du chat](../tests/Feature/Rooms/ChatTest.php), [navigateur chat](../tests/Browser/ChatTest.php) |
| Huit bulles de 80 emoji visibles à 1366 × 768, résultats longs, réduction des mouvements | [Navigateur chat](../tests/Browser/ChatTest.php) |
| Catalogue, publication, packs et imports avec confirmation | [Navigateur catalogue](../tests/Browser/AdminCatalogTest.php), [imports](../tests/Browser/AdminImportsTest.php), [tests administrateur](../tests/Feature/Admin) |
| Pause/reprise, délais serveur, transport privé et historique des contenus | [Transport](../tests/Feature/Rooms/GameTransportTest.php), [quiz](../tests/Feature/Rooms/QuizGameTest.php), [historique](../tests/Feature/Rooms/ContentHistoryTest.php) |

Les commandes reproductibles sont dans le [README](../README.md#vérifications). `composer ci:check` regroupe analyse, lint, format, types, tests frontend, build et tests PHP/navigateur. Les migrations restent explicites ; la base de test doit être préparée avant la commande.

## Livraison automatisée

La [CI](../.github/workflows/ci.yml) vérifie également l’image runtime Docker, les routes de démarrage et la négociation WebSocket Reverb. La [release automatisée](../.github/workflows/release.yml) ouvre les propositions Release Please et déclenche leurs contrôles même lorsqu’elles sont créées avec `GITHUB_TOKEN`. Une fusion fonctionnelle n’est autorisée qu’après succès des contrôles du dernier commit. Les releases restent proposées dans une PR séparée.

## Reproduire sur un réseau local

Les essais automatisés couvrent les navigateurs ; un essai sur les appareils utilisés pour jouer permet de juger la lecture à distance, le clavier et le son.

1. Suivre les instructions réseau du [README](../README.md#salons) : `APP_URL` joignable depuis les téléphones, migration explicite de la base de développement et processus de l’application. Préparer des packs publiés via l’administration ; aucun contenu de démonstration n’est fourni.
2. Ouvrir le salon sur la TV. Rejoindre depuis au moins trois téléphones, par QR et par code ; choisir des accessoires et vérifier un rechargement depuis le même navigateur.
3. Choisir un objectif bas, finir une manche de quiz, vérifier les points et les gagnants, revenir au salon puis prolonger. Les scores et identités doivent rester présents et un nouveau jeu devenir disponible.
4. Jouer les autres jeux ; mettre en pause et reprendre, reconnecter un joueur et interrompre le dessinateur. Vérifier la reprise au bon moment et le vote pour passer.
5. Envoyer des messages une fois l’action terminée, vérifier leur expiration et le retour aux commandes à la nouvelle manche. Vérifier la lisibilité de huit joueurs et le son du blind test avec les réglages d’accessibilité habituels.

Aucun hébergement de production n’est configuré. La validation des appareils locaux et le déploiement dépendent du matériel et de la cible retenus.

## Articulation des Plummos — 9 octobre 2026

Le kit conserve le SVG original et expose corps, ombrage, pieds, bras/mains, plumes, sourcils, yeux, iris, reflets, joues, bouche et langue. Les gestes d’attente, réponse, points, reprise et podium utilisent les événements confirmés du serveur. Les objets tenus passent devant le visage avec leur main ; les masques et lunettes suivent le visage. Une reconnexion consomme le premier état frais silencieusement.

`bun scripts/check-plummo-rig.ts` : Chromium et WebKit valident chacun 180 comparaisons de repos strictement identiques (six couleurs, chaque accessoire et une combinaison complète). Huit objets simultanés gardent leurs références locales. Les transformations indépendantes vérifient le couplage des reflets de plumes et des accessoires du visage ; la réduction des mouvements laisse zéro animation active. Les captures des gestes ont été examinées visuellement.

Le test [PlummoMotionTest](../tests/Browser/PlummoMotionTest.php) couvre téléphone 390 × 844 et écran 1366 × 768 : réponse jouée une seule fois, retour au repos, rechargement silencieux, réduction des mouvements et absence de scroll. Le pied de scène mobile réserve 48 px et s’efface entièrement en mode compact. Les claviers restent simulés : aucun essai matériel iPhone ou TV n’est revendiqué.

La revue indépendante a identifié l’espace mobile, la reconnexion montée et les attaches de reflets/accessoires ; ces trois points ont été corrigés puis relus sans défaut important restant.

Sorties du 9 octobre : `composer ci:check` passe avec 165 tests PHP/navigateur et 1 784 assertions, ainsi que 13 tests frontend et 133 assertions. Le parcours dédié Safari passe avec 21 assertions. La matrice a également révélé une réservation d’espace persistante du lecteur après passage d’une réponse longue à une courte ; la correction retire cette réservation avant la nouvelle mesure. Le cas 320 × 568 anglais passe avec 45 contrôles de géométrie et d’interaction.

Les cinq tailles 320 × 568, 390 × 844, 844 × 390, 1366 × 768 et 1920 × 1080 passent en anglais. Après ajustement des marges et du statut du brouillon sur petit écran, les cinq tailles françaises passent également : 229 contrôles de géométrie et d’interaction. La version Docker est reconstruite et `/up` répond HTTP 200 sur le réseau local.
