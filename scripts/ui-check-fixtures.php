<?php

// Standalone browser checks must never seed a development database.
use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Pack;
use App\Models\Room;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'plummo_testing') {
    throw new RuntimeException('UI fixtures require APP_ENV=testing and plummo_testing.');
}
if (($argv[1] ?? '') === 'cleanup') {
    $ids = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($ids) {
        Content::whereIn('id', $ids['contents'])->delete();
        Pack::whereIn('id', $ids['packs'])->where('name', 'like', 'UIcheck:%')->delete();
        Tag::whereIn('id', $ids['tags'])->where('name', 'like', 'UIcheck:%')->delete();
        Room::whereIn('code', $ids['rooms'] ?? [])->delete();
        User::where('id', $ids['user'])->where('email', $ids['email'])->delete();
    });
    exit;
}
if (! in_array($argv[1] ?? '', ['create', 'probe'], true)) {
    throw new InvalidArgumentException('Expected create or cleanup.');
}
$ids = DB::transaction(function () use ($argv) {
    $key = bin2hex(random_bytes(8));
    $password = bin2hex(random_bytes(16));
    $user = User::create(['name' => 'UIcheck:'.$key, 'email' => $key.'@example.test', 'password' => $password]);
    $user->forceFill(['is_admin' => true])->save();
    $ids = ['user' => $user->id, 'email' => $user->email, 'password' => $password, 'tags' => [], 'packs' => [], 'contents' => []];
    if ($argv[1] === 'probe') {
        return $ids;
    }
    foreach (range(1, 30) as $i) {
        $name = 'UIcheck:'.$key.$i.str_repeat('W', 70);
        $tag = Tag::create(['name' => $name]);
        $pack = Pack::create(['name' => $name]);
        $pack->tags()->sync([$tag->id]);
        $content = Content::create(['type' => ContentType::Quiz, 'published' => true, 'payload' => ['question' => str_repeat('W', 80), 'choices' => array_fill(0, 4, str_repeat('W', 40)), 'correct' => 0]]);
        $content->tags()->sync([$tag->id]);
        $ids['tags'][] = $tag->id;
        $ids['packs'][] = $pack->id;
        $ids['contents'][] = $content->id;
    }

    return $ids;
});
echo json_encode($ids, JSON_THROW_ON_ERROR);
