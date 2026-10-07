<?php

namespace App\Models;

use App\Enums\ContentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property ContentType $type
 * @property string $table_path
 * @property string $table_name
 * @property list<array{name:string,path:string}> $audios
 * @property array{rows:list<array<string,mixed>>,unused:list<string>} $preview
 * @property array<string,int>|null $result
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['user_id', 'type', 'table_path', 'table_name', 'audios', 'preview', 'result', 'expires_at'])]
class ContentImport extends Model
{
    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['type' => ContentType::class, 'audios' => 'array', 'preview' => 'array', 'result' => 'array', 'expires_at' => 'immutable_datetime'];
    }
}
