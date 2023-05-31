<?php
declare(strict_types=1);

namespace Autoframe\InterfaceToConcrete;

use Autoframe\Components\Exception\AfrException;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;
use Autoframe\ClassDependency\AfrClassDependency;

use function array_merge;
use function realpath;
use function print_r;
use function base_convert;
use function md5;
use function serialize;

/**
 * This will make a configuration object that contains the paths to be wired:
 *
 * $oAfrConfigWiredPaths = new AfrConfigWiredPaths(['src','vendor']);
 * AfrMultiClassMapper::setAfrConfigWiredPaths($oAfrConfigWiredPaths);
 * AfrMultiClassMapper::xetRegenerateAll(true/false);
 * register_shutdown_function(function(){ print_r(AfrClassDependency::getDependencyInfo());});
 * $aMaps = AfrMultiClassMapper::getInterfaceToConcrete();
 */
class AfrInterfaceToConcreteClass implements AfrInterfaceToConcreteInterface
{
    /** @var array Paths to cache */
    protected array $aPaths = [];
    /** @var string Path hash */
    protected string $sHash;
    /** @var int The amount of seconds between the checks for Auto-loaded files outside the vendor dir */
    protected int $iAutoWireCacheExpireSeconds;

    protected bool $bForceRegenerateAllButVendor;

    public const VendorPrefix = '1Ve_'; //Vendor prefix
    public const AutoloadPrefix = '2Al_'; //Auto-loaded composer
    public const ExtraPrefix = '3Ex_'; //extra dirs

    /**
     * @param array $aExtraPaths
     * @param int $iAutoWireCacheExpireSeconds
     * @param bool $bForceRegenerateAllButVendor
     * @throws AfrInterfaceToConcreteException
     * @throws AfrException
     */
    public function __construct(
        array $aExtraPaths = [],
        int   $iAutoWireCacheExpireSeconds = 3600 * 24 * 365 * 2,
        bool  $bForceRegenerateAllButVendor = false
    )
    {
        $this->iAutoWireCacheExpireSeconds = max(60, abs($iAutoWireCacheExpireSeconds));
        $this->bForceRegenerateAllButVendor = $bForceRegenerateAllButVendor;

        $aPaths = [self::VendorPrefix => [], self::AutoloadPrefix => [], self::ExtraPrefix => [],];
        $this->applyExtraPrefix($aExtraPaths, $aPaths);
        $this->applyVendorPrefix($aPaths);
        $this->applyAutoloadPrefix($aPaths);

        foreach ($aPaths as $sPrefix => $aPathItem) {
            foreach ($aPathItem as $sPath) {
                if (isset($this->aPaths[$sPath])) {
                    continue;
                }
                $this->aPaths[$sPath] = $sPrefix . $this->hashV($sPath);
            }
        }
        $this->sHash = 'C_' . $this->hashV(serialize(array_merge(
                $this->aPaths,
                AfrClassDependency::setSkipClassInfo([], true),
                AfrClassDependency::setSkipNamespaceInfo([], true)
            )));

    }

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public function getClassInterfaceToConcrete(): array
    {
        AfrMultiClassMapper::setAfrConfigWiredPaths($this);
        return AfrMultiClassMapper::getInterfaceToConcrete();
    }

    /**
     * @param string $s
     * @return string
     */
    private function hashV(string $s): string
    {
        return substr(base_convert(md5($s), 16, 32), 0, 5);
    }

    /**
     * @return array
     */
    public function getPaths(): array
    {
        return $this->aPaths;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->sHash;
    }

    /**
     * @return int
     */
    public function getCacheExpire(): int
    {
        return $this->iAutoWireCacheExpireSeconds;
    }

    /**
     * @return bool
     */
    public function getForceRegenerateAllButVendor(): bool
    {
        return $this->bForceRegenerateAllButVendor;
    }

    /**
     * @param array $aExtraPaths
     * @param array $aPaths
     * @return void
     * @throws AfrInterfaceToConcreteException
     */
    private function applyExtraPrefix(array $aExtraPaths, array &$aPaths): void
    {
        foreach ($aExtraPaths as $sPath) {
            $sPath = (string)$sPath;
            if (strlen($sPath) < 1) {
                continue;
            }
            $sPath = realpath($sPath);
            if ($sPath === false) {
                throw new AfrInterfaceToConcreteException(
                    'Invalid paths for ' . __CLASS__ . '->' . __FUNCTION__ . '->' . print_r($aExtraPaths, true)
                );
            }
            $aPaths[self::ExtraPrefix][] = $sPath;
        }
    }

    /**
     * @param array $aPaths
     * @return void
     * @throws AfrInterfaceToConcreteException
     */
    private function applyVendorPrefix(array &$aPaths): void
    {
        $aPaths[self::VendorPrefix] = [AfrVendorPath::getVendorPath()];
        if (empty($aPaths[self::VendorPrefix])) {
            throw new AfrInterfaceToConcreteException(
                'Composer vendor path not found ' . __CLASS__ . '->' . __FUNCTION__);
        }
    }

    /**
     * @param array $aPaths
     * @return void
     */
    private function applyAutoloadPrefix(array &$aPaths): void
    {
        $aPaths[self::AutoloadPrefix] = [];
        foreach (AfrVendorPath::getComposerAutoloadX(false)['autoload'] as $sType => $mixed) {
            if ($sType === 'psr4' || $sType === 'psr0') {
                foreach ($mixed as $aPsr) {
                    if (!is_array($aPsr)) {
                        continue;
                    }
                    $aPaths[self::AutoloadPrefix] = array_merge($aPaths[self::AutoloadPrefix], $aPsr);
                }
            }
            //if ($sType === 'classmap') {}
        }
    }
}