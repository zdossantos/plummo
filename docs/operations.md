# Production Plummo sur Coolify

## Architecture préparée

Adresse publique : **https://plummo.zdossantos.fr**. Temps réel :
**https://ws.plummo.zdossantos.fr** (WebSocket sécurisé, port 443).
Instance : **https://coolify.zdossantos.fr**.

Le modèle suit DLP Friends : GitHub publie une image GHCR lors d’une release,
Coolify exécute `web`, `worker`, `scheduler` et `reverb` avec cette même image
immuable. Plummo garde Apache/PHP 8.4, Bun 1.3.14 et son stockage audio local privé.
Les réglages publics Reverb sont partagés à l’exécution par Inertia : aucune
variable `VITE_*` ni secret de production n’est requis pour construire l’image.

**Plummo possède ses propres ressources MySQL et Redis**, comptes, secrets et
volumes. Ne reprendre aucun UUID, mot de passe, volume ni base DLP Friends.
Le Compose local reste destiné au développement.

Les fichiers versionnés préparent ce déploiement. Ils ne prouvent pas que les
ressources existent, que le DNS pointe vers le serveur ou que le site est en ligne.

## État de la préparation Coolify

Le 9 octobre 2026, le projet `Plummo` (`atrotpnzj8uy2eq3huap0cft`) et son
environnement `production` (`xhndh1mecwbyivahen9q3zda`) ont été créés.

- MySQL : `0viwhujhwbbhoix47tsbjh7p`, image `mysql:8.4`, base `plummo`,
  compte non-root `mysql`, accès privé, sans port publié.
- Redis : `vs8yprbqbb9jnlm58krognip`, image `redis:7.4`, accès privé,
  `appendonly yes`, `appendfsync everysec`, `maxmemory-policy noeviction`.

- Application Compose : `jrbx79ocp092zjk5jgc6e7xq`, nom **Plummo**,
  fichier `/compose.production.yaml`, source `main`, déploiements manuels.
- Réseau prédéfini commun activé avec l’accord de l’utilisateur ; les bases,
  credentials et volumes restent indépendants de DLP Friends.
- Domaines HTTPS : `plummo.zdossantos.fr` sur `web:80` et
  `ws.plummo.zdossantos.fr` sur `reverb:8080`.
- Les alias automatiques `www.plummo.zdossantos.fr` et
  `www.ws.plummo.zdossantos.fr` ont été supprimés avec l’accord de l’utilisateur.

Ces ressources sont configurées mais **arrêtées**. Le Compose a été chargé depuis
la branche de la PR #27 puis la source rétablie sur `main` : fusionner cette PR
avant tout rechargement du Compose ou déploiement. Les secrets de production,
`APP_IMAGE` (digest publié) et le secret GitHub `COOLIFY_TOKEN` restent à renseigner.
Les variables GitHub `COOLIFY_API_URL` et `COOLIFY_APPLICATION_UUID` sont configurées.
Les deux domaines principaux résolvent vers `82.165.104.174` ; vérifier la
concordance avec le serveur retenu avant la mise en ligne. Aucun déploiement
ni migration de production n’a été lancé.

## Créer les ressources dédiées

Dans un projet Coolify **Plummo**, environnement **production**, sélectionner le
serveur de déploiement et créer deux ressources indépendantes de l’application :

| Ressource | Configuration |
| --- | --- |
| Plummo MySQL | MySQL 8.4 ; base `plummo` ; compte non-root `mysql` propre à cette ressource ; volume propre `/var/lib/mysql` |
| Plummo Redis | Redis 7.4 ; mot de passe propre ; persistance AOF ; volume propre `/data` ; politique `noeviction` |

Conserver les identifiants administrateur dans Coolify. L’application utilise
uniquement le compte MySQL non-root. Ne publier **aucun port** MySQL ou Redis
sur l’hôte. Utiliser leurs hôtes internes uniques indiqués par Coolify, jamais
les noms génériques `mysql` ou `redis` d’une autre application.

Créer une application Docker Compose depuis `zdossantos/plummo`, branche `main`,
fichier `/compose.production.yaml`. Désactiver **Auto Deploy** : les merges de
fonctionnalités ne doivent pas déclencher de livraison. Activer **Connect to
Predefined Network** afin de joindre les ressources dédiées sur la destination
Coolify. Les credentials restent distincts même si le réseau du serveur est commun.

Le workflow construit `linux/amd64`, comme DLP Friends. Vérifier que le serveur
retenu est x86_64 avant d’activer le déploiement ; adapter la plateforme si nécessaire.

## Domaines et variables

Faire pointer les enregistrements DNS `plummo.zdossantos.fr` et
`ws.plummo.zdossantos.fr` vers le serveur Coolify. Affecter les domaines :

- `web`, port interne **80** : `https://plummo.zdossantos.fr` ;
- `reverb`, port interne **8080** : `https://ws.plummo.zdossantos.fr`.

Coolify termine TLS et transmet les connexions WebSocket. Le Compose ne publie
aucun port hôte. `TRUSTED_PROXIES=*` suppose que le web n’est accessible que par
ce proxy ; Laravel accepte le protocole, le port et l’IP transférés, sans faire
confiance à `X-Forwarded-Host`.

Renseigner les variables suivantes dans l’application Coolify, disponibles
pendant la préparation du Compose et à l’exécution. Stocker les secrets directement
dans Coolify, sans les copier dans le dépôt ou les messages.

