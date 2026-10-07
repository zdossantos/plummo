<?php

namespace App\Services;

use App\Models\Content;
use App\Models\ContentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContentImports
{
    /** @return array<string,int> */
    public function confirm(ContentImport $import, bool $published): array
    {
        $copies = [];
        try {
            $result = DB::transaction(function () use ($import, $published, &$copies): array {
                $batch = ContentImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
                abort_if($batch->result !== null, 409);
                abort_if($batch->expires_at->isPast(), 410);
                $file = new UploadedFile(Storage::disk('local')->path($batch->table_path), $batch->table_name, null, null, true);
                $preview = app(ContentImportPreview::class)->read($batch->type, $file, $batch->audios);
                $added = 0;
                foreach ($preview['rows'] as $row) {
                    if ($row['errors']) {
                        continue;
                    }
                    $payload = $row['payload'];
                    if ($row['audio']) {
                        $path = 'audio/'.Str::uuid().'.'.pathinfo($row['audio']['name'], PATHINFO_EXTENSION);
                        if (! Storage::disk('local')->copy($row['audio']['path'], $path)) {
                            throw ValidationException::withMessages(['audio' => __('admin.audio_failed')]);
                        }
                        $copies[] = $path;
                        $payload['audio_path'] = $path;
                    }
                    $content = Content::create(['type' => $batch->type, 'payload' => $payload, 'published' => $published]);
                    $content->tags()->sync($row['tag_ids']);
                    $added++;
                }
                $result = ['added' => $added, 'published' => $published ? $added : 0, 'refused' => count($preview['rows']) - $added];
                $batch->update(['preview' => $preview, 'result' => $result]);

                return $result;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($copies);
            throw $e;
        }
        $this->cleanFiles($import);

        return $result;
    }

    public function cleanFiles(ContentImport $import): void
    {
        Storage::disk('local')->delete([$import->table_path, ...array_column($import->audios, 'path')]);
    }

    public function prune(): int
    {
        $count = 0;
        foreach (ContentImport::where('expires_at', '<=', now())->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$count): void {
                $import = ContentImport::whereKey($id)->lockForUpdate()->first();
                if ($import && $import->expires_at->isPast()) {
                    $this->cleanFiles($import);
                    $import->delete();
                    $count++;
                }
            });
        }

        return $count;
    }
}
