<?php
declare(strict_types=1);

namespace Autoframe\InterfaceToConcrete;

use Autoframe\ClassDependency\AfrClassDependencyException;
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
 * $oAfrConfigWiredPaths = new AfrInterfaceToConcreteClass(
 *  $sEnv, //'DEV'/ 'PRODUCTION'/ 'STAGING'/ 'DEBUG'
 * $aEnvSettings [], //overwrite profile settings
 * $aExtraPaths = [] //all compose paths are covered
 * );
 * $oAfrConfigWiredPaths->getClassInterfaceToConcrete();
 *
 * STATIC CALL AFTER INSTANTIATING:  AfrInterfaceToConcreteClass::$oInstance->getClassInterfaceToConcrete();
 *
 */
class AfrInterfaceToConcreteClass implements AfrInterfaceToConcreteInterface
{
    /** @var array Paths to cache */
    protected array $aPaths = [];
    protected array $aClassInterfaceToConcrete;
    protected array $aEnvSettings;
    public static AfrInterfaceToConcreteInterface $oLatestInstance;
    protected ?AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies;

    /**
     * @param string $sEnv
     * @param array $aEnvSettings
     * @param array $aExtraPaths
     * @throws AfrInterfaceToConcreteException
     */
    public function __construct(
        string $sEnv,
        array  $aEnvSettings = [],
        array  $aExtraPaths = []
    )
    {
        $this->setEnvSettings($sEnv, $aEnvSettings);
        $aPaths = [
            AfrMultiClassMapper::VendorPrefix => [],
            AfrMultiClassMapper::AutoloadPrefix => [],
            AfrMultiClassMapper::ExtraPrefix => [],
        ];
        $this->applyExtraPrefix($aExtraPaths, $aPaths);
        $this->applyVendorPrefix($aPaths);
        $this->applyAutoloadPrefix($aPaths);

        foreach ($aPaths as $sPrefix => $aPathItem) {
            foreach ($aPathItem as $sPath) {
                if (isset($this->aPaths[$sPath])) {
                    continue;
                }
                $this->aPaths[$sPath] = $sPrefix . $this->hashV(serialize([
                        $sPath,
                        $this->aEnvSettings[AfrMultiClassMapper::DumpPhpFilePathAndMtime],
                        $this->aEnvSettings[AfrMultiClassMapper::RegexExcludeFqcnsAndPaths],
                    ]));
            }
        }
        self::$oLatestInstance = $this;
    }

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     * @throws AfrClassDependencyException
     */
    public function getClassInterfaceToConcrete(): array
    {
        if (!isset($this->aClassInterfaceToConcrete)) {
            $aSaveSkipClassInfo = $aSaveSkipNamespaceInfo = [];
            if ($this->aEnvSettings[AfrMultiClassMapper::ClassDependencyRestoreSkipped]) {
                $aSaveSkipClassInfo = AfrClassDependency::getSkipClassInfo();
                $aSaveSkipNamespaceInfo = AfrClassDependency::getSkipNamespaceInfo();
            }
            AfrClassDependency::flush();
            AfrClassDependency::setSkipClassInfo($this->aEnvSettings[AfrMultiClassMapper::ClassDependencySetSkipClassInfo]);
            AfrClassDependency::setSkipNamespaceInfo($this->aEnvSettings[AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo]);

            AfrMultiClassMapper::setAfrConfigWiredPaths($this);
            $this->aClassInterfaceToConcrete = AfrMultiClassMapper::getInterfaceToConcrete();

            if ($this->aEnvSettings[AfrMultiClassMapper::MultiClassMapperFlush]) {
                AfrMultiClassMapper::flush(); //clean memory
            }
            if ($this->aEnvSettings[AfrMultiClassMapper::ClassDependencyFlush]) {
                AfrClassDependency::flush(); //clean memory
            }
            if ($this->aEnvSettings[AfrMultiClassMapper::ClassDependencyRestoreSkipped]) {
                AfrClassDependency::setSkipClassInfo($aSaveSkipClassInfo);
                AfrClassDependency::setSkipNamespaceInfo($aSaveSkipNamespaceInfo);
            }

        }


        return $this->aClassInterfaceToConcrete;
    }

    /**
     * @return AfrInterfaceToConcreteInterface|null
     */
    public static function getLatestInstance(): ?AfrInterfaceToConcreteInterface
    {
        if (!empty(self::$oLatestInstance)) {
            return self::$oLatestInstance;
        }
        return null;
    }


