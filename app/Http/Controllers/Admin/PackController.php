<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Tag;
use App\Services\ContentCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PackController extends Controller
{
    public function index(ContentCatalog $catalog): InertiaResponse
    {
        $packs = Pack::with('tags')->orderBy('name')->get()->map(function (Pack $pack) use ($catalog): array {
            $counts = [];
            foreach (ContentType::cases() as $type) {
                $counts[$type->value] = $catalog->query($type, [$pack->id])->count();
            }

            return [...$pack->toArray(), 'counts' => $counts];
        });

        return Inertia::render('admin/Packs', ['packs' => $packs, 'tags' => Tag::orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        return $this->save($request, new Pack);
    }

    public function update(Request $request, Pack $pack): JsonResponse|RedirectResponse
    {
        return $this->save($request, $pack);
    }

    private function save(Request $request, Pack $pack): JsonResponse|RedirectResponse
    {
        $new = ! $pack->exists;
        $values = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('packs')->ignore($pack)],
            'tag_ids' => ['required', 'array', 'min:1', 'max:30'],
            'tag_ids.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
        ]);
        DB::transaction(function () use ($values, $pack): void {
            $pack->fill(['name' => $values['name']])->save();
            $pack->tags()->sync($values['tag_ids']);
        });

        return $request->expectsJson() ? response()->json($pack->load('tags'), $new ? 201 : 200) : to_route('admin.packs.index');
    }

    public function destroy(Request $request, Pack $pack): Response|RedirectResponse
    {
        $pack->delete();

        return $request->expectsJson() ? response()->noContent() : to_route('admin.packs.index');
    }
}
