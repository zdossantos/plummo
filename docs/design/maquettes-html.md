# Maquettes HTML — nouvelle direction de jeu

Étude interactive autonome dans `public/maquettes/`. Cette étude reste autonome et ne modifie aucune donnée ; sa direction a été validée le 8 octobre 2026 et intégrée dans l’application Vue. Tous les salons, classements et contenus affichés sont fictifs. L’identité des Plummos et leurs calques SVG existants sont conservés ; seul leur placement est composé dans l’interface.

## Direction demandée

Une DA plus marquée, qui ressemble à un jeu. Les Plummos sont des petits personnages habitant l’interface, libres en bas à droite, sans cases, cartes ou bordures individuelles. Le PC est une scène commune ; le téléphone est une manette. Aubergine, commandes citron/menthe/rose/lilas, relief par ombres pleines, grandes lettres et chrono circulaire. L’administration reprend les mêmes matériaux avec une densité adaptée.

## Consulter

Ouvrir `/maquettes/` avec un serveur HTTP pointant vers `public`. Le sélecteur supérieur passe entre grand écran, manette et atelier. « Écrans » ouvre un catalogue paginé des états. « Texte long » remplace les contenus par des cas de longueur maximale. FR permet de basculer les textes en anglais.

Liens directs : `#tv/quiz`, `#phone/blind`, `#phone/drawing`, `#phone/write`, `#admin/catalog`. Les interactions servent à comparer les compositions : aucune musique réelle n’est diffusée, aucune connexion, publication ou import n’est exécuté.

## Solutions sans scroll

- Hauteur calée sur le viewport visuel, incluant sa réduction par le clavier ; en-tête et espace des personnages réservés.
- Réglages du chef et formulaires d’administration en étapes remplaçant la scène centrale.
- Classements et catalogue paginés, avec compte de pages et commandes persistantes.
- Textes trop longs : résumé et commande de lecture intégrale. La lecture calcule ses pages à partir de la hauteur et de la police réelles, sans supprimer de caractère.
- Huit choix musicaux simultanés, avec titre et artiste. La lecture intégrale est séparée du choix définitif.
- Phrases : huit entrées sur PC haut, pages sur téléphone ou petite hauteur. Chaque phrase conserve une lecture intégrale. Le vote pour sa propre phrase est désactivé.
- Saisie longue composée en portions de 60 caractères (30 en très petite hauteur), conservées dans le brouillon. Chaque portion tient dans le champ sans ascenseur interne.
- Dessin sur canvas avec coordonnées relatives et `touch-action: none` ; annuler un trait et effacer.

## Périmètre de la maquette

Les états représentatifs couvrent l’entrée, l’apparence, les quatre mini-jeux, le chef, l’attente, la pause, les scores, la fin de session et l’administration. Les contrôles de la galerie et de démonstration sont extérieurs au produit.

Les règles Laravel existantes restent la référence de l’intégration future. Les formulaires et imports sont des simulations de mise en page, avec un jeu de données fixe ; ils ne remplacent pas les validations serveur. Le QR réel, les phases automatiques et l’audio seront repris des composants existants lors de l’intégration. L’effacement du canvas de démonstration est immédiat ; le produit conserve sa confirmation. Le catalogue est une réserve synthétique ; son sélecteur de type est uniquement visuel.

## Régénérer

Les textes viennent de `lang/fr/maquettes.php` et `lang/en/maquettes.php`. Le générateur `php scripts/build-html-mockups.php` les assemble avec le catalogue et les calques SVG existants dans `public/maquettes/data.js` ; ne pas modifier ce fichier généré directement. Aucun fichier source de mascotte ni lockfile n’est modifié.

## Police et vérifications

