#!/usr/bin/env python3
"""Mix licensed library recordings listed in public/audio/sfx/scenes/sources.json.

Usage: python3 scripts/build-bonus-sfx.py --sources /path/to/extracted/packs
See the scene README for source folder names. Requires FFmpeg on PATH.
"""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile


def build(source_root: Path, ffmpeg: str) -> None:
    output = Path(__file__).resolve().parents[1] / 'public/audio/sfx/scenes'
    manifest = json.loads((output / 'sources.json').read_text())
    from array import array
    import math

    for filename, scene in manifest.items():
        mixed = array('f', [0.0]) * round(48000 * scene['mix']['duration'])
        for layer in scene['sources']:
            source = source_root / layer['path']
            if hashlib.sha256(source.read_bytes()).hexdigest() != layer['sha256']:
                raise ValueError(f'Source checksum mismatch: {source}')
            duration = layer['length']
            chain = [
                f"atrim=start={layer['start']}", 'asetpts=PTS-STARTPTS',
                'silenceremove=start_periods=1:start_duration=0.001:start_threshold=-55dB',
                'aresample=48000', f"asetrate={48000 * layer['pitch']}", 'aresample=48000',
                f'atrim=duration={duration}', 'asetpts=PTS-STARTPTS',
            ]
            if layer['reverse']:
                chain.append('areverse')
            chain.extend(['loudnorm=I=-21:TP=-4:LRA=7', 'aresample=48000',
                          'afade=t=in:d=0.008',
                          f'afade=t=out:st={max(0, duration - .08)}:d=0.08'])
            decoded = subprocess.run(
                [ffmpeg, '-loglevel', 'error', '-i', str(source), '-af', ','.join(chain),
                 '-t', str(duration), '-ac', '1', '-ar', '48000', '-f', 'f32le', '-'],
                capture_output=True, check=True, timeout=30)
            samples = array('f')
            samples.frombytes(decoded.stdout)
            if not samples or not all(math.isfinite(value) for value in samples):
                raise ValueError(f'Invalid decoded source: {source}')
            offset = round(layer['delay'] * 48000)
            count = min(len(samples), max(0, len(mixed) - offset))
            for index, value in enumerate(samples[:count]):
                fade = min(1.0, index / 384, (count - 1 - index) / 3840)
                mixed[offset + index] += value * layer['gain'] * fade
        if max(abs(value) for value in mixed) < .001:
            raise ValueError(f'Silent scene: {filename}')
        with tempfile.TemporaryDirectory() as temporary:
            target = Path(temporary) / filename
            subprocess.run(
                [ffmpeg, '-y', '-loglevel', 'error', '-f', 'f32le', '-ar', '48000',
                 '-ac', '1', '-i', '-', '-af', 'loudnorm=I=-18:TP=-3:LRA=7',
                 '-t', '2.8', '-ac', '2', '-ar', '48000', '-c:a', 'libmp3lame',
                 '-b:a', '160k', str(target)], input=mixed.tobytes(), check=True, timeout=30)
            (output / filename).write_bytes(target.read_bytes())
        print(filename, flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--sources', required=True, type=Path)
    parser.add_argument('--ffmpeg', default='ffmpeg')
    options = parser.parse_args()
    build(options.sources, options.ffmpeg)
