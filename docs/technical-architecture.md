# Architecture effectivement initialisée

## Socle

PHP 8.4, Laravel 13, Inertia 3, Vue 3 Composition API/TypeScript, Vite 8, Bun 1.3.14, Tailwind 4, Reka/shadcn-vue (bouton), Lucide. Versions exactes dans les lockfiles. Le starter officiel Vue a été adapté : suppression de Vite Plus et des parcours de comptes joueurs, migrations automatiques retirées.

Source : https://github.com/laravel/vue-starter-kit (commit d282e817c6c2fa1bd475f7c42ea785ccfc67d0ab), documentation https://laravel.com/docs/13.x/starter-kits et https://inertiajs.com/docs/v3.

La page d’entrée décrit le fonctionnement prévu, avec traductions Laravel FR/EN (langue du navigateur, français par défaut) et thème clair/sombre/système persistant. Elle ne crée pas encore de salon.

MySQL 8.4 et Redis 7.4 sont configurés en Compose. Sessions/cache/files utilisent Redis. L’image Apache/PHP 8.4 contient les assets et extensions ; cible runtime commune pour les futurs processus. Les migrations restent une commande explicite. La base de tests est distincte.

## Qualité et livraison

Pest, Pest Browser/Playwright Chromium, Bun test, Pint, Larastan niveau 7, ESLint, Prettier, vue-tsc. CI : backend/frontend/navigateur, image runtime, démarrage et healthcheck. Dependabot couvre Composer, Bun et Actions.

Release Please maintient une PR de release après les merges sur `main`, avec un déclenchement manuel de secours. La fusion volontaire de cette PR publie la version GitHub et son changelog. Les contrôles de sa branche sont déclenchés explicitement avec le jeton intégré à GitHub Actions. Le [processus de livraison](quality-ci-cd.md) détaille les protections et les vérifications.

GHCR, Coolify, architecture du serveur, domaines, sauvegardes et production restent à configurer lors du choix d’hébergement. Aucun déploiement n’est inclus dans ce socle.

## Suite métier

Les règles et maquettes existantes restent les sources de vérité. Fortify sera activé pour l’administration lors de son implémentation ; aucune inscription joueur n’est exposée. Reverb/Echo seront intégrés avec les salons temps réel, et le stockage audio avec l’administration des contenus. Aucun service mail/analytics/SEO/PWA ajouté par anticipation.

Playwright est épinglé à 1.60.0 : les versions suivantes ont provoqué une attente indéfinie dans Pest Browser lors des vérifications initiales. Réévaluer cet épinglage avec une suite navigateur réussie lors des mises à jour.
