<?php

namespace Salvon;

use Override;
use Exception;
use Salvon\Package\SalvonLoader;

class Salvon extends SalvonLoader
{
    /**
     * @throws Exception
     */
    #[Override]
    public function boot(): void
    {
        $this->initialize();
        $this->registerBundles();
    }
}
