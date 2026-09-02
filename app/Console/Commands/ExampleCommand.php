<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;

class ExampleCommand extends Command
{
    protected $signature = 'inspire';

    public function handle(): void
    {
        $this->info(Inspiring::quote());
    }
}
