<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Participant;

$ps = Participant::has('lotteryTickets')->with('account.customer')->limit(10)->get();
foreach ($ps as $p) {
    echo "P_ID: {$p->id} | Name: {$p->account->customer->name} | Tickets: " . $p->lotteryTickets()->count() . "\n";
}
