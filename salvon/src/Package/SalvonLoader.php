<?php

namespace Salvon\Package;

use Exception;
use Salvon\Facade\Error;
use Salvon\Service\Instance;
use Salvon\Contract\BootablePackage;
use Salvon\Contract\PackageRegistrar;
use Illuminate\Support\Facades\Config;

abstract class SalvonLoader extends SalvonRegistrar implements BootablePackage
{
    /**
     * @throws Exception
     */
    protected function registerBundles(): void
    {
        $app = $this->app;
        $packages = $this->bundles();

        each($packages, static function (string $packageClass) use ($app): void {
            if (!Instance::of($packageClass, PackageRegistrar::class)) {
                Error::ise(sprintf('Salvon package %s must implement %s.', $packageClass, PackageRegistrar::class));
            }

            if (Instance::of($packageClass, BootablePackage::class)) {
                Error::ise(sprintf("Salvon bootable package %s can't be used as registrar package.", $packageClass));
            }

            /** @var PackageRegistrar $package */
            $package = new $packageClass($app);
            $package->initialize();
        });
    }

    private function bundles(): array
    {
        if (Config::get('salvon.framework.discovery_bundles') === true) {
            return $this->discoveryBundles();
        }

        return Config::get('salvon.framework.bundles', []);
    }

    private function discoveryBundles(): array
    {
        $baseClassPrefix = 'Salvon\\Bundle';
        $packagesLocations = glob(__DIR__ . '/../../bundles/*', GLOB_ONLYDIR);
        $packages = [];

        foreach ($packagesLocations as $packagePath) {
            $packagePrefix = basename($packagePath);
            $packages[] = sprintf("%s\%s\%s", $baseClassPrefix, $packagePrefix, $packagePrefix);
        }

        return $packages;
    }
}
