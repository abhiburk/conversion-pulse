<?php

namespace App\Console\Commands;

use App\Actions\EvaluateAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Signature('alerts:evaluate')]
#[Description('Detect traffic drops and processing problems, opening and resolving alerts')]
class RunAlertChecks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EvaluateAlerts $evaluateAlerts): int
    {
        $result = $evaluateAlerts->handle(Date::now());

        $this->components->info("Alerts opened: {$result['opened']}, ongoing: {$result['ongoing']}, resolved: {$result['resolved']}.");

        return self::SUCCESS;
    }
}
