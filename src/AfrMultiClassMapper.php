<?php
declare(strict_types=1);

namespace Autoframe\InterfaceToConcrete;

use Autoframe\Components\Arr\Export\AfrArrExportArrayAsStringClass;
use Autoframe\Components\FileSystem\OverWrite\AfrOverWriteClass;
use Autoframe\ClassDependency\AfrClassDependency;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;


/**
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
 * Magic :)
 */
class AfrMultiClassMapper
{
    public const VendorPrefix = '1Ve_'; //Vendor prefix
    public const AutoloadPrefix = '2Al_'; //Auto-loaded composer
    public const ExtraPrefix = '3Ex_'; //extra dirs
    public const ConcretePrefix = 'C_'; //class map

    public const CacheExpireSeconds = '$i_AfrMultiClassMapper::iCacheExpireSeconds';
    public const ForceRegenerateAllButVendor = '$b_AfrMultiClassMapper::bRegenerateAllFlagButVendor';
    public const SilenceErrors = '$b_AfrMultiClassMapper::bSilenceErrors';
    public const RegexExcludeFqcnsAndPaths = '$a_RegexExcludeFqcnsAndPaths';
    public const CacheDir = '$s_AfrMultiClassMapper::sCacheDir';
    public const MultiClassMapperFlush = '$b_AfrMultiClassMapper::flush';
    public const ClassDependencyFlush = '$b_AfrClassDependency::flush';
    public const ClassDependencyRestoreSkipped = '$b_AfrClassDependencyRestoreSkip';
    public const ClassDependencySetSkipClassInfo = '$a_AfrClassDependency::setSkipClassInfo';
    public const ClassDependencySetSkipNamespaceInfo = '$a_AfrClassDependency::setSkipNamespaceInfo';
    public const DumpPhpFilePathAndMtime = '$b_AfrVendorPath::getVendorPath';

    protected static ?AfrInterfaceToConcreteInterface $oWiringPaths; // what to wire
    protected static string $sCacheDir; //local cache dir

    protected static bool $bForceRegenerateAllButVendor = false; //force regenerate for caches
    protected static bool $bSilenceErrors = false;

    protected static array $aRegeneratedByBuildNewNsClassFilesMap = []; //regeneration report

    protected static bool $bClassTreeInfoCacheWriteDone = true; //infinite loop prevention
    protected static array $aNsClassMergedFromPathMap = []; //temp internal work cache

    /**
     * @param AfrInterfaceToConcreteInterface $oWiringPaths
     * @return void
     * @throws AfrInterfaceToConcreteException
     */
    public static function setAfrConfigWiredPaths(AfrInterfaceToConcreteInterface $oWiringPaths): void
    {
        //allow for multiple calls of AfrInterfaceToConcreteInterface->getClassInterfaceToConcrete
        if (!isset(self::$oWiringPaths) || self::$oWiringPaths !== $oWiringPaths) {
            self::$oWiringPaths = $oWiringPaths;
            self::$bForceRegenerateAllButVendor = $oWiringPaths->getEnvSettings()[self::ForceRegenerateAllButVendor];
            self::$bSilenceErrors = $oWiringPaths->getEnvSettings()[self::SilenceErrors];
            self::$aNsClassMergedFromPathMap = self::$aRegeneratedByBuildNewNsClassFilesMap = [];

            if(!empty($oWiringPaths->getEnvSettings()[self::CacheDir])){
                self::$sCacheDir = realpath($oWiringPaths->getEnvSettings()[self::CacheDir]);
            }
            if (empty(self::$sCacheDir)) { //fallback
                self::$sCacheDir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
            }
            if (!is_dir(self::$sCacheDir)) {
                throw new AfrInterfaceToConcreteException('Dir not found ' . __CLASS__ . ': ' . self::$sCacheDir);
            }
            if (!is_file(self::$sCacheDir . DIRECTORY_SEPARATOR . '.gitignore')) {
                file_put_contents(self::$sCacheDir . DIRECTORY_SEPARATOR . '.gitignore', "*.php\n*CheckTs\n");
            }
        }
    }

