# Qualité et livraison

## PR de travail

Chaque fonctionnalité, correction ou évolution du socle passe par une branche dédiée et une PR vers `main`. Les commits et le titre suivent Conventional Commits. Les commandes locales de vérification sont dans le [README](../README.md).

La protection de `main` exige une PR, une branche à jour, la résolution des conversations et les contrôles `quality`, `runtime` et `conventional`. Elle s’applique aussi aux administrateurs, bloque les suppressions et les push forcés et exige un historique linéaire. Le dépôt autorise uniquement Squash & Merge ; aucune approbation n’est requise pour un auteur seul.

## Versionnement

Après un merge sur `main`, Release Please crée ou actualise une PR de release à partir des Conventional Commits. Cette PR maintient la version SemVer dans `.release-please-manifest.json` et `version.txt`, ainsi que `CHANGELOG.md`. La valeur initiale est `0.1.0` ; le changelog est généré par la première PR de release.

La release GitHub et le tag sont publiés uniquement après la fusion volontaire de la PR de release, avec ses contrôles réussis. Ne pas créer manuellement de tag ou de release. Le workflow `Release proposal` peut également être relancé sur `main` depuis GitHub Actions.

Release Please utilise `GITHUB_TOKEN`, sans secret supplémentaire. Le réglage du dépôt autorise GitHub Actions à créer les PR ; les permissions générales restent en lecture. Le workflow de release accorde seulement les écritures nécessaires aux versions, labels, PR et déclenchements de contrôles.

Les PR créées avec ce jeton ne déclenchent pas les workflows `pull_request`. Le workflow de release lance donc explicitement `CI` et `PR title` sur la branche de chaque PR créée ou actualisée. Le contrôle du titre vérifie également que le commit contrôlé correspond à cette PR ouverte vers `main`.

## Production Coolify

Une fusion de fonctionnalité ne déploie pas l’application. Après fusion volontaire
de la PR de release, le workflow construit le commit exact pour `linux/amd64`,
publie `ghcr.io/zdossantos/plummo`, teste l’image par digest et attache
`container-image.json` à la release. Tag, version et SHA doivent correspondre.

La livraison Coolify est activée uniquement lorsque `COOLIFY_APPLICATION_UUID`
est renseigné. Elle met à jour le digest et le commit du Compose avant de demander
le déploiement ; un échec arrête la séquence sans retry automatique. Le jeton
reste dans les secrets GitHub, les secrets runtime dans Coolify.

Les [instructions de production](operations.md) décrivent les quatre services,
MySQL et Redis dédiés à Plummo, les domaines HTTPS, les variables et les vérifications.
Les migrations restent explicites et l’Auto Deploy indépendant de Coolify est
désactivé. La préparation versionnée ne signifie pas que le site est déployé.
