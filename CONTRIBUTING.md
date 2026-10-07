# Contribuer

Créer une branche depuis `main` à jour (`git pull --ff-only`), utiliser Conventional Commits et proposer une PR. Les contrôles CI et un titre conventionnel doivent réussir avant Squash & Merge.

Chaque fonctionnalité a sa propre PR. `main` exige les contrôles `quality`, `runtime` et `conventional`, ainsi que la résolution des conversations. Aucune approbation personnelle n’est imposée à un auteur seul. Le [processus de livraison](docs/quality-ci-cd.md) décrit les PR de release.

Exécuter les commandes du README avant publication. Une règle métier nécessite un test comportemental ; une modification cosmétique n’exige pas une suite artificielle.

Ne pas publier `.env`, identifiants, extraits audio non autorisés ou données de joueurs. La visibilité publique n’ajoute pas de licence au projet.
