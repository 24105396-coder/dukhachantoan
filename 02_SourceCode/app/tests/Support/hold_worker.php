<?php

// Tiến trình con cho BookingParallelHoldTest: chờ tới mốc thời gian chung rồi giữ chỗ cùng lúc.
// Cách dùng: php tests/Support/hold_worker.php <slotId> <userId> <startAtUnixFloat>

use App\Exceptions\BookingException;
use App\Models\TourSlot;
use App\Models\User;
use App\Services\BookingService;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $slotId, $userId, $startAt] = $argv;
while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    app(BookingService::class)->hold(TourSlot::findOrFail((int) $slotId), User::findOrFail((int) $userId), [
        'quantity' => 1, 'contact_name' => 'Song song', 'contact_phone' => '0901234567', 'contact_email' => 'p@example.com',
    ]);
    echo 'OK';
} catch (BookingException $e) {
    echo 'FULL';
} catch (Throwable $e) {
    echo 'ERROR '.$e->getMessage();
}
