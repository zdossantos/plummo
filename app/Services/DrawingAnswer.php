<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Conservative number agreement, without accepting arbitrary missing final letters. */
class DrawingAnswer
{
    private const INVARIANT = ['bus', 'bras', 'souris', 'tapis', 'radis', 'temps', 'corps', 'pays', 'prix', 'voix', 'croix', 'nez', 'gaz', 'noix', 'choix', 'bois', 'repos', 'poids', 'jus', 'repas', 'bas', 'tas', 'os', 'avis', 'ananas', 'tennis', 'sheep', 'deer', 'fish', 'news', 'series', 'species'];

    private const IRREGULAR = ['oeil' => 'yeux', 'monsieur' => 'messieurs', 'madame' => 'mesdames', 'child' => 'children', 'person' => 'people', 'man' => 'men', 'woman' => 'women', 'mouse' => 'mice', 'goose' => 'geese', 'foot' => 'feet', 'tooth' => 'teeth', 'ox' => 'oxen', 'workman' => 'workmen', 'knife' => 'knives', 'leaf' => 'leaves', 'wolf' => 'wolves', 'wife' => 'wives', 'life' => 'lives'];

    public function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', Str::lower(Str::ascii($value)));
    }

    public function matches(string $word, string $guess): bool
    {
        if ($this->normalize($word) === $this->normalize($guess)) {
            return true;
        }
        $tokens = fn (string $value): array => preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $left = $tokens($word);
        $right = $tokens($guess);
        $pattern = function (array $parts): string {
            return '/^'.implode('', array_map(fn (string $part): string => '(?:'.implode('|', array_map(fn (string $form): string => preg_quote($form, '/'), $this->forms($part))).')', $parts)).'$/';
        };
        if ($left !== [] && preg_match($pattern($left), $this->normalize($guess)) === 1) {
            return true;
        }
        if ($right !== [] && preg_match($pattern($right), $this->normalize($word)) === 1) {
            return true;
        }
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $token) {
            if (! in_array($token, $this->forms($right[$index]), true) && ! in_array($right[$index], $this->forms($token), true)) {
                return false;
            }
        }

        return $left !== [];
    }

    /** @return list<string> */
    private function forms(string $word): array
    {
        if ($word === 'bus') {
            return ['bus', 'buses'];
        }
        $forms = [$word, ...$this->plurals($word)];
        if (! in_array($word, self::INVARIANT, true)) {
            $roots = [substr($word, 0, -1), substr($word, 0, -2), substr($word, 0, -3).'y', substr($word, 0, -3).'al', substr($word, 0, -3).'ail'];
            $irregular = array_search($word, self::IRREGULAR, true);
            if ($irregular !== false) {
                $roots[] = $irregular;
            }
            foreach ($roots as $root) {
                if ($root !== '' && in_array($word, $this->plurals($root), true)) {
                    $forms[] = $root;
                }
            }
        }

        return array_values(array_unique($forms));
    }

    /** @return list<string> */
    private function plurals(string $word): array
    {
        $plural = $this->plural($word);
        if ($plural !== $word && in_array($plural, self::INVARIANT, true)) {
            return [$word];
        }
        $dual = ['animal', 'canal', 'metal', 'signal', 'local', 'general', 'principal'];

        return in_array($word, $dual, true) ? [$plural, $word.'s'] : [$plural];
    }

    private function plural(string $word): string
    {
        if (strlen($word) < 2 || in_array($word, self::INVARIANT, true) || is_numeric($word)) {
            return $word;
        }
        if (isset(self::IRREGULAR[$word])) {
            return self::IRREGULAR[$word];
        }
        if (in_array($word, self::IRREGULAR, true)) {
            return $word;
        }
        if (in_array($word, ['cheval', 'animal', 'journal', 'hopital', 'canal', 'metal', 'signal', 'bocal', 'local', 'general', 'principal'], true)) {
            return substr($word, 0, -2).'aux';
        }
        if (in_array($word, ['bijou', 'caillou', 'chou', 'genou', 'hibou', 'joujou', 'pou', 'travail', 'vitrail', 'corail', 'bail', 'email', 'soupirail'], true)) {
            return str_ends_with($word, 'ail') ? substr($word, 0, -3).'aux' : $word.'x';
        }
        if (preg_match('/(eau|au|eu)$/', $word) && ! in_array($word, ['bleu', 'pneu', 'landau', 'emeu'], true)) {
            return $word.'x';
        }
        if (preg_match('/[^aeiou]y$/', $word)) {
            return substr($word, 0, -1).'ies';
        }
        if (preg_match('/(aux|eux)$/', $word) || in_array($word, ['bijoux', 'cailloux', 'choux', 'genoux', 'hiboux', 'joujoux', 'poux'], true)) {
            return $word;
        }
        if (preg_match('/(ch|sh|ss|x|zz)$/', $word) || $word === 'gas') {
            return $word.'es';
        }
        if (preg_match('/[sz]$/', $word)) {
            return $word;
        }

        return $word.'s';
    }
}
