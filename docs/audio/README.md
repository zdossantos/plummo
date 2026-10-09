# Musique d’ambiance Plummo

Composition originale approuvée : marimba, basse ronde, accords doux et petites percussions, 104 BPM, 64 mesures, 147,692313 secondes.

Le fichier `public/audio/plummo-ambiance.mp3` remplace le thème court. Le niveau des passages est égalisé avec une enveloppe circulaire, sans fondu d’introduction ou de fin. Les résonances de fin sont repliées au début lors du rendu. La lecture utilise un AudioBufferSourceNode en boucle ; couper le son ne recommence pas la piste. Pendant le blind test, la réduction de volume existante reste active.

Contrôle Chromium à 44 100 Hz : 6 513 231 échantillons décodés, écart de niveau moyen entre les huit sections inférieur à 0,4 dB, saut au raccord de 0,000031. La variation à l’intérieur des phrases reste naturelle. Le MP3 contient les informations de lecture sans délai d’encodage.

`level-preview.py` documente le traitement appliqué au WAV original ; il requiert NumPy et utilise des chemins temporaires. La source de composition est conservée à côté de ce document.
