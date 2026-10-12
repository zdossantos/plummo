<?php

namespace App\Services;

class MiniGameScenes
{
    public const TRIALS = ['memory', 'hide', 'count', 'cups', 'author'];

    /** @return list<string> */
    public function available(int $players): array
    {
        return array_values(array_filter(self::TRIALS, fn ($trial) => $players >= ($trial === 'author' ? 3 : 1)));
    }

    /** @return array<string, mixed> */
    public function generate(string $kind): array
    {
        $catalog = app(PlummoCatalog::class);
        $colors = $catalog->colors();
        $heads = array_keys(array_filter($catalog->slots(), fn ($slot) => $slot === 'head'));
        if ($kind === 'memory' || $kind === 'hide') {
            $people = [];
            $pairs = [];
            foreach ($colors as $color) {
                foreach ($heads as $head) {
                    $pairs[] = ['color' => $color, 'accessories' => [$head]];
                }
            }
            shuffle($pairs);
            if ($kind === 'memory') {
                shuffle($colors);
                shuffle($heads);
                foreach (array_slice($colors, 0, 4) as $index => $color) {
                    $people[] = ['color' => $color, 'accessories' => [$heads[$index]], 'number' => $index + 1];
                }
                $target = random_int(0, 3);
                $attribute = random_int(0, 1) === 0 ? 'color' : 'accessory';
                $choices = $attribute === 'color' ? array_column($people, 'color') : array_map(fn ($p) => $p['accessories'][0], $people);
                shuffle($choices);

                return ['scene' => ['people' => $people], 'observe' => 8, 'question' => 'mini_memory_'.$attribute, 'args' => ['number' => $target + 1], 'choiceKind' => $attribute, 'choices' => $choices, 'correct' => array_search($attribute === 'color' ? $people[$target]['color'] : $people[$target]['accessories'][0], $choices, true)];
            }
            foreach (array_slice($pairs, 0, 12) as $index => $person) {
                $people[] = [...$person, 'number' => $index + 1];
            }
            $target = random_int(0, 11);

            return ['scene' => ['people' => $people], 'observe' => 0, 'question' => 'mini_hide_question', 'args' => ['color' => $people[$target]['color'], 'accessory' => $people[$target]['accessories'][0]], 'choiceKind' => 'number', 'choices' => array_map('strval', range(1, 12)), 'correct' => $target];
        }
        if ($kind === 'count') {
            $inside = 0;
            $events = [];
            $residents = [];
            foreach (range(0, 7) as $index) {
                $direction = $inside === 0 || random_int(0, 1) === 0 ? 1 : -1;
                $inside += $direction;
                if ($direction === 1) {
                    $color = $colors[array_rand($colors)];
                    $residents[] = $color;
                } else {
                    $color = array_pop($residents);
                }
                $events[] = ['at' => 1 + $index * 1.35, 'direction' => $direction, 'side' => $index % 2 === 0 ? -1 : 1, 'color' => $color];
            }

            return ['scene' => ['events' => $events, 'residents' => $residents], 'observe' => 12, 'question' => 'mini_count_question', 'args' => [], 'choiceKind' => 'number', 'choices' => array_map('strval', range(0, 8)), 'correct' => $inside];
        }
        if ($kind === 'cups') {
            $order = [0, 1, 2];
            $swaps = [];
            $last = null;
            $pairs = [[0, 1], [1, 2], [0, 2]];
            $at = 2.1;
            $durations = [0.72, 0.5, 0.36, 0.58, 0.32, 0.44];
            foreach (range(0, 5) as $index) {
                $possible = array_values(array_filter($pairs, fn ($pair) => $pair !== $last));
                $pair = collect($possible)->random();
                [$order[$pair[0]], $order[$pair[1]]] = [$order[$pair[1]], $order[$pair[0]]];
                $swaps[] = ['at' => $at, 'duration' => $durations[$index], 'positions' => $pair];
                $at += $durations[$index] + 0.12;
                $last = $pair;
            }

            return ['scene' => ['swaps' => $swaps, 'under' => 0, 'color' => $colors[array_rand($colors)]], 'observe' => $at + 0.7, 'question' => 'mini_cups_question', 'args' => [], 'choiceKind' => 'number', 'choices' => ['1', '2', '3'], 'correct' => array_search(0, $order, true)];
        }

        return ['scene' => null, 'observe' => 0, 'question' => 'mini_author_prompt_'.random_int(1, 4), 'args' => [], 'choiceKind' => 'author', 'choices' => [], 'correct' => null];
    }
}
