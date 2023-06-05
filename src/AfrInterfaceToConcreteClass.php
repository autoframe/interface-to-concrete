<?php
declare(strict_types=1);

namespace Autoframe\InterfaceToConcrete;

use Autoframe\ClassDependency\AfrClassDependencyException;
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
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
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
    protected bool $bSilenceErrors;
    protected array $aClassInterfaceToConcrete;


    /**
     * @param array $aExtraPaths
     * @param int $iAutoWireCacheExpireSeconds
     * @param bool $bForceRegenerateAllButVendor
     * @param bool $bSilenceErrors
     * @throws AfrException
     * @throws AfrInterfaceToConcreteException
     */
    public function __construct(
        array $aExtraPaths = [],
        int   $iAutoWireCacheExpireSeconds = 3600 * 24 * 365 * 2,
        bool  $bForceRegenerateAllButVendor = false,
        bool  $bSilenceErrors = false
    )
    {
        $this->iAutoWireCacheExpireSeconds = max(60, abs($iAutoWireCacheExpireSeconds));
        $this->bForceRegenerateAllButVendor = $bForceRegenerateAllButVendor;
        $this->bSilenceErrors = $bSilenceErrors;

        $aPaths = [AfrMultiClassMapper::VendorPrefix => [], AfrMultiClassMapper::AutoloadPrefix => [], AfrMultiClassMapper::ExtraPrefix => [],];
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
     * @throws AfrClassDependencyException
     */
    public function getClassInterfaceToConcrete(): array
    {
        if(!isset($this->aClassInterfaceToConcrete)){

            AfrClassDependency::setSkipClassInfo([
                'ArrayAccess',
                'BadFunctionCallException',
                'BadMethodCallException',
                'Countable',
                'Exception',
                'Iterator',
                'IteratorAggregate',
                'IteratorIterator',
                'InvalidArgumentException',
                'JsonSerializable',
                'LogicException',
                'OuterIterator',
                'ReflectionException',
                'RuntimeException',
                'Serializable',
                'SplFileInfo',
                'Stringable',
                'Throwable',
                'Traversable',
                'Throwable',
            ],true);

            AfrClassDependency::setSkipNamespaceInfo([
                'PHPUnit\\',
                'PharIo\\',
                'SebastianBergmann\\',
                'TheSeer\\',
                'phpDocumentor\\',
                'Webmozart\\',
                'Symfony\\',
                'Doctrine\\',
                'Composer\\',
                'Assert\\',
                'Cose\\',
                'DeepCopy\\',
                'FG\\',
                'PHPStan\\',
                'ParagonIE\\',
                'PhpParser\\',
                'Prophecy\\',
            ],true);

            AfrMultiClassMapper::setAfrConfigWiredPaths($this);
            $this->aClassInterfaceToConcrete =  AfrMultiClassMapper::getInterfaceToConcrete();
            AfrMultiClassMapper::flush(); //clean memory
            AfrClassDependency::flush(); //clean memory
        }


        return $this->aClassInterfaceToConcrete;
    }

    /**
     * @param string $s
     * @return string
     */
    protected function hashV(string $s): string
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
     * @return bool
     */
    public function getSilenceErrors(): bool
    {
        return $this->bSilenceErrors;
    }

    /**
     * @param array $aExtraPaths
     * @param array $aPaths
     * @return void
     * @throws AfrInterfaceToConcreteException
     */
    protected function applyExtraPrefix(array $aExtraPaths, array &$aPaths): void
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
            $aPaths[AfrMultiClassMapper::ExtraPrefix][] = $sPath;
        }
    }

    /**
     * @param array $aPaths
     * @return void
     * @throws AfrInterfaceToConcreteException
     */
    protected function applyVendorPrefix(array &$aPaths): void
    {
        $aPaths[AfrMultiClassMapper::VendorPrefix] = [AfrVendorPath::getVendorPath()];
        if (empty($aPaths[AfrMultiClassMapper::VendorPrefix])) {
            throw new AfrInterfaceToConcreteException(
                'Composer vendor path not found ' . __CLASS__ . '->' . __FUNCTION__);
        }
    }

    /**
     * @param array $aPaths
     * @return void
     */
    protected function applyAutoloadPrefix(array &$aPaths): void
    {
        $aPaths[AfrMultiClassMapper::AutoloadPrefix] = [];
        foreach (AfrVendorPath::getComposerAutoloadX(false)['autoload'] as $sType => $mixed) {
            if ($sType === 'psr4' || $sType === 'psr0') {
                foreach ($mixed as $aPsr) {
                    if (!is_array($aPsr)) {
                        continue;
                    }
                    $aPaths[AfrMultiClassMapper::AutoloadPrefix] =
                        array_merge($aPaths[AfrMultiClassMapper::AutoloadPrefix], $aPsr);
                }
            }
            //if ($sType === 'classmap') {}
        }
    }
}