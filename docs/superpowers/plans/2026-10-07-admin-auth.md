# Accès administrateur — plan d'implémentation

**Objectif :** protéger l'administration sans ajouter de comptes joueurs ou d'inscription publique.
**Spécification :** docs/metier-plummo.md section 6, docs/technical-architecture.md, socle personnel Fortify.
**Architecture :** Fortify sous /admin, guard web existant. Colonne users.is_admin false par défaut ; seuls les administrateurs peuvent se connecter et accéder au tableau de bord. Création d'un compte par commande locale interactive, mot de passe masqué et sans argument CLI.

## Tâche 1 — Authentification et autorisation

- [x] Écrire tests HTTP : invité redirigé vers /admin/login, utilisateur ordinaire refusé, admin autorisé, mauvaise connexion refusée, connexion/déconnexion admin, aucune inscription. Constater RED.
- [x] Installer Fortify compatible Laravel 13 (sources officielles vérifiées), désactiver inscription/reset/2FA non requis, créer provider, Gate administer et routes protégées. Ne créer aucun compte/secrets par défaut.
- [x] Ajouter migration users.is_admin et commande admin:create avec saisie interactive de nom/email/password, confirmation d'au moins 12 caractères et détection du doublon sans écrasement. Tests console sans afficher de secret.
- [x] Migrer explicitement plummo_testing ; GREEN des tests.

## Tâche 2 — Vue et livraison

- [x] Pages admin/Login.vue et admin/Dashboard.vue, traduction FR/EN dans lang/admin et messages auth, thème partagé, erreurs et traitement en cours, déconnexion. Tableau de bord décrit seulement les fonctionnalités disponibles ; le catalogue viendra dans la PR suivante.
- [x] Test navigateur de connexion et déconnexion ; contrôles README, Docker, revue indépendante.
- [x] PR #16 fusionnée après succès des quatre contrôles GitHub sur 7573fb3.

## Interfaces

Les futures routes de catalogue réutilisent middleware auth + can:administer et la navigation admin. Le statut is_admin ne figure dans aucune validation de profil public. La commande de création ne se lance ni au démarrage ni dans un seeder. Aucune connexion admin n'est nécessaire pour jouer.

## Vérification effectuée

RED HTTP : routes absentes puis colonne is_admin absente. RED console : commande absente. GREEN : cinq tests, 41 assertions. Navigateur connexion/déconnexion : un test, quatre assertions. Revue indépendante : une route de confirmation Fortify non configurée retirée ; régression RED puis GREEN des trois routes supplémentaires. Contrôles complets composer ci:check : 51 tests, 383 assertions ; Pint, PHPStan, ESLint, Prettier, types, quatre tests Bun et build réussis. Image runtime Docker construite avec succès. Aucun compte créé hors tests, aucun mot de passe enregistré dans la documentation.
