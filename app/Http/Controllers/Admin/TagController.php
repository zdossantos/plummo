<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TagController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('admin/Tags', ['tags' => Tag::withCount(['contents', 'packs'])->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $tag = Tag::create($request->validate(['name' => ['required', 'string', 'max:100', 'unique:tags,name']]));

        return $request->expectsJson() ? response()->json($tag, 201) : to_route('admin.tags.index');
    }

    public function update(Request $request, Tag $tag): JsonResponse|RedirectResponse
    {
        $tag->update($request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('tags')->ignore($tag)]]));

        return $request->expectsJson() ? response()->json($tag) : to_route('admin.tags.index');
    }

    public function destroy(Request $request, Tag $tag): Response|RedirectResponse
    {
        abort_if($tag->packs()->exists(), 409, __('admin.tag_used'));
        $tag->delete();

        return $request->expectsJson() ? response()->noContent() : to_route('admin.tags.index');
    }
}