    /**
     * @param string $sEnv
     * @param array $aOverwrite
     * @throws AfrInterfaceToConcreteException
     */
    protected function setEnvSettings(string $sEnv, array $aOverwrite = [])
    {
        $aEnv = ['DEV', 'PRODUCTION', 'STAGING', 'DEBUG'];
        $sEnv = strtoupper($sEnv);
        if (!in_array($sEnv, $aEnv)) {
            throw new AfrInterfaceToConcreteException(
                __FUNCTION__ . ' for ' . get_class($this) . ' must be: ' . implode(' / ', $aEnv)
            );
        }

        $aEnvSettings = [
            //time between changes checks. if something changed, then the cache is recalculated
            AfrMultiClassMapper::CacheExpireSeconds => 3600 * 24 * 365 * 2,

            //all php sources except the vendor because there we check vendor/composer/install.json timestamp
            AfrMultiClassMapper::ForceRegenerateAllButVendor => false,

            //ob_start is used and redirects / cli handling
            AfrMultiClassMapper::SilenceErrors => false,

            //exclude folder and file paths and namespaces in all checks
            //eg. ['@src.{1,}Exception@','@PHPUnit.{1,}Telemetry@']
            AfrMultiClassMapper::RegexExcludeFqcnsAndPaths => [],

            // full path: /server/cacheDir
            // overwrite here or auto set by AfrMultiClassMapper::getCacheDir()
            // realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
            AfrMultiClassMapper::CacheDir => realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache',

            // Clean memory after job or keep AfrMultiClassMapper::$aNsClassMergedFromPathMap
            // and access the raw data using AfrMultiClassMapper::getAllNsClassFilesMap()
            AfrMultiClassMapper::MultiClassMapperFlush => true,

            //flush after usage and clear memory or kep all for debug
            AfrMultiClassMapper::ClassDependencyFlush => true,

            //restore AfrMultiClassMapper skip info after flush
            AfrMultiClassMapper::ClassDependencyRestoreSkipped => true,

            //this will consume more space inside cache dir and memory to process
            AfrMultiClassMapper::DumpPhpFilePathAndMtime => false,

            //pointless in my application type, but it depends on what you do
            AfrMultiClassMapper::ClassDependencySetSkipClassInfo => [
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
            ],
            AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo => [
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
            ],
        ];

        if ($sEnv === 'DEV') {
            $aEnvSettings = array_merge($aEnvSettings, [
                AfrMultiClassMapper::CacheExpireSeconds => 60,
            ]);
        } elseif ($sEnv === 'DEBUG') {
            $aEnvSettings = array_merge($aEnvSettings, [
                AfrMultiClassMapper::CacheExpireSeconds => 15,
                AfrMultiClassMapper::ForceRegenerateAllButVendor => true,
                AfrMultiClassMapper::SilenceErrors => false,
                AfrMultiClassMapper::RegexExcludeFqcnsAndPaths => [],
                AfrMultiClassMapper::MultiClassMapperFlush => false,
                AfrMultiClassMapper::ClassDependencyFlush => false,
                AfrMultiClassMapper::ClassDependencyRestoreSkipped => false,
                AfrMultiClassMapper::DumpPhpFilePathAndMtime => true,
                AfrMultiClassMapper::ClassDependencySetSkipClassInfo => [],
                AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo => [],
            ]);
        } else { // PRODUCTION
            $aEnvSettings = array_merge($aEnvSettings, [
                AfrMultiClassMapper::SilenceErrors => true,
            ]);
        }
        foreach ($aEnvSettings as $sKey => $mValue) {
            if (isset($aOverwrite[$sKey])) {
                $sType = substr($sKey, 0, 2);
                $sErr = 'EnvSettings[' . $sKey . '] was given as ' . gettype($aOverwrite[$sKey]) . ' in stead of ';
                if ($sType === '$i') {
                    if (!is_int($aOverwrite[$sKey])) {
                        throw new AfrInterfaceToConcreteException($sErr . ' integer');
                    }
                    $aEnvSettings[$sKey] = max(15, abs($aOverwrite[$sKey]));
                } elseif ($sType === '$b') {
                    if (!is_bool($aOverwrite[$sKey])) {
                        throw new AfrInterfaceToConcreteException($sErr . ' boolean');
                    }
                    $aEnvSettings[$sKey] = $aOverwrite[$sKey];
                } elseif ($sType === '$a') {
                    if (!is_array($aOverwrite[$sKey])) {
                        throw new AfrInterfaceToConcreteException($sErr . ' array');
                    }
                    $aEnvSettings[$sKey] = $aOverwrite[$sKey];
                } elseif ($sType === '$s') {
                    if (!is_string($aOverwrite[$sKey])) {
                        throw new AfrInterfaceToConcreteException($sErr . ' string');
                    }
                    $aEnvSettings[$sKey] = $aOverwrite[$sKey];
                } else {
                    throw new AfrInterfaceToConcreteException('EnvSettings[' . $sKey . '] unknown format');
                }
            }
        }
        $this->aEnvSettings = $aEnvSettings;
    }

