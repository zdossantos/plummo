<?php

namespace App\Services;

use Illuminate\Support\Str;

class BluffAnswers
{
    public function normalize(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($text))) ?? '';
    }

    /** @param array<int, string> $submitted
     * @return list<array{id: string, text: string, correct: bool, authors: list<int>}>
     */
    public function build(string $truth, array $submitted): array
    {
        $key = $this->normalize($truth);
        $entries = [$key => ['id' => (string) Str::uuid(), 'text' => $truth, 'correct' => true, 'authors' => []]];
        foreach ($submitted as $author => $text) {
            $normalized = $this->normalize($text);
            if ($normalized === '') {
                continue;
            }
            $entries[$normalized] ??= ['id' => (string) Str::uuid(), 'text' => trim($text), 'correct' => false, 'authors' => []];
            $entries[$normalized]['authors'][] = (int) $author;
        }
        $entries = array_values($entries);
        shuffle($entries);

        return $entries;
    }

    /** @param list<array{id: string, correct: bool, authors: list<int>}> $entries
     * @param  array<int, string>  $votes
     * @return array<int, int>
     */
    public function awards(array $entries, array $votes): array
    {
        $awards = [];
        foreach ($entries as $entry) {
            $voters = array_keys(array_filter($votes, fn ($choice) => $choice === $entry['id']));
            if ($entry['correct']) {
                foreach ([...$entry['authors'], ...$voters] as $player) {
                    $awards[$player] = ($awards[$player] ?? 0) + 65;
                }
            } elseif ($voters !== [] && $entry['authors'] !== []) {
                $points = (int) round(count($voters) * 65 / count($entry['authors']));
                foreach ($entry['authors'] as $player) {
                    $awards[$player] = ($awards[$player] ?? 0) + $points;
                }
            }
        }

        return $awards;
    }
}
