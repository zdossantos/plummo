<?php

namespace Tests;

use App\Models\Room;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** Prepare confirmations for tests concerned with gameplay rather than lobby preparation. */
    public function startReadyGame(Room $room, array $settings): TestResponse
    {
        prepareReadyGame($room, $settings);

        return $this->postJson('/rooms/'.$room->code.'/games', $settings);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
