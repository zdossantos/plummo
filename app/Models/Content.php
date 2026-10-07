<?php

namespace App\Models;

use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property ContentType $type
 * @property array<string, mixed> $payload
 * @property bool $published
 */
#[Fillable(['type', 'payload', 'published'])]
class Content extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => ContentType::class, 'payload' => 'array', 'published' => 'boolean'];
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
