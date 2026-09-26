<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$d = App\Models\Dispatch::where('Status', 'Pending')->latest('DispatchID')->first();

if (!$d) {
    echo "No pending dispatch found.\n";
    exit;
}

$d->update(['Status' => 'On Route', 'AcceptedAt' => now()]);
$d->truck()->update(['Status' => 'On Route']);

App\Models\DispatchLog::create([
    'DispatchID' => $d->DispatchID,
    'Action' => 'Accepted',
    'Notes' => 'Manually simulated for testing.',
    'LoggedAt' => now(),
]);

echo "Done: Dispatch #{$d->DispatchID} accepted, truck #{$d->TruckID} set to On Route.\n";