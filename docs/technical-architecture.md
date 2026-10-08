# Architecture effectivement initialisée

## Socle

PHP 8.4, Laravel 13, Inertia 3, Vue 3 Composition API/TypeScript, Vite 8, Bun 1.3.14, Tailwind 4, Reka/shadcn-vue (bouton), Lucide. Versions exactes dans les lockfiles. Le starter officiel Vue a été adapté : suppression de Vite Plus et des parcours de comptes joueurs, migrations automatiques retirées.

Source : https://github.com/laravel/vue-starter-kit (commit d282e817c6c2fa1bd475f7c42ea785ccfc67d0ab), documentation https://laravel.com/docs/13.x/starter-kits et https://inertiajs.com/docs/v3.

TypeScript 7 est disponible via l’alias `@typescript/native`. Vue, `vue-tsc` et ESLint utilisent l’API de `typescript` 6.0.x, selon le principe de [compatibilité officielle](https://devblogs.microsoft.com/typescript/announcing-typescript-7-0/#running-side-by-side-with-typescript-6.0). Le paquet 6 est installé directement : Bun 1.3.14 résout incorrectement la dépendance imbriquée du wrapper `@typescript/typescript6` dans cette configuration. Les contrôles Vue restent exécutés par `vue-tsc` ; le compilateur natif ne le remplace pas. Pour lancer explicitement le compilateur natif : `bun node_modules/@typescript/native/bin/tsc --version`.

L’entrée `/` crée ou retrouve le salon du grand écran ; `/join` sert à la saisie du code sur téléphone. Laravel valide les apparences du catalogue SVG et toutes les mutations. MySQL conserve salons et joueurs ; un verrou transactionnel du salon protège capacité et rôle du chef. Un cookie opaque chiffré HttpOnly par salon permet la reconnexion depuis le même navigateur, indépendamment du prénom. Seul son hash est stocké en base ; aucun identifiant secret n’est publié. Les téléphones signalent leur présence et le grand écran relit le salon toutes les deux secondes. Les noms et scores sont publics pour les détenteurs du code de salon.

BaconQrCode produit les QR SVG localement ; liens de connexion et adresse manuelle utilisent APP_URL. `rooms:prune`, planifié chaque minute, supprime les salons sans joueur connecté depuis trente minutes avec leurs participants. Compose et composer dev exécutent le scheduler ; aucun processus ne lance automatiquement les migrations.

Traductions Laravel FR/EN (langue du navigateur, français par défaut), libellés du catalogue partagés via Inertia et thème clair/sombre/système persistant.

MySQL 8.4 et Redis 7.4 sont configurés en Compose. Sessions/cache/files utilisent Redis. L’image Apache/PHP 8.4 contient les assets et extensions ; cible runtime commune pour les futurs processus. Les migrations restent une commande explicite. La base de tests est distincte.

## Qualité et livraison

Pest, Pest Browser/Playwright Chromium, Bun test, Pint, Larastan niveau 7, ESLint, Prettier, vue-tsc. CI : backend/frontend/navigateur, image runtime, démarrage et healthcheck. Dependabot couvre Composer, Bun et Actions.

Release Please maintient une PR de release après les merges sur `main`, avec un déclenchement manuel de secours. La fusion volontaire de cette PR publie la version GitHub et son changelog. Les contrôles de sa branche sont déclenchés explicitement avec le jeton intégré à GitHub Actions. Le [processus de livraison](quality-ci-cd.md) détaille les protections et les vérifications.

GHCR, Coolify, architecture du serveur, domaines, sauvegardes et production restent à configurer lors du choix d’hébergement. Aucun déploiement n’est inclus dans ce socle.

## Suite métier

Les règles et maquettes existantes restent les sources de vérité. Fortify est actif sous `/admin` pour les comptes portant `is_admin`. Inscription, réinitialisation, profils publics, 2FA et passkeys sont désactivés ; aucune inscription joueur n’est exposée. La création locale passe uniquement par `admin:create`, sans seeder. Reverb/Echo invalident l’état du salon sur canal privé, autorisé seulement au grand écran propriétaire ou aux joueurs connectés. Une relecture HTTP sérialisée toutes les deux secondes reste disponible. Le catalogue conserve les extraits sur le disque privé, avec routes autorisées pour leur écoute. OpenSpout lit la première feuille XLSX sans évaluer les formules ; les imports CSV sont lus en flux. Les lots privés sont liés à un administrateur, revalidés et verrouillés à la confirmation, puis nettoyés par le scheduler. Aucun service mail/analytics/SEO/PWA ajouté par anticipation.

Playwright est épinglé à 1.63.0. Chaque mise à jour doit passer la suite Pest Browser sur Chromium : l’ancien épinglage à 1.60.0 répondait à des attentes indéfinies constatées lors des vérifications initiales.

## Session et barèmes

`Scoring` porte les trois calculs validés (rapidité/ex æquo, dessinateur, votes) et rejette les effectifs impossibles. `SessionController` utilise le verrou du salon et exige le chef connecté pour configurer/prolonger/recommencer. Les entiers sont bornés au type MySQL ; les reprises conservent les identités. Le classement conserve les joueurs partis et des rangs partagés ; le grand écran bascule entre invitation et classement pour préserver la lisibilité. Le moteur verrouille la configuration pendant un mini-jeu et s’arrête après la manche qui atteint le seuil.

## Historique des contenus

`RoomContentCatalog` sélectionne et inscrit les contenus révélés sous verrou du salon. L’historique survit à la suppression du contenu source et aux remises à zéro des scores ; il disparaît à la fermeture du salon. Les contenus inédits restent prioritaires même lorsque le chef autorise des répétitions. Le moteur de quiz consomme ce contrat ; aucun contenu n’est marqué lors d’un simple aperçu des quantités disponibles.

## Moteur de quiz

`GameEngine` orchestre des états JSON sur `Game` sous le verrou transactionnel de `Room` : participants figés, question copiée, réponses privées horodatées par le serveur, gains et échéance. Les réponses indiquent partie/manche et sont refusées si périmées. La clôture engage les scores une fois ; les projections publiques excluent la bonne réponse jusqu’à révélation. Les phases pause/reprise conservent le temps restant et les participants absents attendent la prochaine manche.

`RoomChanged` diffuse uniquement une invalidation vide après commit ; les appareils relisent leur projection autorisée. Une seule requête à la fois sur chaque appareil évite l’écrasement par une réponse plus ancienne. `games:tick`, planifié chaque seconde, assure expiration et pause sans dépendre d’un téléphone. Compose partage le stockage entre web/scheduler/worker/Reverb. L’image PHP inclut `pcntl`, nécessaire au serveur Reverb ; la CI vérifie sa connexion WebSocket réelle.

Les versions verrouillées incluent Reverb 1.12, Echo 2.6 et Pusher JS 8.6. Reverb requiert `guzzlehttp/psr7` 2.x : Composer a résolu 2.13.1 au lieu de 3.1, sans contourner les contraintes des dépendances.
