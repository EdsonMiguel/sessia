<?php

// Only used with the disposable MySQL database by ConcurrencyTest.
use App\Models\User;
use App\Modules\Attendance\CompleteAttendance;
use App\Modules\Billing\RecordPayment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.mysql.database') !== 'sessia_test') {
    fwrite(STDERR, 'Disposable test database required');
    exit(2);
}
Auth::login(User::findOrFail((int) $argv[1]));
$payload = json_decode(base64_decode($argv[3]), true, flags: JSON_THROW_ON_ERROR);
try {
    if ($argv[2] === 'complete') {
        app(CompleteAttendance::class)->handle($payload['id']);
    } elseif ($argv[2] === 'pay') {
        app(RecordPayment::class)->handle($payload['allocations'], 'pix', $payload['paidAt'], $payload['key']);
    } else {
        exit(2);
    }
    echo 'success';
} catch (ValidationException) {
    echo 'rejected';
}
