# Vérification approfondie des bonus

## Scénarios automatisés

- Les 11 couples jeu/objet : trois bonus de quiz, quatre de blind test, deux de phrases et deux de dessin. Attribution compatible, cibles collectives, durée et confidentialité de la poche.
- Attribution initiale, option désactivée, solo, rattrapage réservé aux joueurs derrière le meilleur score et absence d’attribution aux ex æquo.
- Poche pleine : proposition privée, remplacement d’un objet précis, refus, promotion après un lancement, conservation entre manches sans écrasement, nettoyage en fin de partie.
- Requêtes périmées, objets volés ou incompatibles, corps invalides, joueurs en attente ou exclus, absence d’adversaire admissible. Aucun objet consommé en cas de refus.
- Un lancement par manche, deux requêtes envoyées simultanément, durée non prolongée par un autre lancement, pause/reprise et refus pendant la révélation ou la reprise.
- Phrases : transformation après validation, cumul accent/éternuement, brouillon original conservé et auteurs anonymes au vote.
- Dessin : traits existants préservés, changement de couleur pendant un trait tenu, découpage à l’activation et à l’expiration, retries de segments.
- Téléphone/TV : overlays traversables, sélection correcte malgré le mélange, artistes cachés uniquement chez les adversaires puis restaurés, huit réponses visibles sans scroll, grand écran passif.
- Réceptions/sons : déduplication, lots d’événements, reconnexion silencieuse, nouvelle partie, signatures courtes distinctes et lecture Web Audio dans le navigateur.
- Drawer de remplacement : tous les boutons visibles jusqu’à 390 × 360.

## Exécution

Les tests PHP et navigateur utilisent uniquement `plummo_testing`. Les tests navigateur automatisés utilisent Chromium ; cette campagne ne remplace pas un essai matériel Safari/iPhone ni une écoute sur la TV réelle.

Commandes : `php artisan test`, `bun run test:unit`, `composer lint:check`, `composer analyse`.

Résultats du 9 octobre 2026 : 220 tests PHP/navigateur passent (2 525 assertions, 180,84 s), 24 tests frontend passent (349 assertions). Pint, PHPStan et TypeScript passent également.

## Couverture VFX et SFX

`bun run test:bonuses` vérifie chaque scène réelle et chaque MP3 local, les crédits, les réactions défensives et le mouvement réduit dans Chromium. `bun run test:unit` vérifie que tous les objets du catalogue serveur ont une scène explicite. Ces deux commandes sont exécutées en CI. Voir le [contrat de livraison pour chaque bonus/malus](bonus-presentation.md).

Validation du 14 octobre 2026 pour ces contrôles : 46 tests frontend réussis, 38 MP3 décodés et crédités, 164 rendus Vue normaux/statiques et réactions défensives réussis. Un MP3 rendu volontairement illisible et une scène volontairement masquée font échouer le contrôle ; les fichiers originaux sont restaurés et le contrôle repasse. ESLint, Prettier, TypeScript et build Vite réussis avec Bun 1.3.14.
