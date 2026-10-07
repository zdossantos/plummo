<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class ContentImportPreview
{
    /** @return list<string> */
    public function columns(ContentType $type): array
    {
        return match ($type) {
            ContentType::Quiz => ['question', 'reponse_a', 'reponse_b', 'reponse_c', 'reponse_d', 'bonne_reponse', 'tags'],
            ContentType::BlindTest => ['titre', 'artiste', 'fichier_audio', 'tags'],
            ContentType::Drawing => ['mot', 'tags'],
            ContentType::Phrase => ['debut_phrase', 'tags'],
        };
    }

    /** @param list<array{name:string,path:string}> $audios
     * @return array{rows:list<array<string,mixed>>,unused:list<string>}
     */
    public function read(ContentType $type, UploadedFile $file, array $audios): array
    {
        try {
            $raw = $this->table($file);
        } catch (Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            throw ValidationException::withMessages(['table' => __('admin.import_unreadable')]);
        }
        $header = array_shift($raw) ?? [];
        if (array_filter($header, fn ($value) => ! is_scalar($value) && $value !== null)) {
            throw ValidationException::withMessages(['table' => __('admin.import_unreadable')]);
        }
        $header = array_map(fn ($v) => trim((string) $v, "\xEF\xBB\xBF \t\n\r"), $header);
        if (count(array_unique($header)) !== count($header) || array_diff($this->columns($type), $header)) {
            throw ValidationException::withMessages(['table' => __('admin.import_columns', ['columns' => implode(', ', $this->columns($type))])]);
        }
        $rows = [];
        $used = [];
        $seen = [];
        foreach ($raw as $i => $cells) {
            if (! array_filter($cells, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $values = [];
            $errors = [];
            foreach ($header as $j => $name) {
                $value = $cells[$j] ?? '';
                if (! is_scalar($value)) {
                    $errors[] = __('admin.import_cell_type', ['column' => $name]);
                    $value = '';
                }
                $values[$name] = trim((string) $value);
            }
            $payload = match ($type) {
                ContentType::Quiz => ['question' => $values['question'], 'choices' => array_map(fn ($letter) => $values['reponse_'.$letter], ['a', 'b', 'c', 'd']), 'correct' => array_search(strtoupper($values['bonne_reponse']), ['A', 'B', 'C', 'D'], true)],
                ContentType::BlindTest => ['title' => $values['titre'], 'artist' => $values['artiste']],
                ContentType::Drawing => ['word' => $values['mot']],
                ContentType::Phrase => ['prompt' => $values['debut_phrase']],
            };
            $tagIds = [];
            foreach (array_unique(array_filter(array_map('trim', explode('|', $values['tags'])))) as $tagName) {
                $tag = Tag::where('name', $tagName)->first();
                if ($tag) {
                    $tagIds[] = $tag->id;
                } else {
                    $errors[] = __('admin.import_unknown_tag', ['tag' => $tagName]);
                }
            }
            $audio = null;
            if ($type === ContentType::BlindTest) {
                $matches = array_values(array_filter($audios, fn ($audio) => $audio['name'] === $values['fichier_audio']));
                if (count($matches) !== 1) {
                    $errors[] = __('admin.import_audio_match', ['name' => $values['fichier_audio']]);
                } else {
                    $audio = $matches[0];
                    $used[] = $audio['name'];
                }
            }
            $data = ['type' => $type->value, 'published' => true, 'payload' => $payload, 'tag_ids' => $tagIds];
            $errors = [...$errors, ...Validator::make($data, ContentRules::rules($type->value, true))->errors()->all()];
            $signature = json_encode($payload);
            $existing = Content::where('type', $type->value);
            foreach ($payload as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $index => $choice) {
                        $existing->where('payload->'.$key.'->'.$index, $choice);
                    }
                } else {
                    $existing->where('payload->'.$key, $value);
                }
            }
            $duplicate = isset($seen[$signature]) || $existing->exists();
            $seen[$signature] = true;
            $rows[] = ['line' => $i + 2, 'values' => $values, 'payload' => $payload, 'tag_ids' => $tagIds, 'audio' => $audio, 'errors' => $errors, 'warnings' => $duplicate ? [__('admin.import_duplicate')] : []];
        }

        return ['rows' => $rows, 'unused' => array_values(array_unique(array_diff(array_column($audios, 'name'), $used)))];
    }

    /** @return list<list<mixed>> */
    private function table(UploadedFile $file): array
    {
        $rows = [];
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $reader = new Reader;
            $reader->open($file->getPathname());
            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $rows[] = array_values($row->toArray());
                        $this->limit($rows);
                    }
                    break;
                }
            } finally {
                $reader->close();
            }
        } else {
            $handle = fopen($file->getPathname(), 'r');
            if ($handle === false) {
                throw ValidationException::withMessages(['table' => __('admin.import_unreadable')]);
            }
            try {
                $first = fgets($handle) ?: '';
                $separator = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
                rewind($handle);
                while (($row = fgetcsv($handle, null, $separator, '"', '')) !== false) {
                    $rows[] = $row;
                    $this->limit($rows);
                }
            } finally {
                fclose($handle);
            }
        }

        return $rows;
    }

    /** @param list<list<mixed>> $rows */
    private function limit(array $rows): void
    {
        if (count($rows) > 501) {
            throw ValidationException::withMessages(['table' => __('admin.import_limit')]);
        }
    }
}