| Variable | Valeur |
| --- | --- |
| `APP_IMAGE` | `ghcr.io/zdossantos/plummo@sha256:…` tiré du `container-image.json` de la release vérifiée |
| `APP_KEY` | Nouvelle clé Laravel, unique et conservée entre déploiements |
| `DB_HOST` | Hôte interne de **Plummo MySQL** |
| `DB_USERNAME` | `mysql`, compte non-root de la ressource créée |
| `DB_PASSWORD` | Mot de passe du compte non-root de **Plummo MySQL** |
| `REDIS_HOST` | Hôte interne de **Plummo Redis** |
| `REDIS_USERNAME` | Utilisateur Redis indiqué par Coolify (`default` par défaut) |
| `REDIS_PASSWORD` | Mot de passe de **Plummo Redis** |
| `REVERB_APP_KEY` | Clé publique propre à Plummo |
| `REVERB_APP_SECRET` | Secret serveur propre à Plummo |
| `REVERB_PUBLIC_HOST` | `ws.plummo.zdossantos.fr` par défaut ; adapter au domaine affecté à Reverb |

`APP_URL`, cookies HTTPS chiffrés, noms de base/utilisateur, préfixes Redis et
origines autorisées sont définis dans le Compose. Si le sous-domaine WebSocket
change, modifier sa variable et son DNS ; l’origine autorisée reste celle du site.

Le volume `app-storage` est partagé entre les quatre services pour les extraits,
imports et copies de jeu privées. Les caches de vues, logs et fichiers temporaires
de l’image ne sont pas conservés entre releases. Le build exclut les uploads et
données locales : aucun catalogue de développement n’est publié.

## GitHub : image de release et livraison

Dans les variables Actions du dépôt **Plummo**, renseigner :

- `COOLIFY_API_URL=https://coolify.zdossantos.fr/api/v1` ;
- `COOLIFY_APPLICATION_UUID` : UUID de l’application **Plummo**.

Ajouter le secret Actions `COOLIFY_TOKEN`, autorisé à mettre à jour les variables
et le commit de cette application puis à lancer son déploiement. Les secrets
MySQL, Redis et Laravel restent dans Coolify. Si GHCR exige une authentification,
connecter Docker sur le serveur avec un jeton limité à `read:packages`, saisi via
`--password-stdin`, sans le journaliser.

Après fusion volontaire de la PR Release Please :

1. Le workflow vérifie la concordance tag, version et commit exact.
2. Il publie l’image de ce commit sur GHCR puis la teste **par digest** : `/up`,
   page `/join`, assets compilés et absence de `.env`, `.git` et `node_modules`.
3. Il conserve image, commit et plateforme dans `container-image.json` attaché
   à la release.
4. Si l’UUID est configuré, il actualise uniquement `APP_IMAGE`, épingle le commit
   du Compose puis demande le déploiement Coolify. Sinon il indique explicitement
   que l’image est prête et que la configuration Coolify reste à terminer.

Une réponse API confirme l’acceptation de la demande, **pas la santé du site**.
Vérifier le résultat dans Coolify et les parcours ci-dessous. Les mutations API
ne sont pas relancées automatiquement après un timeout : vérifier d’abord si une
livraison a déjà été acceptée.

## Premier démarrage et vérification

Sur la base de production vide, exécuter explicitement dans `web` :

```sh
php artisan migrate --force
php artisan admin:create
```

La création administrateur est interactive avec mot de passe masqué. Aucun
seed automatique ni migration dans un entrypoint. Pour une nouvelle release,
examiner les migrations et sauvegarder avant leur application explicite.

Vérifier dans chacun des quatre conteneurs une connexion Laravel à la base
`plummo` et un `PING` Redis authentifié. Les healthchecks prouvent le démarrage
HTTP ou du processus ; ils ne suffisent pas à valider MySQL/Redis.

Vérifier ensuite : certificat HTTPS, `/up`, QR vers le domaine public, entrée
avec deux téléphones, session conservée après actualisation, WebSocket connecté,
quiz et pause/reprise de cinq secondes, bulles, lecture audio et import administrateur.
Confirmer que le scheduler avance les manches sans requête et que le worker
traite la file. Redéployer et vérifier la conservation des fichiers privés et données.

## Sauvegardes et retour arrière

Configurer une sauvegarde quotidienne de **Plummo MySQL** avec rétention 30 jours
et une copie protégée hors serveur. Sauvegarder aussi le volume `app-storage` et
conserver la clé Laravel dans le gestionnaire de secrets. Redis conserve ses
sessions et files avec AOF ; il ne remplace pas la sauvegarde MySQL/fichiers.
Valider une restauration isolée avant de considérer ces sauvegardes opérationnelles.

Pour revenir à une version, reprendre son couple digest/commit depuis son
`container-image.json`, conserver les mêmes ressources et volumes puis demander
le redéploiement. Vérifier d’abord la compatibilité avec le schéma courant.
Aucun `migrate:rollback`, effacement de volume ou changement de base automatique.

## Références

- [Flux GitHub Actions / Coolify](https://coolify.io/docs/applications/sources/github/actions)
- [Mise à jour des variables par API](https://coolify.io/docs/api/endpoints/applications/update-envs-by-application-uuid)
- [Proxy de confiance Laravel](https://laravel.com/framework/docs/13.x/requests#configuring-trusted-proxies)
