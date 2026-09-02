<?php

namespace Salvon\Package;

use Override;
use Salvon\Service\Instance;
use Salvon\Contract\PackageRegistrar;
use Illuminate\Support\ServiceProvider;

class SalvonRegistrar extends ServiceProvider implements PackageRegistrar
{
    #[Override]
    public function initialize(): void
    {
        $this->autoload();
    }

    private function autoload(): void
    {
        $dir = Instance::directory($this);
        $this->loadMigrationsFrom($dir . '/Database/Migrations');

        $routePath = $dir . '/routes/routes.php';

        if (file_exists($routePath)) {
            $this->loadRoutesFrom($routePath);
        }
    }
}
