<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ContentCatalog
{
    /** @param list<int> $packIds
     * @return Builder<Content>
     */
    public function query(ContentType $type, array $packIds): Builder
    {
        $packIds = array_values(array_unique($packIds));
        $packs = Pack::with('tags')->whereIn('id', $packIds)->get();
        if (count($packIds) < 1 || count($packIds) > 3 || $packs->count() !== count($packIds)) {
            throw ValidationException::withMessages(['packs' => __('admin.invalid_packs')]);
        }

        return Content::where('type', $type->value)->where('published', true)
            ->where(function (Builder $query) use ($packs): void {
                foreach ($packs as $pack) {
                    $ids = $pack->tags->modelKeys();
                    $query->orWhere(function (Builder $group) use ($ids): void {
                        if ($ids === []) {
                            $group->whereRaw('1 = 0');
                        } else {
                            $group->whereHas('tags', fn (Builder $tags) => $tags->whereIn('tags.id', $ids), '=', count($ids));
                        }
                    });
                }
            });
    }
}
