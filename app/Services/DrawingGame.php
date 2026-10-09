<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DrawingGame
{
    private function time(): float
    {
        return app(GameEngine::class)->time();
    }

    /** @param array<string,mixed> $state */
    private function save(Game $game, array $state, string $status = 'active'): void
    {
        app(GameEngine::class)->save($game, ['state' => $state, 'status' => $status]);
    }

    /** @return list<int> */
    private function connected(Room $room): array
    {
        return array_values($room->players()->orderBy('connected_since')->orderBy('id')->get()->filter(fn (RoomPlayer $p) => $p->connected())->map(fn (RoomPlayer $p) => $p->id)->all());
    }

    /** @param array<string,mixed> $settings */
    public function start(Room $room, array $settings): Game
    {
        $players = $this->connected($room);
        if (count($players) < 2) {
            throw ValidationException::withMessages(['players' => __('rooms.drawing_minimum')]);
        }
        $counts = app(RoomContentCatalog::class)->availability($room, ContentType::Drawing, $settings['packs']);
        if (($settings['allow_repeats'] ?? false) ? $counts['total'] < 3 : $counts['unseen'] < count($players) * $settings['rounds'] * 3) {
            throw ValidationException::withMessages(['packs' => __('rooms.contents_exhausted')]);
        }
        $game = Game::create(['room_id' => $room->id, 'type' => 'drawing', 'settings' => $settings, 'state' => ['scores' => array_fill_keys($players, 0), 'number' => 0, 'tour' => 1, 'drawn' => [], 'queue' => $players]]);
        $this->next($room, $game);

        return $game;
    }

    private function next(Room $room, Game $game): void
    {
        $state = $game->state;
        $connected = $this->connected($room);
        $state['queue'] = array_values(array_filter($state['queue'], fn ($id) => in_array($id, $connected, true) && ! in_array($id, $state['drawn'], true)));
        foreach ($connected as $id) {
            if (! in_array($id, $state['drawn'], true) && ! in_array($id, $state['queue'], true)) {
                $state['queue'][] = $id;
            }
        }
        if ($state['queue'] === []) {
            if ($state['tour'] >= $game->settings['rounds']) {
                $state['phase'] = 'results';
                $state['deadline'] = 0.0;
                $this->save($game, $state, 'finished');

                return;
            }
            $state['tour']++;
            $state['drawn'] = [];
            $state['queue'] = $connected;
        }
        if (count($connected) < 2) {
            $state['phase'] = 'waiting';
            $state['deadline'] = 0.0;
            $this->save($game, $state);

            return;
        }
        try {
            $words = app(RoomContentCatalog::class)->reveal($room, ContentType::Drawing, $game->settings['packs'], 3, $game->settings['allow_repeats'] ?? false)->map(fn ($content) => $content->payload['word'])->values()->all();
        } catch (ValidationException $exception) {
            if (! isset($state['round'])) {
                throw $exception;
            }
            $state['phase'] = 'paused';
            $state['previous_phase'] = 'reveal';
            $state['remaining'] = 0.0;
            $state['manual_pause'] = true;
            $state['exhausted'] = true;
            $this->save($game, $state);

            return;
        }
        $artist = array_shift($state['queue']);
        $state['drawn'][] = $artist;
        $state = [...$state, 'scores' => $state['scores'] + array_fill_keys($connected, 0), 'number' => $state['number'] + 1, 'phase' => 'selecting', 'previous_phase' => 'selecting', 'remaining' => 0.0, 'manual_pause' => false, 'excluded' => [], 'started_at' => $this->time(), 'deadline' => $this->time() + 15, 'round' => ['artist' => $artist, 'words' => $words, 'word' => null, 'participants' => $connected, 'guessers' => [], 'answers' => [], 'awards' => [], 'near' => [], 'votes' => [], 'canvas' => [], 'revision' => 0]];
        $this->save($game, $state);
    }

    /** @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public function resumed(Room $room, array $state): array
    {
        if ($state['phase'] === 'waiting') {
            $state['phase'] = 'reveal';
        } elseif ($state['phase'] === 'artist_missing' && in_array($state['round']['artist'], $this->connected($room), true)) {
            $state['round']['votes'] = [];
            $state['phase'] = $state['artist_phase'];
            $state['deadline'] += $state['artist_remaining'];
        }

        return $state;
    }

    public function tick(Room $room, Game $game): Game
    {
        $state = $game->state;
        $connected = $this->connected($room);
        // Keep pending artists in live connection order, without drawing twice in a tour.
        $state['queue'] = array_values(array_filter($state['queue'], fn ($id) => in_array($id, $connected, true)));
        foreach ($connected as $id) {
            if (! in_array($id, $state['drawn'], true) && ! in_array($id, $state['queue'], true)) {
                $state['queue'][] = $id;
            }
        }
        if ($state !== $game->state) {
            $this->save($game, $state);
        }
        if ($state['phase'] === 'waiting') {
            if (count($connected) >= 2) {
                $state['phase'] = 'resuming';
                $state['previous_phase'] = 'waiting';
                $state['remaining'] = 0.0;
                $state['deadline'] = $this->time() + 5;
                $this->save($game, $state);
            }

            return $game;
        }
        if ($state['phase'] === 'reveal') {
            if ($state['deadline'] <= $this->time()) {
                if ($room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target) {
                    $state['phase'] = 'results';
                    $this->save($game, $state, 'finished');
                } else {
                    $this->next($room, $game);
                }
            }

            return $game;
        }
        $artistConnected = in_array($state['round']['artist'], $connected, true);
        if (in_array($state['phase'], ['selecting', 'drawing'], true) && ! $artistConnected) {
            $state['artist_phase'] = $state['phase'];
            $state['artist_remaining'] = max(0, $state['deadline'] - $this->time());
            $state['phase'] = 'artist_missing';
            $state['deadline'] = 0.0;
            $this->save($game, $state);
        }
        if ($state['phase'] === 'artist_missing' && $artistConnected) {
            $state['round']['votes'] = [];
            $state['phase'] = 'resuming';
            $state['previous_phase'] = $state['artist_phase'];
            $state['remaining'] = $state['artist_remaining'];
            $state['deadline'] = $this->time() + 5;
            $this->save($game, $state);

            return $game;
        }
        if ($state['phase'] === 'selecting' && $state['deadline'] <= $this->time()) {
            $this->begin($game, random_int(0, 2));
            $state = $game->state;
        }
        if (in_array($state['phase'], ['drawing', 'artist_missing'], true) && $state['round']['word'] !== null) {
            $waiting = array_filter($state['round']['guessers'], fn ($id) => in_array($id, $connected, true) && ! in_array($id, $state['excluded'], true) && ! isset($state['round']['answers'][$id]));
            if ($waiting === [] || ($state['phase'] === 'drawing' && $state['deadline'] <= $this->time())) {
                $this->finish($game);
            }
        }
        if ($game->state['phase'] === 'artist_missing') {
            $voters = array_values(array_diff($connected, [$state['round']['artist']]));
            if ($voters !== [] && array_diff($voters, $game->state['round']['votes']) === []) {
                $this->finish($game);
            }
        }

        return $game;
    }

    private function begin(Game $game, int $choice): void
    {
        $state = $game->state;
        $state['round']['word'] = $state['round']['words'][$choice];
        $state['round']['guessers'] = array_values(array_diff($state['round']['participants'], [$state['round']['artist']]));
        $state['phase'] = 'drawing';
        $state['deadline'] = $this->time() + $game->settings['duration'];
        $this->save($game, $state);
    }

    private function finish(Game $game): void
    {
        $state = $game->state;
        $state['round']['word'] ??= $state['round']['words'][random_int(0, 2)];
        $state['phase'] = 'reveal';
        $state['deadline'] = $this->time() + 3;
        $this->save($game, $state);
    }

    /** @param list<string> $phases */
    private function current(Room $room, int $gameId, int $number, array $phases): Game
    {
        $game = app(GameEngine::class)->tick($room);
        abort_unless($game !== null && $game->type === 'drawing' && $game->id === $gameId && $game->state['number'] === $number && in_array($game->state['phase'], $phases, true), 409);

        return $game;
    }

    public function choose(Room $room, RoomPlayer $player, int $choice, int $gameId, int $number): void
    {
        $game = $this->current($room, $gameId, $number, ['selecting']);
        abort_unless($player->connected() && $game->state['round']['artist'] === $player->id, 403);
        $this->begin($game, $choice);
        $this->tick($room, $game);
    }

    /** @param array<string,mixed> $state */
    private function eligible(RoomPlayer $player, array $state): bool
    {
        return $player->connected() && in_array($player->id, $state['round']['guessers'], true) && ! in_array($player->id, $state['excluded'], true);
    }

    public function guess(Room $room, RoomPlayer $player, string $guess, int $gameId, int $number): void
    {
        $game = $this->current($room, $gameId, $number, ['drawing', 'artist_missing']);
        $state = $game->state;
        abort_unless($state['round']['word'] !== null && $this->eligible($player, $state), 403);
        abort_if(isset($state['round']['answers'][$player->id]), 409);
        $submitted = $guess;
        $word = $this->normalize($state['round']['word']);
        $guess = $this->normalize($guess);
        $state['round']['near'][$player->id] = $guess !== $word && $this->distance($guess, $word) <= (mb_strlen($word) > 7 ? 2 : 1);
        if ($word === $guess) {
            $state['round']['answers'][$player->id] = ['at' => $this->time()];
        }
        $state['round']['guesses'] = array_slice([...($state['round']['guesses'] ?? []), ['playerId' => $player->id, 'text' => $submitted, 'found' => $word === $guess]], -12);
        $this->save($game, $state);
        if ($word === $guess) {
            $this->award($room, $game);
        }
        $this->tick($room, $game);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', Str::lower(Str::ascii($value)));
    }

    private function distance(string $a, string $b): int
    {
        $left = mb_str_split($a);
        $right = mb_str_split($b);
        $row = range(0, count($right));
        foreach ($left as $i => $character) {
            $next = [$i + 1];
            foreach ($right as $j => $other) {
                $next[] = min($next[$j] + 1, $row[$j + 1] + 1, $row[$j] + ($character === $other ? 0 : 1));
            }
            $row = $next;
        }

        return $row[count($right)];
    }

    private function award(Room $room, Game $game): void
    {
        $state = $game->state;
        $rank = 1;
        $awards = [];
        foreach (collect($game->answers())->sortBy('at')->groupBy(fn ($answer) => sprintf('%.6f', $answer['at']), true) as $tied) {
            $points = app(Scoring::class)->rapid(count($state['round']['guessers']), $rank, $tied->count());
            foreach ($tied as $id => $answer) {
                $awards[$id] = $points;
            }
            $rank += $tied->count();
        }
        $awards[$state['round']['artist']] = app(Scoring::class)->artist(count($state['round']['guessers']), count($state['round']['answers']));
        foreach ($awards as $id => $points) {
            $delta = $points - ($state['round']['awards'][$id] ?? 0);
            $state['scores'][$id] = ($state['scores'][$id] ?? 0) + $delta;
            $room->players()->whereKey($id)->increment('score', $delta);
        }
        $state['round']['awards'] = $awards;
        $this->save($game, $state);
    }

    public function cancelRound(Room $room, Game $game): void
    {
        $state = $game->state;
        if (in_array($state['previous_phase'], ['reveal', 'waiting'], true)) {
            return;
        }
        foreach ($state['round']['awards'] as $id => $points) {
            $state['scores'][$id] -= $points;
            $room->players()->whereKey($id)->decrement('score', $points);
        }
        $state['round']['awards'] = [];
        $this->save($game, $state);
    }

    public function skip(Room $room, RoomPlayer $player, int $gameId, int $number): void
    {
        $game = $this->current($room, $gameId, $number, ['artist_missing']);
        abort_unless($player->connected() && $player->id !== $game->state['round']['artist'], 403);
        $state = $game->state;
        $state['round']['votes'] = array_values(array_unique([...$state['round']['votes'], $player->id]));
        $this->save($game, $state);
        $this->tick($room, $game);
    }

    /** @param array<string,mixed> $values */
    public function canvas(Room $room, RoomPlayer $player, string $action, array $values): void
    {
        $game = $this->current($room, $values['game_id'], $values['round'], ['drawing']);
        $state = $game->state;
        abort_unless($player->connected() && $state['round']['artist'] === $player->id, 403);
        abort_unless($values['revision'] === $state['round']['revision'], 409);
        $canvas = $state['round']['canvas'];
        if ($action === 'stroke') {
            $index = $values['id'] - 1;
            abort_unless($index <= count($canvas) && $index >= count($canvas) - 1, 409);
            if ($index === count($canvas)) {
                abort_unless($values['offset'] === 0, 409);
                $canvas[] = ['id' => $values['id'], 'color' => app(GameBonuses::class)->paint($game, $values['color']), 'input_color' => $values['color'], 'width' => $values['width'], 'points' => []];
            }
            $stroke = $canvas[$index];
            abort_unless(($stroke['input_color'] ?? $stroke['color']) === $values['color'] && $stroke['width'] === $values['width'], 409);
            $offset = $values['offset'];
            if ($offset < count($stroke['points'])) {
                abort_unless(array_slice($stroke['points'], $offset, count($values['points'])) == $values['points'], 409);

                return;
            }
            abort_unless($offset === count($stroke['points']), 409);
            if (array_sum(array_map(fn ($stroke) => count($stroke['points']), $canvas)) + count($values['points']) > 10000) {
                throw ValidationException::withMessages(['points' => __('rooms.drawing_limit')]);
            }
            $canvas[$index]['points'] = [...$stroke['points'], ...$values['points']];
        } else {
            if ($action === 'undo') {
                array_pop($canvas);
            } else {
                $canvas = [];
            }
            $state['round']['revision']++;
        }
        $state['round']['canvas'] = $canvas;
        $this->save($game, $state);
    }

    /** @return array<string,mixed> */
    public function view(Room $room, Game $game, ?RoomPlayer $me): array
    {
        $state = $game->state;
        $round = $state['round'];
        $revealed = in_array($state['phase'], ['reveal', 'results'], true) || ($state['previous_phase'] === 'reveal' && in_array($state['phase'], ['paused', 'resuming'], true));
        $artist = $me?->id === $round['artist'];

        return ['id' => $game->id, 'type' => 'drawing', 'phase' => $state['phase'], 'deadline' => $state['deadline'], 'settings' => $game->settings, 'scores' => $state['scores'], 'exhausted' => $state['exhausted'] ?? false, 'targetReached' => $game->status === 'finished' && $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target,
            'round' => ['number' => $state['number'], 'total' => $game->settings['rounds'], 'tour' => $state['tour'], 'artistId' => $round['artist'], 'word' => $revealed ? $round['word'] : null, 'guesses' => array_map(fn ($guess) => [...$guess, 'text' => $guess['found'] && ! $revealed ? null : $guess['text']], $round['guesses'] ?? []), 'canvas' => $round['canvas'], 'revision' => $round['revision'], 'awards' => $round['awards']],
            'me' => $me === null ? null : ['eligible' => $this->eligible($me, $state), 'found' => isset($round['answers'][$me->id]), 'canGuess' => $round['word'] !== null && $this->eligible($me, $state) && ! isset($round['answers'][$me->id]) && in_array($state['phase'], ['drawing', 'artist_missing'], true), 'near' => $round['near'][$me->id] ?? false, 'points' => $round['awards'][$me->id] ?? 0, 'words' => $artist && ($state['phase'] === 'selecting' || ($state['phase'] === 'resuming' && $state['previous_phase'] === 'selecting')) ? $round['words'] : [], 'word' => $artist ? $round['word'] : null, 'canDraw' => $artist && $me->connected() && $state['phase'] === 'drawing', 'canSkip' => $me->connected() && ! $artist && $state['phase'] === 'artist_missing', 'votedSkip' => in_array($me->id, $round['votes'], true)]];
    }
}
