<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Events\RoomChanged;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameEngine
{
    public function active(Room $room): ?Game
    {
        return Game::where('room_id', $room->id)->where('status', 'active')->first();
    }

    /** @param array<string, mixed> $settings */
    public function start(Room $room, array $settings): Game
    {
        abort_if($this->active($room) !== null, 409);
        if ($room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target) {
            throw ValidationException::withMessages(['target' => __('rooms.target_reached')]);
        }
        if ($settings['type'] === 'drawing') {
            return app(DrawingGame::class)->start($room, $settings);
        }
        $counts = app(RoomContentCatalog::class)->availability($room, ContentType::from($settings['type'] ?? 'quiz'), $settings['packs']);
        if (($settings['allow_repeats'] ?? false) ? $counts['total'] < 1 : $counts['unseen'] < $settings['rounds']) {
            throw ValidationException::withMessages(['packs' => __('rooms.contents_exhausted')]);
        }
        if ($settings['type'] === 'blind_test' && app(BlindChoices::class)->count() < 8) {
            throw ValidationException::withMessages(['packs' => __('rooms.blind_catalogue_small')]);
        }
        $game = Game::create(['room_id' => $room->id, 'type' => $settings['type'], 'settings' => $settings, 'state' => ['scores' => [], 'number' => 0]]);
        $this->nextRound($room, $game);

        return $game;
    }

    /** @param array<string, mixed> $changes */
    public function save(Game $game, array $changes): void
    {
        $game->update($changes);
        if (config('broadcasting.default') !== 'null') {
            event(new RoomChanged(Room::findOrFail($game->room_id)->code));
        }
    }

    private function nextRound(Room $room, Game $game): void
    {
        $players = $room->players()->get()->filter(fn (RoomPlayer $player) => $player->connected())->modelKeys();
        if ($players === []) {
            return;
        }
        try {
            [$content, $payload] = DB::transaction(function () use ($room, $game) {
                $content = app(RoomContentCatalog::class)->reveal($room, ContentType::from($game->type), $game->settings['packs'], 1, $game->settings['allow_repeats'] ?? false)->firstOrFail();
                $payload = $content->payload;
                if ($game->type === 'blind_test') {
                    $payload = [...app(BlindChoices::class)->prepare($content), 'question' => __('rooms.blind_listen'), 'audio_path' => app(GameAudio::class)->copy($game, $content)];
                }

                return [$content, $payload];
            });
        } catch (ValidationException $exception) {
            if (! isset($game->state['round'])) {
                throw $exception;
            }
            $state = $this->paused($game->state);
            $state['exhausted'] = true;
            $state['manual_pause'] = true;
            $this->save($game, ['state' => $state]);

            return;
        }
        app(GameAudio::class)->retire($game->state['round']['payload']['audio_path'] ?? null);
        $this->save($game, ['state' => ['scores' => $game->state['scores'] + array_fill_keys($players, 0), 'number' => $game->state['number'] + 1, 'phase' => 'answer', 'previous_phase' => 'answer', 'remaining' => 0.0, 'manual_pause' => false, 'started_at' => $this->time(), 'deadline' => $this->time() + $game->settings['duration'], 'round' => ['content_id' => $content->id, 'payload' => $payload, 'participants' => $players, 'answers' => [], 'awards' => []]]]);
    }

    public function time(): float
    {
        return (float) now()->format('U.u');
    }

    public function tick(Room $room): ?Game
    {
        $game = $this->active($room);
        if ($game === null) {
            return null;
        }
        $state = $game->state;
        $connected = $room->players()->get()->filter(fn (RoomPlayer $player) => $player->connected());
        $blocked = $connected->isEmpty() || $room->screen_seen_at === null || $room->screen_seen_at->addSeconds(15)->lte(now());
        if ($blocked && $state['phase'] !== 'paused') {
            $state = $this->paused($state);
            $this->save($game, ['state' => $state]);
        }
        if ($state['phase'] === 'paused') {
            if (! $blocked && ! $state['manual_pause']) {
                $state['phase'] = 'resuming';
                $state['deadline'] = $this->time() + 5;
                $this->save($game, ['state' => $state]);
            }

            return $game;
        }
        if ($state['phase'] === 'resuming') {
            if ($state['deadline'] > $this->time()) {
                return $game;
            }
            $state['phase'] = $state['previous_phase'];
            $state['deadline'] += $state['remaining'];
            if ($game->type === 'drawing') {
                $state = app(DrawingGame::class)->resumed($room, $state);
            }
            $this->save($game, ['state' => $state]);
        }
        if ($game->type === 'drawing') {
            return app(DrawingGame::class)->tick($room, $game);
        }
        if ($state['phase'] === 'answer') {
            $waiting = $room->players()->get()->filter(fn (RoomPlayer $player) => $this->eligible($player, $state) && ! isset($state['round']['answers'][$player->id]));
            if ($state['deadline'] <= $this->time() || $waiting->isEmpty()) {
                $this->finishRound($room, $game);
            }
        } elseif ($state['phase'] === 'reveal' && $state['deadline'] <= $this->time()) {
            $targetReached = $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target;
            if ($state['number'] >= $game->settings['rounds'] || $targetReached) {
                app(GameAudio::class)->retire($state['round']['payload']['audio_path'] ?? null);
                $state['phase'] = 'results';
                $this->save($game, ['state' => $state, 'status' => 'finished']);
            } else {
                $this->nextRound($room, $game);
            }
        }

        return $game;
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function paused(array $state): array
    {
        if ($state['phase'] !== 'paused') {
            $state['previous_phase'] = $state['phase'] === 'resuming' ? $state['previous_phase'] : $state['phase'];
            $state['remaining'] = $state['phase'] === 'resuming' ? $state['remaining'] : max(0, $state['deadline'] - $this->time());
            $state['phase'] = 'paused';
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function eligible(RoomPlayer $player, array $state): bool
    {
        return $player->connected() && in_array($player->id, $state['round']['participants'], true)
            && ! in_array($player->id, $state['excluded'] ?? [], true);
    }

    public function withdraw(Room $room, RoomPlayer $player): void
    {
        $game = $this->active($room);
        if ($game === null) {
            return;
        }
        $state = $game->state;
        if ($game->type === 'drawing') {
            $state['queue'] = array_values(array_diff($state['queue'], [$player->id]));
        }
        $state['excluded'] = array_values(array_unique([...($state['excluded'] ?? []), $player->id]));
        $this->save($game, ['state' => $state]);
    }

    public function answer(Room $room, RoomPlayer $player, int $choice, int $gameId, int $roundNumber): void
    {
        $current = $this->active($room);
        abort_unless($current !== null && $current->id === $gameId && $current->state['number'] === $roundNumber, 409);
        $game = $this->tick($room);
        abort_unless($game !== null && $game->id === $gameId && $game->state['number'] === $roundNumber && $game->state['phase'] === 'answer', 409);
        abort_unless(in_array($game->type, ['quiz', 'blind_test'], true), 409);
        $state = $game->state;
        abort_unless($this->eligible($player, $state), 403);
        abort_if(isset($state['round']['answers'][$player->id]), 409);
        $state['round']['answers'][$player->id] = ['choice' => $choice, 'at' => $this->time()];
        $this->save($game, ['state' => $state]);
        $this->tick($room);
    }

    private function finishRound(Room $room, Game $game): void
    {
        $state = $game->state;
        $correct = collect($game->answers())->filter(fn ($answer) => ($answer['choice'] ?? null) === $state['round']['payload']['correct'])->sortBy('at');
        $rank = 1;
        foreach ($correct->groupBy(fn ($answer) => sprintf('%.6f', $answer['at']), true) as $tied) {
            $points = app(Scoring::class)->rapid(count($state['round']['participants']), $rank, $tied->count());
            foreach ($tied as $id => $answer) {
                $state['round']['awards'][$id] = $points;
                $state['scores'][$id] = ($state['scores'][$id] ?? 0) + $points;
                $room->players()->whereKey($id)->increment('score', $points);
            }
            $rank += $tied->count();
        }
        $state['phase'] = 'reveal';
        $state['deadline'] = $this->time() + 3;
        $this->save($game, ['state' => $state]);
    }

    public function control(Room $room, string $action): void
    {
        if ($action === 'lobby') {
            abort_if($this->active($room) !== null, 409);
            Game::where('room_id', $room->id)->where('status', 'finished')->update(['status' => 'dismissed']);

            return;
        }
        $game = $this->tick($room);
        abort_unless($game !== null && $game->status === 'active', 409);
        $state = $game->state;
        if ($action === 'pause') {
            $state = $this->paused($state);
            $state['manual_pause'] = true;
        } elseif ($action === 'resume') {
            abort_unless($state['phase'] === 'paused' && ! ($state['exhausted'] ?? false) && $room->screen_seen_at?->addSeconds(15)->gt(now()) && $room->players()->get()->contains(fn (RoomPlayer $player) => $player->connected()), 409);
            $state['manual_pause'] = false;
            $state['phase'] = 'resuming';
            $state['deadline'] = $this->time() + 5;
        } else {
            abort_unless($state['phase'] === 'paused', 409);
            app(GameAudio::class)->retire($state['round']['payload']['audio_path'] ?? null);
            $this->save($game, ['status' => 'stopped']);

            return;
        }
        $this->save($game, ['state' => $state]);
    }

    /** @param array<string, mixed> $settings */
    public function recover(Room $room, array $settings): void
    {
        $game = $this->active($room);
        abort_unless($game !== null && $game->state['phase'] === 'paused' && ($game->state['exhausted'] ?? false), 409);
        $counts = app(RoomContentCatalog::class)->availability($room, ContentType::from($settings['type'] ?? $game->type), $settings['packs']);
        $required = $game->type === 'drawing' ? 3 : 1;
        if (($settings['allow_repeats'] ?? false) ? $counts['total'] < $required : $counts['unseen'] < $required) {
            throw ValidationException::withMessages(['packs' => __('rooms.contents_exhausted')]);
        }
        $state = $game->state;
        $state['exhausted'] = false;
        $this->save($game, ['settings' => [...$game->settings, ...$settings], 'state' => $state]);
        $this->control($room, 'resume');
    }

    /** @return array<string, mixed>|null */
    public function view(Room $room, ?RoomPlayer $me, bool $screen = false): ?array
    {
        $game = $this->tick($room) ?? Game::where('room_id', $room->id)->latest('id')->first();
        if ($game?->status !== 'active' && $game?->status !== 'finished') {
            return null;
        }
        if ($game->type === 'drawing') {
            return app(DrawingGame::class)->view($room, $game, $me);
        }
        $state = $game->state;
        $round = $state['round'];
        $revealed = $state['phase'] === 'reveal' || $state['phase'] === 'results' || ($state['previous_phase'] === 'reveal' && in_array($state['phase'], ['paused', 'resuming'], true));

        return ['id' => $game->id, 'settings' => $game->settings, 'exhausted' => $state['exhausted'] ?? false, 'targetReached' => $game->status === 'finished' && $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target, 'type' => $game->type, 'phase' => $state['phase'], 'deadline' => $state['deadline'], 'scores' => $state['scores'], 'round' => ['number' => $state['number'], 'total' => $game->settings['rounds'], 'question' => $game->type === 'blind_test' ? __('rooms.blind_listen') : $round['payload']['question'], 'audio' => $screen && $game->type === 'blind_test' && $game->status === 'active' ? '/rooms/'.$room->code.'/games/'.$game->id.'/rounds/'.$state['number'].'/audio' : null, 'choices' => $round['payload']['choices'], 'correct' => $revealed ? $round['payload']['correct'] : null, 'awards' => $revealed ? $round['awards'] : []], 'me' => $me === null ? null : ['eligible' => $this->eligible($me, $state), 'answered' => isset($round['answers'][$me->id]), 'choice' => $round['answers'][$me->id]['choice'] ?? null, 'points' => $revealed ? ($round['awards'][$me->id] ?? 0) : null]];
    }
}
