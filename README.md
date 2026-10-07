# Plummo

Jeux entre amis et en famille : un grand écran commun et un téléphone par joueur.

Le dépôt contient le **socle technique**, les spécifications validées et les références des maquettes. Les salons, mini-jeux et l’administration restent à développer.

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

Les tests utilisent `plummo_testing` pour tout futur accès MySQL. Aucun test actuel ne nécessite de données. Aucun compte n’est créé par les seeders.

## Références

- [Produit](PRODUCT.md) et [règles métier](docs/metier-plummo.md)
- [Parcours et écrans](docs/parcours-et-ecrans-plummo.md)
- [Maquettes Figma](https://www.figma.com/design/6kioGGc1qTiFtyAjvsHSqw/Projet-jeu-tel---pc)
- [Architecture technique](docs/technical-architecture.md)
- [Contribuer](CONTRIBUTING.md)

Aucun hébergement de production n’est configuré. Les sources publiques ne constituent pas une autorisation de réutilisation des créations graphiques.

## Kit vectoriel Plummo

La [planche de personnalisation](public/plummo/index.html) présente la mascotte et 22 accessoires indépendants. Sur un serveur du dossier public, ouvrir `/plummo/index.html`. Les [conventions du kit](public/plummo/README.md) expliquent les calques, les couleurs et l’ajout de pièces. Reconstruire la planche après modification des SVG ou des libellés : `php scripts/build-plummo-preview.php`.
