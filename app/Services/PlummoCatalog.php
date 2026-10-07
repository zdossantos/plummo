<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class PlummoCatalog
{
    /** @return array<string, mixed> */
    public function data(): array
    {
        return json_decode(File::get(public_path('plummo/catalog.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    public function colors(): array
    {
        return array_column($this->data()['colors'], 'id');
    }

    /** @return array<string, string> */
    public function slots(): array
    {
        return array_column($this->data()['accessories'], 'slot', 'id');
    }
}