Les titres utilisent Fredoka, hébergée dans `public/maquettes/fonts/`. Source : [Google Fonts, Fredoka](https://github.com/google/fonts/tree/main/ofl/fredoka), licence OFL jointe. Les maquettes ne font aucune requête vers un service de polices.

Serveur de consultation : `python3 -m http.server 8766 --bind 127.0.0.1 --directory public`.

Vérifications autonomes, sans base de données :

```sh
node scripts/check-html-mockups.mjs
node scripts/check-html-mockups-interactions.mjs
```

`MOCKUPS_URL` permet de cibler un autre serveur local. Le premier script mesure les 59 états, textes courts et longs, dans deux langues et sept dimensions (1440×900, 1366×768, 390×844, 375×667, 320×568, 844×390, 390×400). Il vérifie les bornes des commandes et le dépassement du document. Le second vérifie réponse définitive, lecture sans perte de caractères, vote propre interdit, brouillon paginé, modifications répétées, dessin/annulation, chat et focus lors d’une réduction de fenêtre. Une fenêtre réduite simule la hauteur disponible avec le clavier ; cela ne remplace pas un essai sur appareils réels.

La direction a été validée par le porteur du projet. La galerie reste une référence visuelle indépendante de l’application.

Revue indépendante terminée : les six correctifs demandés ont été évalués résolus (`ship` sur la liste de corrections), dont géométrie, conservation des portions de texte, focus et typographie locale. Résultat récent des commandes : 1 652 configurations sans dépassement ni erreur JavaScript ; interactions passées. Les fichiers de preuve sont locaux dans `.impeccable/review/`, exclus de Git.

Le vestiaire (`#phone/identity`) utilise quatre silhouettes en pointillés (tête, visage, cou, main). Chaque emplacement ouvre les accessoires de sa catégorie dans un tiroir latéral sur PC et inférieur sur téléphone. Les choix sont paginés sans scroll. La composition réutilise les couches SVG originales, un accessoire par emplacement et deux accessoires maximum selon le catalogue existant. Un accessoire équipé peut être remplacé ou retiré. « Aléatoire » propose une couleur et deux accessoires de catégories différentes, puis laisse chaque élément modifiable. Échap ferme le tiroir, le focus revient à son emplacement et la navigation clavier reste dans le tiroir pendant son ouverture.

## Intégration Vue

Les salons, quatre mini-jeux et l’administration utilisent maintenant la coque de viewport, des étapes et listes paginées. Les mutations, autorisations, validations, QR et audio restent ceux de Laravel. Les scènes cachées conservent les composants de jeu afin de garder l’audio et les brouillons. Les lecteurs paginent selon la taille et la police mesurées ; les champs longs conservent leur valeur complète entre portions. Les contrôles restent accessibles quand la hauteur visuelle diminue.

Le vestiaire réel utilise les composants officiels [Drawer Shadcn Vue](https://www.shadcn-vue.com/docs/components/drawer.html), basés sur Reka UI, dans `resources/js/components/ui/drawer/`. Les quatre emplacements du catalogue réel sont combinables ; « Aléatoire » choisit une couleur et un accessoire par catégorie disponible. Les assets SVG n’ont pas changé. La galerie historique conserve son ancienne limite de deux accessoires ; le vestiaire Vue suit les quatre emplacements déjà autorisés par Laravel.

Les vérifications autonomes Vue nécessitent un serveur dédié à la base de test, jamais le serveur de développement :

```sh
APP_ENV=testing DB_DATABASE=plummo_testing SESSION_DRIVER=file CACHE_STORE=array QUEUE_CONNECTION=sync BROADCAST_CONNECTION=null php artisan serve --no-reload --host=127.0.0.1 --port=8781
bun scripts/check-vue-viewport.mjs
bun scripts/check-vue-admin-viewport.mjs
PLUMMO_UI_KEYBOARD=1 bun scripts/check-vue-viewport.mjs
```

Migrer explicitement `plummo_testing` selon README avant lancement. `--no-reload` conserve les variables de test transmises au serveur PHP. `PLUMMO_UI_URL` permet une autre adresse dédiée. Le contrôle admin crée trente contenus, packs et tags longs ainsi qu’un compte temporaire, puis les supprime dans son bloc `finally` ; son helper PHP refuse toute base autre que `plummo_testing`. Le contrôle joueur vérifie d’abord que le serveur accepte un compte éphémère présent exclusivement dans la base de test, puis supprime ses salons et ce compte dans son bloc `finally`. Les codes restent consignés dans `/tmp/plummo-ui-rooms` pour diagnostic. La matrice couvre FR/EN, PC, téléphone et paysage. La hauteur réduite simule le clavier ; un essai sur appareils réels reste nécessaire.

Vérifications récentes de l’intégration : 120 contrôles de géométrie FR sur cinq formats, 144 contrôles admin FR/EN sur six formats et 96 contrôles avec hauteur réduite sur deux formats, dans les deux langues. Une régression dédiée vérifie aussi la lecture complète de textes Unicode avec retours à la ligne, sur 320×400 et 1366×768. Les contrôles TypeScript, PHPStan, formatage, les tests unitaires frontend et les builds Vite/Docker passent.

Suite complète Laravel/Pest : 157 tests réussis, 1 677 assertions (120,69 s). Tests frontend : 8 réussis, 34 assertions.
