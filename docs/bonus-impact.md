# Impact des bonus et malus — issue #93

Équilibrage du 14 octobre 2026, validé avant implémentation. Objectif : rendre les farces perceptibles pendant une vraie action, sans modifier le barème, la fréquence des cadeaux, les limites temporelles ni les défenses. Les indices de gêne du tirage restent inchangés : ils décrivent la nature de la gêne et non une durée en secondes.

## Audit du catalogue et compatibilité

Les 19 objets communs sont disponibles dans les sept modes (quiz, blind test, phrases, dessin, mini-jeux, tribunal et bluff). Les objets propres à une interaction ne sont pas distribués dans les autres modes ; le catalogue serveur reste la référence.

| Objets évalués | Modes | Décision et raison |
| --- | --- | --- |
| Éclair | Tous | 3 → 5 s : laisse le temps de rencontrer la gêne pendant la réponse. TV assombrie seulement en dessin, manettes dans tous les modes. |
| Dé farceur | Quiz, blind test, mini-jeux, tribunal, bluff | 3 déplacements / 6 s → 5 / 10 s. Rotation toutes les 2 s, identités des réponses conservées. |
| Squatteur | Quiz, blind test, mini-jeux, tribunal, bluff | 4 → 8 s ; largeur 28 → 38 vw, bornes 80–130 → 140–240 px. Ne capte jamais les clics. |
| Artiste incognito | Blind test | 4 → 8 s : masque les artistes sur la TV et les manettes adverses. |
| Tampon, pot renversé | Dessin | 4 → 10 s. Tampon temporaire ; le rose ne concerne que les nouveaux traits et la couleur choisie revient ensuite. |
| Main maladroite | Dessin | Amplitude multipliée par trois, aperçu et serveur identiques. Gomme et remplissage précis. |
| Accélération | Blind test | ×1,25 / 8 s → ×1,5 / 10 s. Lecture normale à expiration, pause ou fin. |
| Bouclier, savon, boomerang | Tous | Conservés : absorption, nettoyage et renvoi sont déjà décisifs ; pas de chaîne de renvois. |
| Piñata, lave-linge, cadenas, poche mystère | Tous | Conservés : agissent réellement sur les objets distribués, échangés ou disponibles ; augmenter la durée déséquilibrerait l’économie. |
| Bulles, photobomb, gelée, grève | Tous | Conservés : interactions ou occultations déjà visibles. Photobomb 4 s, autres 8–20 s. |
| Fuite de temps, gel du chrono, rallonge, montre invisible, heures supplémentaires, dernier souffle, réveil, temps emprunté | Tous | Conservés : effets déjà déterminants sur le temps ou sa lecture. Pas temporel 6–20 s, cumul et réserve de réponse inchangés. Réveil peut renforcer les nouvelles durées, toujours avec au plus 12 s ajoutées. |
| Accent, éternuement, traducteur pompeux, publicité | Phrases | Conservés jusqu’à la fin de l’écriture : modifient déjà les contributions publiées, sans toucher les brouillons ni révéler leurs auteurs. |
| Mange-mot | Quiz, bluff | Conservé : retire déjà un mot significatif. |
| Alphabet retourné | Quiz, mini-jeux, bluff | Conservé : gêne la lecture sans modifier l’identité ni la zone de réponse. |
| Rideau | Quiz, blind test, mini-jeux, tribunal, bluff | Conservé : impose déjà une ouverture puis une réponse sur la manette ; TV passive. |
| Disque rayé, chanteur | Blind test | Conservés à 4 s : gêne audio déjà forte, éviter de rendre l’extrait inexploitable. |
| Pinceau géant, miroir TV | Dessin | Conservés : pinceau ×3 borné à 36 et miroir déjà structurants. Le miroir ne change pas la géométrie enregistrée. |

Les effets audio sont incompatibles avec les jeux sans extrait ; les effets de pinceau avec les jeux sans toile ; les transformations de phrases avec les jeux sans contributions publiées. Les réponses mélangées n’ont pas de sens dans les phrases ou le dessin. Aucun raccordement anticipé à un nouveau mode.

## Scénarios avant / après

- Quiz de 60 s : lancer un dé à 20 s. Avant, trois ordres successifs puis retour à 26 s. Après, cinq ordres puis retour à 30 s ; une réponse conserve son identifiant à chaque déplacement.
- Vote tribunal à deux choix : alternance pendant 10 s, sans transformer le candidat choisi. Le vote reste définitif ; la fin du vote efface les effets.
- Blind test : accélérer un extrait en cours. Avant ×1,25 pendant 8 s ; après ×1,5 pendant 10 s. Pause puis reprise conservent le temps restant, sans audio accéléré pendant la pause.
- Dessin : lancer le pot après un trait violet. Celui-ci reste violet ; nouveaux traits roses pendant 10 s. Une continuation du même trait et les retries restent cohérents ; le prochain trait retrouve la couleur choisie.
- Défenses : bouclier absorbe l’éclair renforcé ; boomerang le renvoie une fois ; savon dissipe le malus. Un second éclair ne rallonge pas le premier. Pause à 1 s conserve ses 4 s restantes, quel que soit le temps passé en pause.
- TV principale : onde et six icônes à chaque lancer accepté, pendant au plus 1,8 s. Son synthétisé propre à l’objet, avec timbre et variation de hauteur, soumis au bouton son, au volume et à l’atténuation blind test. Aucun nouvel asset audio ni téléchargement.
- Accessibilité : gerbe absente en mouvement réduit, onde statique ; couche sans interaction, aucune extension du viewport. Les snapshots répétés, chargements initiaux et reconnexions ne rejouent ni impacts ni sons.

## Validation

Tests métier : durées et cibles du catalogue, défenses, effets simultanés, pause/reprise, nettoyage en fin de manche et de partie, retries du dessin. Tests Vue : cinq rotations, amplitude bornée, vitesse audio et restauration, signatures sonores et déduplication. Tests navigateur : TV/manettes sans débordement, impact réservé à la TV, absence de capture des clics et mouvement réduit.

Vérifications du 14 octobre 2026 : 537 tests PHP réussis sur `plummo_testing` (10 519 assertions), 44 tests frontend réussis, 130 contrôles viewport et 280 contrôles préparation/règles FR/EN. Pint, PHPStan, ESLint, Prettier, TypeScript et build Vite réussis avec Bun 1.3.14.

L’échec initial des tests a été constaté sur les anciennes durées, la quatrième rotation, l’amplitude et la vitesse ×1,25. Les tests automatiques mesurent ces règles ; la perception du volume et l’équilibrage social restent à confirmer lors d’une partie en groupe sur du matériel réel.
