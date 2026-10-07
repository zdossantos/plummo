# Catalogue métier — plan d'implémentation

> Exécution avec superpowers:executing-plans, une PR dédiée puis revue et fusion après CI.

**Objectif :** fournir la gestion administrateur des quatre types de contenu, tags et packs dynamiques, et les sélections serveur réutilisables par les jeux.
**Spécification :** docs/metier-plummo.md section 6 ; docs/parcours-et-ecrans-plummo.md section administration.
**Architecture :** tables contents (type, payload JSON, published), tags et packs avec pivots ; tous les tags d'un pack requis. Stockage privé des extraits préparés, accès audio par futur moteur. Aucun contenu de démonstration ajouté à la base. Administration protégée par auth + can:administer.

## Contraintes

FR/EN ; Laravel valide et autorise. Pas de difficulté des mots. Quatre réponses quiz distinctes avec index correct ; titre/artiste et extrait requis pour musique. Brouillons acceptent les champs incomplets ; publication valide le contenu. Migration explicite plummo_testing. Tests RED/GREEN puis contrôles README/revue/PR/CI/Squash.

## Interfaces et fichiers

- app/Enums/ContentType.php : quiz, blind_test, drawing, phrase.
- app/Models/Content.php, Tag.php, Pack.php ; migration tables/pivots. Payload contient question/choices/correct, title/artist/audio_path, word ou prompt selon type. Audio_path jamais accepté du client.
- app/Services/ContentCatalog.php : query(type, packIds) union de packs AND tags, seulement publiés, dédoublonnage ; disponibles publiés et effectifs par pack. Pas d'historique marqué lors de la simple consultation.
- app/Http/Requests/Admin/ContentRequest.php : règles communes et publication selon type ; choix vides/identiques rejetés ; fichier préparé MP3/WAV/OGG/M4A au plus 20 Mio, limites documentées comme choix techniques V1 ; mots 100 chars, questions 500, propositions 200, prompts 240, titres/artistes 200.
- app/Http/Controllers/Admin/{Content,Tag,Pack}Controller.php : formulaires protégés, pagination/recherche/statut/type, CRUD ; aucun remplacement silencieux.
- resources/js/pages/admin/{Contents,ContentForm,Tags,Packs}.vue, composant AdminLayout réutilisé ; traduction lang/admin ; navigation Dashboard.
- tests/Feature/Admin/CatalogTest.php : permissions CRUD, validation publication, brouillon, audio privé, tags packs AND et union dédoublonnée. tests/Browser/AdminCatalogTest.php : création mot/tags/pack et publication.

## Tâche 1 — Données, règles et routes

- [x] Tests refus invité/non-admin, création/édition brouillon vs publication, faux audio_path, IDs de tags inexistants, pack sans tags, tags supprimés référencés protégés. Exécuter RED pertinent.
- [x] Migration et modèles enum/casts/pivots, query catalogue ; validation et controllers. Tests dédiés intersection/union et isolation type/publié.
- [x] Audio stocké local privé sous nom généré ; média écoutable admin route protégée ; suppression ancienne pièce après remplacement, éviter suppression si transaction échoue. Nettoyage contenu supprimé.
- [x] Migrations explicites testing et GREEN.

## Tâche 2 — Administration utilisable et livraison

- [x] Listes/formulaires avec erreurs, étiquettes, aperçu des champs, publication explicite, gestion tags/packs et nombres disponibles par type. Confirmer suppression d'un contenu ; interdire suppression tag encore requis par pack pour ne pas élargir silencieusement le pack.
- [x] Test navigateur ; format/lint/types/analyse/build/tests et Docker. Revue indépendante ; corriger les findings.
- [x] Docs disponibles/limites, PR dédiée attachée, contrôles dernier commit puis fusion.

## Revue ciblée

- Audio path fabriqué, fichier PHP déguisé audio : rejet avant stockage.
- Suppression tag encore requis par pack : conserver définition intacte.
- Publication après édition d'un brouillon incomplet : erreurs par champ, rien publié.
- Contenu portant des tags supplémentaires : appartient au pack AND ; autres jeux et brouillons exclus.
- Packs recouvrants : contenu unique, choix serveur de un à trois packs ; pack sans contenu non proposé aux joueurs.

L'historique persistant par salon et les distracteurs musicaux seront ajoutés dans le moteur / blind test : ils dépendent des révélations, pas de l'administration. Le moteur devra sauvegarder un snapshot du contenu engagé pour qu'une édition admin ne change pas une manche en cours.

Vérification locale : composer ci:check réussi, 61 tests PHP/navigateur et 473 assertions ; quatre tests Bun réussis. Revue : sélection vide, édition audio concurrente et échec de stockage corrigés avec régressions RED/GREEN.
