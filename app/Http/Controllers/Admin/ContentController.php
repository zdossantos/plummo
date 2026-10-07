<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentRequest;
use App\Models\Content;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ContentController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate(['status' => ['nullable', 'in:draft,published'], 'type' => ['nullable', 'string', 'in:quiz,blind_test,drawing,phrase'], 'search' => ['nullable', 'string', 'max:100']]);
        $query = Content::with('tags')->latest('id');
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('published', $filters['status'] === 'published');
        }
        if (! empty($filters['search'])) {
            $query->where('payload', 'like', '%'.$filters['search'].'%');
        }

        return Inertia::render('admin/Contents', ['contents' => $query->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('admin/ContentForm', ['content' => null, 'tags' => Tag::orderBy('name')->get()]);
    }

    public function edit(Content $content): InertiaResponse
    {
        return Inertia::render('admin/ContentForm', ['content' => $content->load('tags'), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function store(ContentRequest $request): JsonResponse|RedirectResponse
    {
        return $this->save($request, new Content);
    }

    public function update(ContentRequest $request, Content $content): JsonResponse|RedirectResponse
    {
        return $this->save($request, $content);
    }

    private function save(ContentRequest $request, Content $content): JsonResponse|RedirectResponse
    {
        $new = ! $content->exists;
        $values = $request->validated();
        $type = ContentType::from($values['type']);
        $keys = match ($type) {
            ContentType::Quiz => ['question', 'choices', 'correct'],
            ContentType::BlindTest => ['title', 'artist'],
            ContentType::Drawing => ['word'],
            ContentType::Phrase => ['prompt'],
        };
        $payload = array_intersect_key($values['payload'] ?? [], array_flip($keys));
        $oldAudio = null;
        $newAudio = $request->file('audio')?->store('audio', 'local');
        if ($newAudio === false) {
            throw ValidationException::withMessages(['audio' => __('admin.audio_failed')]);
        }
        try {
            DB::transaction(function () use ($values, $payload, $type, &$content, &$oldAudio, $newAudio): void {
                if ($content->exists) {
                    $content = Content::whereKey($content->id)->lockForUpdate()->firstOrFail();
                }
                $oldAudio = $content->payload['audio_path'] ?? null;
                if ($type === ContentType::BlindTest && ($newAudio || $oldAudio)) {
                    $payload['audio_path'] = $newAudio ?: $oldAudio;
                }
                if ($type === ContentType::BlindTest && $values['published'] && ! isset($payload['audio_path'])) {
                    throw ValidationException::withMessages(['audio' => __('admin.audio_required')]);
                }
                $content->fill(['type' => $type, 'payload' => $payload, 'published' => $values['published']])->save();
                $content->tags()->sync($values['tag_ids']);
            });
        } catch (Throwable $error) {
            if ($newAudio) {
                Storage::disk('local')->delete($newAudio);
            }
            throw $error;
        }
        if ($oldAudio && ($newAudio || $type !== ContentType::BlindTest)) {
            Storage::disk('local')->delete($oldAudio);
        }

        return $request->expectsJson() ? response()->json($content->load('tags'), $new ? 201 : 200) : to_route('admin.contents.edit', $content);
    }

    public function audio(Content $content): StreamedResponse
    {
        abort_unless($content->type === ContentType::BlindTest && isset($content->payload['audio_path']), 404);
        $path = $content->payload['audio_path'];
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Content $content): Response|RedirectResponse
    {
        $audio = $content->payload['audio_path'] ?? null;
        $content->delete();
        if ($audio) {
            Storage::disk('local')->delete($audio);
        }

        return $request->expectsJson() ? response()->noContent() : to_route('admin.contents.index');
    }
}
