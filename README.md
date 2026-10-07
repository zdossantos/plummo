# Plummo

Jeux entre amis et en famille : un grand écran commun et un téléphone par joueur.

Le dépôt contient le socle technique, les salons sans compte, la personnalisation des Plummos et les réglages de session. Le catalogue administrateur est disponible ; les mini-jeux restent à développer.

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

Aucun hébergement de production n’est configuré. Les sources publiques ne constituent pas une autorisation de réutilisation des créations graphiques.

## Kit vectoriel Plummo

La [planche de personnalisation](public/plummo/index.html) présente la mascotte et 28 accessoires indépendants. Sur un serveur du dossier public, ouvrir `/plummo/index.html`. Les [conventions du kit](public/plummo/README.md) expliquent les calques, les couleurs et l’ajout de pièces. Reconstruire la planche après modification des SVG ou des libellés : `php scripts/build-plummo-preview.php`.

## Salons

Ouvrir `/` sur le grand écran crée un salon (une actualisation retrouve le même salon). Les téléphones entrent via le QR ou `/join` avec le code de six caractères. Chaque joueur choisit son prénom, sa couleur et jusqu’à deux accessoires. Huit places maximum ; le premier arrivé devient chef et peut transférer son rôle ou fermer le salon avec confirmation.

Le navigateur du téléphone conserve une identité privée dans un cookie chiffré HttpOnly : revenir avec ce même navigateur retrouve le Plummo et les points. Effacer les cookies ou utiliser un autre navigateur crée une autre identité. Un départ volontaire libère la place immédiatement ; une déconnexion détectée après quinze secondes réserve la place deux minutes. Un retour dans un salon plein attend une place disponible.

Les listes se synchronisent toutes les cinq secondes. Les mini-jeux ne sont pas encore disponibles : les points sont conservés et affichés, sans gain possible pour le moment. Reverb/Echo sera intégré aux interactions de jeu.

Pour tester avec de vrais téléphones sur le même réseau, définir `APP_URL=http://ADRESSE_LOCALE_DU_PC:8000` dans `.env`, exécuter les migrations de développement puis lancer `composer dev`. Ouvrir cette adresse sur le grand écran et les téléphones. Les liens QR et de saisie utilisent `APP_URL` ; `localhost` n’est joignable que depuis le PC. Les ports Compose restent accessibles uniquement depuis le PC.

Un salon sans joueur connecté expire après trente minutes, même si le grand écran reste ouvert. Le nettoyage est planifié chaque minute : Compose lance le service `scheduler`, et `composer dev` lance `schedule:work`. Les migrations doivent être effectuées explicitement avant de démarrer ces processus. Nettoyage manuel : `php artisan rooms:prune`. En production, prévoir l’exécution régulière de `schedule:run` lors de la configuration de l’hébergement.

## Scores et session

Le chef règle un objectif entier (1 000 points par défaut) ou choisit le mode sans limite depuis son téléphone. Prolonger fixe le nouvel objectif au meilleur score actuel, augmenté des points demandés. Recommencer remet tous les scores à zéro après confirmation, en conservant les identités et les Plummos. Le classement global inclut les joueurs partis et partage les rangs en cas d’égalité.

Le service serveur `Scoring` centralise les barèmes validés et leurs arrondis. Aucun téléphone ne peut attribuer des points ; les futurs mini-jeux appliqueront ces calculs. L’arrêt après la manche atteignant l’objectif et le verrouillage des réglages pendant un mini-jeu seront intégrés avec le moteur de manches.

## Administration

L’administration est accessible sur `/admin` avec un compte dédié. Aucune inscription publique ni compte joueur n’est ajouté. Après migration explicite de la base souhaitée, créer un administrateur avec `php artisan admin:create` : nom, adresse e-mail et mot de passe confirmé de 12 caractères minimum sont saisis interactivement ; le mot de passe est masqué. La commande refuse les adresses déjà utilisées et ne transforme aucun compte existant. Aucun administrateur n’est créé automatiquement.

La connexion utilise Fortify, limitée à cinq tentatives par minute et combinaison e-mail/adresse IP. Toutes les pages d’administration exigent la permission serveur `administer`. Le catalogue permet de créer et publier les questions, morceaux, mots à dessiner et débuts de phrases. Les brouillons incomplets restent privés. Les tags sont réutilisables et chaque pack exige tous ses tags ; les sélections de plusieurs packs forment une union sans doublons. Un tag requis par un pack ne peut pas être supprimé.

Les extraits préparés (MP3, WAV, OGG ou M4A, 20 Mio maximum) sont stockés sur le disque privé et écoutables uniquement par un administrateur. Les fichiers remplacés ou supprimés sont nettoyés. Aucun contenu ni audio de démonstration n’est fourni. Les imports CSV et Excel seront ajoutés séparément.
