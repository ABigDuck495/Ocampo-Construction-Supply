<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportSchedulingTest extends TestCase
{
    public function test_daily_report_schedule_runs_at_5_pm(): void
    {
        $kernelFile = base_path('app/Console/Kernel.php');
        $contents = file_get_contents($kernelFile);

        $this->assertStringContainsString("dailyAt('17:00')", $contents);
    }
}
