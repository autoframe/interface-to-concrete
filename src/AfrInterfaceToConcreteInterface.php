<?php
declare(strict_types=1);

namespace Autoframe\InterfaceToConcrete;


use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;

/**
 * This will make a configuration object that contains the paths to be wired:
 *
 * $oAfrConfigWiredPaths = new AfrConfigWiredPaths(['src','vendor']);
 * AfrMultiClassMapper::setAfrConfigWiredPaths($oAfrConfigWiredPaths);
 * AfrMultiClassMapper::xetRegenerateAll(true/false);
 * register_shutdown_function(function(){ print_r(AfrClassDependency::getDependencyInfo());});
 * $aMaps = AfrMultiClassMapper::getInterfaceToConcrete();
 */
interface AfrInterfaceToConcreteInterface
{
    /**
     * @param array $aExtraPaths
     * @param int $iAutoWireCacheExpireSeconds
     * @param bool $bForceRegenerateAllButVendor
     * @throws AfrInterfaceToConcreteException
     */
    public function __construct(array $aExtraPaths = [], int $iAutoWireCacheExpireSeconds = 3600 * 24 * 365 * 2, bool $bForceRegenerateAllButVendor = false);

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public function getClassInterfaceToConcrete(): array;

    /**
     * @return array
     */
    public function getPaths(): array;

    /**
     * @return string
     */
    public function getHash(): string;

    /**
     * @return int
     */
    public function getCacheExpire(): int;

    /**
     * @return bool
     */
    public function getForceRegenerateAllButVendor(): bool;
}