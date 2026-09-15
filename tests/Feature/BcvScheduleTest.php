<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class BcvScheduleTest extends TestCase
{
    public function test_bcv_sync_is_scheduled_at_09_and_17(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())->filter(function (Event $event) {
            return str_contains($event->command ?? '', 'bcv:sync');
        });

        $this->assertCount(2, $events);

        $expressions = $events->map(fn (Event $event) => $event->expression)->values();

        $this->assertTrue($expressions->contains('0 9 * * *'));
        $this->assertTrue($expressions->contains('0 17 * * *'));
    }
}
