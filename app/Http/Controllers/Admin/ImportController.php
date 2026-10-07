<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\ContentImport;
use App\Services\ContentImportPreview;
use App\Services\ContentImports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Imports', ['batch' => null]);
    }

    public function show(Request $request, ContentImport $import): Response|JsonResponse
    {
        $this->owner($request, $import);
        $data = $import->only(['id', 'type', 'preview', 'result']);
        foreach ($data['preview']['rows'] as &$row) {
            $row['audio'] = $row['audio'] ? ['name' => $row['audio']['name']] : null;
        }
        unset($row);

        return $request->expectsJson() ? response()->json($data) : Inertia::render('admin/Imports', ['batch' => $data]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::enum(ContentType::class)], 'table' => ['required', 'file', 'extensions:csv,xlsx', 'max:5120'], 'audios' => ['nullable', 'array', 'max:50'], 'audios.*' => ['file', 'mimes:mp3,wav,ogg,m4a', 'max:20480']]);
        $paths = [];
        try {
            $table = $request->file('table');
            $tablePath = $table->store('imports', 'local');
            if ($tablePath === false) {
                throw ValidationException::withMessages(['table' => __('admin.import_unreadable')]);
            }
            $paths[] = $tablePath;
            $audios = [];
            foreach ($request->file('audios', []) as $audio) {
                $path = $audio->store('imports', 'local');
                if ($path === false) {
                    throw ValidationException::withMessages(['audios' => __('admin.audio_failed')]);
                }
                $paths[] = $path;
                $audios[] = ['name' => $audio->getClientOriginalName(), 'path' => $path];
            }
            $type = ContentType::from($data['type']);
            $preview = app(ContentImportPreview::class)->read($type, $table, $audios);
            $import = ContentImport::create(['user_id' => $request->user()->id, 'type' => $type, 'table_path' => $tablePath, 'table_name' => $table->getClientOriginalName(), 'audios' => $audios, 'preview' => $preview, 'expires_at' => now()->addHour()]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        return $request->expectsJson() ? response()->json(['id' => $import->id], 201) : to_route('admin.imports.show', $import);
    }

    public function confirm(Request $request, ContentImport $import, ContentImports $imports): JsonResponse|RedirectResponse
    {
        $this->owner($request, $import);
        $values = $request->validate(['published' => ['required', 'boolean']]);
        $result = $imports->confirm($import, $values['published']);

        return $request->expectsJson() ? response()->json($result) : to_route('admin.imports.show', $import);
    }

    public function audio(Request $request, ContentImport $import, int $line): StreamedResponse
    {
        $this->owner($request, $import);
        abort_if($import->result !== null, 410);
        $row = collect($import->preview['rows'])->firstWhere('line', $line);
        $path = $row['audio']['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function template(Request $request, ContentType $type): StreamedResponse
    {
        return response()->streamDownload(function () use ($type): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Cannot open response stream');
            }
            fputcsv($stream, app(ContentImportPreview::class)->columns($type), ',', '"', '');
            fclose($stream);
        }, $type->value.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function errors(Request $request, ContentImport $import): StreamedResponse
    {
        $this->owner($request, $import);

        return response()->streamDownload(function () use ($import): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Cannot open response stream');
            }
            $columns = app(ContentImportPreview::class)->columns($import->type);
            fputcsv($stream, [...$columns, 'ligne', 'erreurs'], ',', '"', '');
            foreach ($import->preview['rows'] as $row) {
                if (! $row['errors']) {
                    continue;
                }
                $values = [...array_map(fn ($column) => $row['values'][$column] ?? '', $columns), $row['line'], implode(' | ', $row['errors'])];
                $values = array_map(fn ($value) => preg_match('/^[=+@-]/', (string) $value) ? "'".$value : $value, $values);
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, 'errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function owner(Request $request, ContentImport $import): void
    {
        abort_unless($import->user_id === $request->user()->id, 404);
        abort_if($import->expires_at->isPast(), 410);
    }
}