    /**
     * @return array
     */
    public function getEnvSettings(): array
    {
        return $this->aEnvSettings;
    }

    /**
     * @param string $s
     * @return string
     */
    public function hashV(string $s): string
    {
        return substr(base_convert(md5($s), 16, 32), 0, 6);
    }

    /**
     * @return array
     */
    public function getPaths(): array
    {
        return $this->aPaths;
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
        foreach (AfrVendorPath::getComposerAutoloadX()['autoload'] as $sType => $mixed) {
            if ($sType === 'psr4' || $sType === 'psr0') {
                foreach ($mixed as $aPsr) {
                    if (!is_array($aPsr)) {
                        continue;
                    }
                    $aPaths[AfrMultiClassMapper::AutoloadPrefix] =
                        array_merge($aPaths[AfrMultiClassMapper::AutoloadPrefix], $aPsr);
                }
            }
            //if ($sType === 'classmap') {} // classmap covered under AfrMultiClassMapper::VendorPrefix
        }
    }

    /**
     * @return AfrToConcreteStrategiesInterface
     */
    public function getAfrToConcreteStrategies(): AfrToConcreteStrategiesInterface
    {
        //use default concrete
        if (empty($this->oAfrToConcreteStrategies)) {
            $this->oAfrToConcreteStrategies = AfrToConcreteStrategiesClass::getLatestInstance();
        }
        return $this->oAfrToConcreteStrategies;
    }

    /**
     * @param AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies
     * @return AfrToConcreteStrategiesInterface
     */
    public function setAfrToConcreteStrategies(
        AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies
    ): AfrToConcreteStrategiesInterface
    {
        return $this->oAfrToConcreteStrategies = $oAfrToConcreteStrategies;
    }

    /**
     * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @param string $sNotConcreteFQCN
     * @param string|null $sTemporaryContextOverwrite
     * @param string|null $sTemporaryPriorityRuleOverwrite
     * @return string
     * @throws AfrClassDependencyException
     * @throws AfrInterfaceToConcreteException
     */
    public function resolve(
        string $sNotConcreteFQCN,
        string $sTemporaryContextOverwrite = null,
        string $sTemporaryPriorityRuleOverwrite = null
    ): string
    {
        if ($sTemporaryContextOverwrite !== null) {
            $sBackupContext = $this->getAfrToConcreteStrategies()->getContext();
            $this->getAfrToConcreteStrategies()->setContext($sTemporaryContextOverwrite);
        }

        if ($sTemporaryPriorityRuleOverwrite !== null) {
            $sBackupPriorityRule = $this->getAfrToConcreteStrategies()->getPriorityRule();
            $this->getAfrToConcreteStrategies()->setPriorityRule($sTemporaryPriorityRuleOverwrite);
        }

        if (!isset($this->aClassInterfaceToConcrete)) {
            $this->getClassInterfaceToConcrete(); //init map
        }
        $aMappings =
            !empty($this->aClassInterfaceToConcrete[$sNotConcreteFQCN]) &&
            is_array($this->aClassInterfaceToConcrete[$sNotConcreteFQCN]) ?
                $this->aClassInterfaceToConcrete[$sNotConcreteFQCN] : [];

        $sResoled =
            $this->getAfrToConcreteStrategies()->
            setContext($sTemporaryContextOverwrite)->
            resolveMap($aMappings, $sNotConcreteFQCN);

        //restore
        if ($sTemporaryContextOverwrite !== null) {
            $this->getAfrToConcreteStrategies()->setContext($sBackupContext);
        }
        if ($sTemporaryPriorityRuleOverwrite !== null) {
            $this->getAfrToConcreteStrategies()->setPriorityRule($sBackupPriorityRule);
        }
        return $sResoled;
    }


}