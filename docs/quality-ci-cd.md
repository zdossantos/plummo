# Qualité et livraison

## PR de travail

Chaque fonctionnalité, correction ou évolution du socle passe par une branche dédiée et une PR vers `main`. Les commits et le titre suivent Conventional Commits. Les commandes locales de vérification sont dans le [README](../README.md).

La protection de `main` exige une PR, une branche à jour, la résolution des conversations et les contrôles `quality`, `runtime` et `conventional`. Elle s’applique aussi aux administrateurs, bloque les suppressions et les push forcés et exige un historique linéaire. Le dépôt autorise uniquement Squash & Merge ; aucune approbation n’est requise pour un auteur seul.

## Versionnement

Après un merge sur `main`, Release Please crée ou actualise une PR de release à partir des Conventional Commits. Cette PR maintient la version SemVer dans `.release-please-manifest.json` et `version.txt`, ainsi que `CHANGELOG.md`. La valeur initiale est `0.1.0` ; le changelog est généré par la première PR de release.

La release GitHub et le tag sont publiés uniquement après la fusion volontaire de la PR de release, avec ses contrôles réussis. Ne pas créer manuellement de tag ou de release. Le workflow `Release proposal` peut également être relancé sur `main` depuis GitHub Actions.

Release Please utilise `GITHUB_TOKEN`, sans secret supplémentaire. Le réglage du dépôt autorise GitHub Actions à créer les PR ; les permissions générales restent en lecture. Le workflow de release accorde seulement les écritures nécessaires aux versions, labels, PR et déclenchements de contrôles.

Les PR créées avec ce jeton ne déclenchent pas les workflows `pull_request`. Le workflow de release lance donc explicitement `CI` et `PR title` sur la branche de chaque PR créée ou actualisée. Le contrôle du titre vérifie également que le commit contrôlé correspond à cette PR ouverte vers `main`.

## Production à configurer

Une fusion de fonctionnalité ne déploie pas l’application. La publication GitHub d’une version ne déploie pas non plus le site à ce stade.

Après le choix de l’hébergement et de l’architecture du serveur, compléter le flux prévu par le socle : build du commit exact de release, publication GHCR, test de l’image par digest, conservation de sa référence et déploiement Coolify. Les migrations resteront explicites et l’Auto Deploy indépendant de Coolify devra être désactivé. Aucun secret de production ne doit être versionné.