    /**
     * Cleanup
     * @return void
     */
    public static function flush(): void
    {
        self::$oWiringPaths = null;
        self::$bClassTreeInfoCacheWriteDone = true;
        self::$aNsClassMergedFromPathMap = self::$aRegeneratedByBuildNewNsClassFilesMap = [];
    }

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public static function getInterfaceToConcrete(): array
    {
        self::getAllNsClassFilesMap();
        if (self::$aRegeneratedByBuildNewNsClassFilesMap || !is_file(self::getInterfaceToConcretePath())) { //we have new or modified php classes
            self::$bClassTreeInfoCacheWriteDone = false; //set start to regenerate flag
            if (self::$bSilenceErrors) {
                ob_start();
            }
            $aWrittenToInterfaceToConcretePath = self::classInterfaceToConcreteWrite();
            if (self::$bSilenceErrors) {
                ob_end_clean();
            }
            return $aWrittenToInterfaceToConcretePath; //fast return without including a file from the drive
        }
        if (!is_file(self::getInterfaceToConcretePath())) {
            throw new AfrInterfaceToConcreteException(__CLASS__ . ' can not resolve ' . __FUNCTION__);
        }
        return (array)(include(self::getInterfaceToConcretePath()));

    }

    /**
     * TRY TO STEP OVER MAX ONE FATAL ERROR!
     * This might happen during a long composer update or when writing code on dev
     * We mark the corrupted class as failed and continue with the mapping
     * @return void
     * @throws AfrInterfaceToConcreteException
     */
    protected static function handleFatalFail(): void
    {
        register_shutdown_function(function () {
            if (AfrClassDependency::getDebugFatalError()) {

                self::overWrite(self::getFailedClassPermanentSkipFile(), array_merge(
                    self::getClassPermanentSkipClasses(),
                    AfrClassDependency::getDebugFatalError()
                ));
                $bIsCli = http_response_code() === false;

                //cli is nor recoverable so far
                //TODO: try to spawn a cli worker having the same argv
                if (!self::$bSilenceErrors || $bIsCli) {
                    //echo ob_get_contents();
                    //ob_end_clean();
                    echo PHP_EOL . 'Corrupted classes: ' .
                        implode('; ', array_keys(AfrClassDependency::getDebugFatalError())) .
                        PHP_EOL;
                }
                //try to recover the http initial request
                if (self::$bSilenceErrors && !$bIsCli) { // not cli
                    sleep(2); //server needs to recover in case of high load
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        http_response_code(307);
                        header('Location: ' . $_SERVER['REQUEST_URI']);
                    } else {
                        //TODO: using curl, try to resend the original request
                    }
                }
            }
            self::classInterfaceToConcreteWrite(); //try to continue
        });
    }

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public static function classInterfaceToConcreteWrite(): array
    {
        if (!self::$bClassTreeInfoCacheWriteDone) {
            //TRY TO STEP OVER MAX ONE FATAL ERROR!
            //This might happen during a long composer update or when writing code on dev
            self::handleFatalFail();

            $aToSkip = self::getClassPermanentSkipClasses();
            foreach (self::$aNsClassMergedFromPathMap as $sNsCl => $sClassPath) {
                if (isset($aToSkip[$sNsCl])) {
                    //Corrupted class! This will not be used in the "to concrete process"
                    if (!self::$bSilenceErrors) {
                        trigger_error('The class is marked as corrupted! Fix it and remove from ' .
                            self::getFailedClassPermanentSkipFile(), E_USER_WARNING);
                    }
                    continue;
                }
                AfrClassDependency::getClassInfo($sNsCl);
            }
            $aClassInterfaceToConcreteMap = self::classInterfaceToConcreteMap();
            self::$bClassTreeInfoCacheWriteDone = true; //finally done
            self::$aRegeneratedByBuildNewNsClassFilesMap = []; //cleanup generation flags
            if (!self::overWrite(self::getInterfaceToConcretePath(), $aClassInterfaceToConcreteMap)) {
                throw new AfrInterfaceToConcreteException(
                    'File is not writable: ' . self::getInterfaceToConcretePath()
                );
            }
            return $aClassInterfaceToConcreteMap;
        }
        return [];

    }


    /**
     * @param string $sPath
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public static function getSingleNsClassFilesMap(string $sPath): array
    {
        if (!isset(self::$oWiringPaths->getPaths()[$sPath])) {
            throw new AfrInterfaceToConcreteException('First set the path using ' . __CLASS__ . '::setAfrConfigWiredPaths(AfrInterfaceToConcreteInterface)');
        }
        if (self::checkForRegenerationFlag($sPath)) {
            return self::buildNewNsClassFilesMap($sPath);
        }
        return (array)(include self::getNsClassFilesMapPath($sPath));

    }

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public static function getAllNsClassFilesMap(): array
    {
        if (empty(self::$oWiringPaths)) {
            throw new AfrInterfaceToConcreteException('Run first ' . __CLASS__ . '::setAfrConfigWiredPaths(AfrInterfaceToConcreteInterface)');
        }
        if (!empty(self::$aNsClassMergedFromPathMap)) {
            return self::$aNsClassMergedFromPathMap;
        }
        foreach (self::$oWiringPaths->getPaths() as $sPath => $sDirHash) {
            self::$aNsClassMergedFromPathMap = array_merge(
                self::$aNsClassMergedFromPathMap,
                self::getSingleNsClassFilesMap($sPath)
            );
        }
        return self::$aNsClassMergedFromPathMap;
    }

    /**
     * @param string $sPath
     * @return bool
     * @throws AfrInterfaceToConcreteException
     */
    protected static function checkForRegenerationFlag(string $sPath): bool
    {
        $sNsClassFilesMapPath = self::getNsClassFilesMapPath($sPath);

        //FIRST RUN:
        if (!is_file($sNsClassFilesMapPath)) {
            return true;
        }
        $iCacheFileMtime = (int)filemtime($sNsClassFilesMapPath);
        //VENDOR
        if (AfrVendorPath::getVendorPath() === $sPath) {
            // wait at least 2 seconds in the case of a complex composer run
            $iCTS = AfrVendorPath::getComposerTs();
            $bComposerUpdated = $iCTS > $iCacheFileMtime && $iCTS + 2 < time();
            if ($bComposerUpdated) {
                //propagate composer change to all because there might be new classes / interfaces in the packages!
                self::$bForceRegenerateAllButVendor = true;
            }
            return $bComposerUpdated;
        }

        //OTHER PATHS
        if (self::$bForceRegenerateAllButVendor) {
            return true;
        }

        $iCacheExpire = self::$oWiringPaths->getEnvSettings()[self::CacheExpireSeconds];
        if (time() > $iCacheFileMtime + $iCacheExpire) {

            //old local cache file, so we rescan the system:
            $sLastCheckFilePath = self::getNsClassFilesMapPathLastCheckTs($sPath);
            if (!is_file($sLastCheckFilePath)) {
                return true;
            }
            if (time() > filemtime($sLastCheckFilePath) + $iCacheExpire) {
                $iTsMaxFileSystem = self::getMaxDirTs($sPath);
                if ($iTsMaxFileSystem > $iCacheFileMtime) {
                    return true;
                } else {
                    // no file changes since last cache build
                    self::overWriteTs($sLastCheckFilePath);
                }
            }
        }
        return false;
    }

    /**
     * @return array
     */
    protected static function classInterfaceToConcreteMap(): array
    {
        $aOut = [];
        foreach (AfrClassDependency::getDependencyInfo() as $sFQCN => $oEntity) {
            if ($oEntity->isClass()) {
                foreach ($oEntity->getAllDependencies() as $sFQCN_Implementation => $v) {
                    $oDependency = AfrClassDependency::getClassInfo($sFQCN_Implementation);
                    if ($oDependency->isInterface()) {
                        $aOut[$sFQCN_Implementation][$sFQCN] = $oEntity->isInstantiable();
                    }
                }
            }
        }
        //resolve instantiable classes regardless of dependencies
        foreach (AfrClassDependency::getDependencyInfo() as $sFQCN => $oEntity) {
            if ($oEntity->isClass()) {
                if (empty($aOut[$sFQCN])) {
                    $aOut[$sFQCN] = $oEntity->isInstantiable();
                }
            }
        }

        ksort($aOut);
        return $aOut;
    }


    /**
     * @param string $sPath
     * @return array
     */
    protected static function buildNewNsClassFilesMap(string $sPath): array
    {
        $sVendorOrAutoload = '';
        if (AfrVendorPath::getVendorPath() === $sPath) {
            $sVendorOrAutoload = 'vendor';
        } elseif (substr($sPath, 0, strlen(self::AutoloadPrefix)) === self::AutoloadPrefix) {
            $sVendorOrAutoload = 'autoload';
        }

        if ($sVendorOrAutoload) {
            $aClasses = AfrVendorPath::getComposerAutoloadX()[$sVendorOrAutoload];
            $aClasses = array_merge(
                $aClasses['classmap'],
                AfrVendorPath::createMapFromPsrX($aClasses['psr4']),
                AfrVendorPath::createMapFromPsrX($aClasses['psr0']),
            );
        } else {
            $aClasses = AfrVendorPath::createMap($sPath);
        }

        $bDumpPhpFilePathAndMtime = self::$oWiringPaths->getEnvSettings()[self::DumpPhpFilePathAndMtime];
        $iShort = AfrVendorPath::getVendorPath() === $sPath ? 1 : 2;
        foreach ($aClasses as $sFQCN => &$sClassPath) {
            if (self::excludeRegEx($sClassPath) || self::excludeRegEx($sFQCN)) {
                unset($aClasses[$sFQCN]);
                continue;
            }
            $sClassPath = $bDumpPhpFilePathAndMtime ?
                ((string)@filemtime($sClassPath)) . '|' . $sClassPath :
                $iShort;
        }

        arsort($aClasses); //sort in order to have the latest timestamp first

        self::$aRegeneratedByBuildNewNsClassFilesMap[$sPath] =
            self::overWrite(self::getNsClassFilesMapPath($sPath), $aClasses);

        self::overWriteTs(self::getNsClassFilesMapPathLastCheckTs($sPath));

        return $aClasses;
    }


    /**
     * @param string $sPathTo
     * @param array $aData
     * @param int $iRetryMs
     * @param float $fDelta
     * @return bool
     */
    protected static function overWrite(string $sPathTo, array $aData, int $iRetryMs = 3000, float $fDelta = 2): bool
    {
        $sHeader = '<?php /* ' .gmdate('D, d M Y H:i:s') . ' GMT ->getEnvSettings: '.
            str_replace('*/', '* /', print_r(self::$oWiringPaths->getEnvSettings(), true)) .
            "*/ \n return ";
        return AfrOverWriteClass::getInstance()->overWriteFile(
            $sPathTo,
            $sHeader . AfrArrExportArrayAsStringClass::getInstance()->exportPhpArrayAsString($aData),
            $iRetryMs,
            $fDelta
        );
    }

    /**
     * @param string $sPathTo
     * @param int $iRetryMs
     * @param float $fDelta
     * @return bool
     */
    protected static function overWriteTs(string $sPathTo, int $iRetryMs = 3000, float $fDelta = 2): bool
    {
        return AfrOverWriteClass::getInstance()->overWriteFile(
            $sPathTo,
            gmdate('D, d M Y H:i:s') . ' GMT' . PHP_EOL . 'getEnvSettings: ' .
            print_r(self::$oWiringPaths->getEnvSettings(), true),
            $iRetryMs,
            $fDelta
        );
    }

    /**
     * @param string $sPath
     * @return string
     */
    protected static function getNsClassFilesMapPath(string $sPath): string
    {
        return self::getCacheDir() . DIRECTORY_SEPARATOR . self::$oWiringPaths->getPaths()[$sPath] . '_NsClassFilesMap.php';
    }

    /**
     * @param string $sPath
     * @return string
     */
    protected static function getNsClassFilesMapPathLastCheckTs(string $sPath): string
    {
        return self::getCacheDir() . DIRECTORY_SEPARATOR . self::$oWiringPaths->getPaths()[$sPath] . '_CheckTs';
    }

    protected static function getHash(): string
    {
        $aEnvSettings = self::$oWiringPaths->getEnvSettings();
        return self::ConcretePrefix . self::$oWiringPaths->hashV(serialize(
                [
                    self::$oWiringPaths->getPaths(),
                    $aEnvSettings[self::RegexExcludeFqcnsAndPaths],
                    $aEnvSettings[self::DumpPhpFilePathAndMtime],
                    AfrClassDependency::getSkipClassInfo(),
                    AfrClassDependency::getSkipNamespaceInfo(),
                ]));
    }

    /**
     * @return string
     */
    protected static function getInterfaceToConcretePath(): string
    {
        return self::getCacheDir() . DIRECTORY_SEPARATOR .
            self::getHash() .
            '_ClassInterfaceToConcrete.php';
    }

    /**
     * @return string
     */
    protected static function getFailedClassPermanentSkipFile(): string
    {
        return self::getCacheDir() . DIRECTORY_SEPARATOR .
            self::getHash() .
            '_FailedClassesPermanentlySkipped.php';
    }

    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    protected static function getClassPermanentSkipClasses(): array
    {
        if (is_file(self::getFailedClassPermanentSkipFile())) {
            return (array)(include(self::getFailedClassPermanentSkipFile()));
        }
        return [];
    }

    /**
     * @param $sPath
     * @return bool
     */
    protected static function excludeRegEx($sPath): bool
    {
        foreach (self::$oWiringPaths->getEnvSettings()[self::RegexExcludeFqcnsAndPaths] as $sPattern) {
            if (preg_match($sPattern, $sPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $sDirPath
     * @param int $iMaxTimestamp
     * @return int
     * @throws AfrInterfaceToConcreteException
     */
    protected static function getMaxDirTs(string $sDirPath, int $iMaxTimestamp = 0): int
    {
        $aSubDirs = [];
        $sDirPath = strtr($sDirPath, DIRECTORY_SEPARATOR === '/' ? '\\' : '/', DIRECTORY_SEPARATOR);
        // Ignore current cache dir
        // Under the vendor dir, this is not necessary because the vendor dir
        // is versioned by the vendor/composer/install.json and not by the individual php's timestamps
        if ($sDirPath === self::getCacheDir()) {
            return $iMaxTimestamp;
        }
        if (self::excludeRegEx($sDirPath)) {
            return $iMaxTimestamp;
        }

        $dh = opendir($sDirPath);
        while (($sEntry = readdir($dh)) !== false) {
            if ($sEntry === '.' || $sEntry === '..') {
                continue;
            }
            $sTarget = $sDirPath . DIRECTORY_SEPARATOR . $sEntry;
            $sType = (string)(@filetype($sTarget));
            if (!$sType) {
                continue;
            }
            if ($sType === 'file') {
                $sExt = strtolower(substr($sEntry, -4, 4));
                if ($sExt === '.php' || $sExt === '.inc') {
                    if (self::excludeRegEx($sTarget)) {
                        continue;
                    }
                    $iMaxTimestamp = max($iMaxTimestamp, (int)filemtime($sTarget));
                }
            } elseif ($sType === 'dir') {
                $aSubDirs[] = $sTarget; //keep low the open dir resource count
            }
        }
        closedir($dh);
        foreach ($aSubDirs as $sSubDir) {
            $iMaxTimestamp = self::getMaxDirTs($sSubDir, $iMaxTimestamp);
        }
        return $iMaxTimestamp;
    }

    /**
     * @return string
     */
    public static function getCacheDir(): string
    {
        return self::$sCacheDir; //get
    }

}