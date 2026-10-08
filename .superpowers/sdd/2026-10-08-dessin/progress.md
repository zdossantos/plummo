# SDD ledger — plan: docs/superpowers/plans/2026-10-08-dessin.md

Pre-flight: existing room, catalog, scoring, pause/reprise and projection contracts are reused; drawing remains a separate game type.

Task 1: complete — drawing rules, rotation, private words, exact guesses, proximity hints, scoring, absence, pause/reprise and recovery implemented. Evidence: `11 passed, 176 assertions` in `/tmp/plummo-drawing-green2.log`.

Task 2: complete — normalized SVG strokes, revision checks, idempotent batches, undo, clear and limits implemented. Evidence: drawing command suite included in `/tmp/plummo-drawing-large-red.log` with `15 passed, 238 assertions`.

Task 3: complete — phone drawing board, word selection, guessing, screen projection, translations, browser coverage, README and build/type/lint/static analysis checks added. Backend/browser end-to-end execution remains dependent on the configured MySQL/browser runtime.

Design ruling: `Game::$state` intentionally uses `array<string,mixed>` because choice, blind and drawing games have different state schemas; game-specific services validate and project each schema.
