# Bruitages des animations Plummo

38 montages de bruitages de bibliothèque, servis localement. Aucun son synthétisé à l’exécution.
Les fichiers sources sont sous CC0 1.0 ou CC-BY 3.0. Les extraits CC-BY restent sous cette licence ; les crédits ci-dessous accompagnent également les vidéos distribuées.

## Crédits

- [100 CC0 SFX #2](https://opengameart.org/content/100-cc0-sfx-2) — rubberduck — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [Multiple Dice Rolling](https://opengameart.org/content/multiple-dice-rolling) — Justin Nichol — [CC-BY-3.0](https://creativecommons.org/licenses/by/3.0/).
- [Swishes Sound Pack](https://opengameart.org/content/swishes-sound-pack) — artisticdude — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [80 CC0 creature SFX](https://opengameart.org/content/80-cc0-creature-sfx) — rubberduck — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [100 CC0 SFX](https://opengameart.org/content/100-cc0-sfx) — rubberduck — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [202 More Sound Effects](https://opengameart.org/content/202-more-sound-effects) — OwlishMedia — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [Genuine Sneeze Sound](https://opengameart.org/content/genuine-sneeze-sound) — qubodup — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [40 CC0 water, splash & slime SFX](https://opengameart.org/content/40-cc0-water-splash-slime-sfx) — rubberduck — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [Shimmer Glitter Magic](https://opengameart.org/content/shimmer-glitter-magic) — The Berklee College of Music; submitted by qubodup — [CC-BY-3.0](https://creativecommons.org/licenses/by/3.0/).
- [Clock Tick](https://opengameart.org/content/clock-tick-0) — IgnasD — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [Scratch Speed](https://opengameart.org/content/scratch-speed) — Raccoonanimator — [CC-BY-3.0](https://creativecommons.org/licenses/by/3.0/).
- [feminine voice singing improv melody](https://freesound.org/people/buggly/sounds/700247/) — buggly — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).
- [Pencil Sounds](https://opengameart.org/content/pencil-sounds) — NachtmahrTV (writing), damsur (erasing); packaged by AntumDeluge — [CC0-1.0](https://creativecommons.org/publicdomain/zero/1.0/).

OpenGameArt : https://opengameart.org/ ; Freesound : https://freesound.org/.

Modifications par Plummo : sélection d’extraits, coupe des silences initiaux, superposition et placement temporel, changements de hauteur/vitesse et lecture inversée pour certains effets, fondus, normalisation et conversion MP3. Les paramètres exacts et SHA-256 des enregistrements originaux figurent dans `sources.json`.

## Reconstruction

Télécharger les sources depuis les pages indiquées dans `sources.json`. Extraire les archives dans un dossier portant le slug OpenGameArt (`100-cc0-sfx`, `pencil-sounds`, etc.). Les fichiers seuls portent le slug et leur extension (`genuine-sneeze-sound.flac`, `clock-tick-0.ogg`, `multiple-dice-rolling.ogg`, `shimmer-glitter-magic.flac`, `scratch-speed.wav`). Le chant utilise le MP3 de préécoute haute qualité `https://cdn.freesound.org/previews/700/700247_9088242-hq.mp3`, enregistré en `singer.mp3`.

Puis :

```sh
python3 scripts/build-bonus-sfx.py --sources /chemin/vers/les/sources
```

FFmpeg doit être disponible sur PATH (ou utiliser `--ffmpeg /chemin/ffmpeg`). Le script vérifie les empreintes avant toute conversion. Sortie : stéréo 48 kHz, MP3 160 kbit/s, durée 2,8 s, volume cible −18 LUFS et crête cible −3 dB. Les silences entre gestes sont intentionnels.
