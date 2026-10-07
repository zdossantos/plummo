# Travail sur Plummo

Lire PRODUCT.md et docs/metier-plummo.md avant de développer le métier. Le socle personnel est résumé dans docs/technical-architecture.md.

- Laravel porte validation, autorisations et règles ; Vue présente les interactions.
- Grand écran first, adaptation téléphone selon les parcours validés. Aucun service worker, manifeste PWA ni Web Push.
- Réutiliser avant d’ajouter ; ne pas implémenter des fonctionnalités anticipées.
- Textes visibles dans lang/fr et lang/en, partagés via Inertia.
- PHP 8.4, Bun 1.3.14 ; conserver composer.lock et bun.lock.
- Tests métier d’abord, vérifier leur échec pertinent puis l’implémentation.
- Tester uniquement sur plummo_testing ; migrations explicites, jamais dans un entrypoint.
- Aucun secret versionné ; variables VITE_* publiques.
- Branches chore/feature/fix/docs, Conventional Commits, PR et Squash & Merge ; pas de push direct sur main.
- Commandes de validation dans README.md. Ne pas annoncer une vérification sans sortie récente.
