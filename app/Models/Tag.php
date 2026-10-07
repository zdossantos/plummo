<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property int $id */
#[Fillable(['name'])]
class Tag extends Model
{
    /** @return BelongsToMany<Content, $this> */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class);
    }

    /** @return BelongsToMany<Pack, $this> */
    public function packs(): BelongsToMany
    {
        return $this->belongsToMany(Pack::class);
    }
}
