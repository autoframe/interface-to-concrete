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

    protected static AfrInterfaceToConcreteClass $oWiringPaths; // what to wire
    protected static string $sCacheDir; //local cache dir

    protected static bool $bRegenerateAllFlagButVendor = false; //force regenerate for caches
    protected static array $aRegeneratedByBuildNewNsClassFilesMap = []; //regeneration report

    protected static bool $bClassTreeInfoCacheWriteDone = true; //infinite loop prevention
    protected static array $aNsClassMergedFromPathMap = []; //temp internal work cache

    /**
     * @param AfrInterfaceToConcreteClass $oWiringPaths
     * @return void
     */
    public static function setAfrConfigWiredPaths(AfrInterfaceToConcreteClass $oWiringPaths): void
    {
        self::$oWiringPaths = $oWiringPaths;
        self::$bRegenerateAllFlagButVendor = $oWiringPaths->getForceRegenerateAllButVendor();
        self::$aNsClassMergedFromPathMap = self::$aRegeneratedByBuildNewNsClassFilesMap = [];
    }


    /**
     * Set or get force regenerating flag
     * The regeneration can take few seconds depending on the number of php clases
     * @param bool|null $bRegenerateAll
     * @return bool
     */
    public static function xetForceRegenerateAllButVendor(bool $bRegenerateAll = null): bool
    {
        if ($bRegenerateAll !== null) { //set
            self::$bRegenerateAllFlagButVendor = $bRegenerateAll;
        }
        return self::$bRegenerateAllFlagButVendor; //get
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
            ob_start();
            self::classInterfaceToConcreteWrite();
            ob_end_clean();
        }
        if (!is_file(self::getInterfaceToConcretePath())) {
            throw new AfrInterfaceToConcreteException(__CLASS__ . ' is can not resolve ' . __FUNCTION__);
        }
        //return [self::$aNsClassMergedFromPathMap, include(self::getInterfaceToConcretePath())];
        return (array)(include(self::getInterfaceToConcretePath()));

    }

    /**
     * @return void
     */
    public static function classInterfaceToConcreteWrite(): void
    {
        if (!self::$bClassTreeInfoCacheWriteDone) {
            //TRY TO STEP OVER MAX ONE FATAL ERROR!
            register_shutdown_function(function () {
                if (AfrClassDependency::getDebugFatalError()) {
                    echo ob_get_contents();
                    ob_end_clean();
                    echo PHP_EOL . 'Corrupted classes: ' .
                        implode('; ', array_keys(AfrClassDependency::getDebugFatalError())) .
                        PHP_EOL;
                    self::overWrite(self::getFailedClassPermanentSkipFile(), array_merge(
                        self::getClassPermanentSkipClasses(),
                        AfrClassDependency::getDebugFatalError()
                    ));
                }
                self::classInterfaceToConcreteWrite();
            });

            $aToSkip = self::getClassPermanentSkipClasses();
            foreach (self::$aNsClassMergedFromPathMap as $sNsCl => $sClassPath) {
                if (isset($aToSkip[$sNsCl])) {
                    //Corrupted class! This will not be used in the "to concrete process"
                    continue;
                }
                AfrClassDependency::getClassInfo($sNsCl);
            }
            self::overWrite(self::getInterfaceToConcretePath(), self::classInterfaceToConcreteMap());
            self::$bClassTreeInfoCacheWriteDone = true;
            self::$aRegeneratedByBuildNewNsClassFilesMap = [];

        }

    }


    /**
     * @param string $sPath
     * @return array
     * @throws AfrInterfaceToConcreteException
     */
    public static function getSingleNsClassFilesMap(string $sPath): array
    {
        if (!isset(self::$oWiringPaths->getPaths()[$sPath])) {
            throw new AfrInterfaceToConcreteException('First set the path using ' . __CLASS__ . '::setAfrConfigMapWiringPaths(AfrConfigMapWiringPaths)');
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
    protected static function getAllNsClassFilesMap(): array
    {
        if (empty(self::$oWiringPaths)) {
            throw new AfrInterfaceToConcreteException('Run first ' . __CLASS__ . '::setAfrConfigMapWiringPaths(AfrConfigMapWiringPaths)');
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
            $bComposerUpdated = AfrVendorPath::getComposerTs() > $iCacheFileMtime;
            if ($bComposerUpdated) {
                self::xetForceRegenerateAllButVendor(true); //propagate composer change to all
            }
            return $bComposerUpdated;
        }

        //OTHER PATHS
        if (self::xetForceRegenerateAllButVendor()) {
            return true;
        }

        if (time() > $iCacheFileMtime + self::$oWiringPaths->getCacheExpire()) {
            //old local cache file, so we rescan the system:
            $sLastCheckFilePath = self::getNsClassFilesMapPathLastCheckTs($sPath);
            if (!is_file($sLastCheckFilePath)) {
                return true;
            }
            if (time() > filemtime($sLastCheckFilePath) + self::$oWiringPaths->getCacheExpire()) {
                $iTsMaxFileSystem = self::getMaxDirTs($sPath);
                if ($iTsMaxFileSystem > $iCacheFileMtime) {
                    return true;
                } else {
                    // no file changes since last cache build
                    self::overWrite($sLastCheckFilePath, [gmdate('D, d M Y H:i:s').' GMT']);
                }
            }
        }
        return false;
    }

    /**
     * @return array
     */
    private static function classInterfaceToConcreteMap(): array
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
    private static function buildNewNsClassFilesMap(string $sPath): array
    {
        $sAlPrefix = AfrInterfaceToConcreteClass::AutoloadPrefix;

        $sVendorOrAutoload = '';
        if (AfrVendorPath::getVendorPath() === $sPath) {
            $sVendorOrAutoload = 'vendor';
        } elseif (substr($sPath, 0, strlen($sAlPrefix)) === $sAlPrefix) {
            $sVendorOrAutoload = 'autoload';
        }

        if ($sVendorOrAutoload) {
            $aClasses = AfrVendorPath::getComposerAutoloadX(false)[$sVendorOrAutoload];
            $aClasses = array_merge(
                $aClasses['classmap'],
                AfrVendorPath::createMapFromPsrX($aClasses['psr4']),
                AfrVendorPath::createMapFromPsrX($aClasses['psr0']),
            );
        } else {
            $aClasses = AfrVendorPath::createMap($sPath);
        }

        if (AfrVendorPath::getVendorPath() === $sPath) {
            foreach ($aClasses as &$sClassPath) {
                //$sClassPath = '1|' . $sClassPath;
                $sClassPath = 1;
            }
        } else {
            foreach ($aClasses as &$sClassPath) {
                //$sClassPath = ((string)@filemtime($sClassPath)) . '|' . $sClassPath;
                $sClassPath = 2;
            }
        }

        arsort($aClasses); //sort in order to have the latest timestamp first

        self::$aRegeneratedByBuildNewNsClassFilesMap[$sPath] =
            self::overWrite(self::getNsClassFilesMapPath($sPath), $aClasses);
        self::overWrite(self::getNsClassFilesMapPathLastCheckTs($sPath), [gmdate('D, d M Y H:i:s').' GMT']);

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
        return (new AfrOverWriteClass())->overWriteFile(
            $sPathTo,
            '<?php return ' . (new AfrArrExportArrayAsStringClass())->exportPhpArrayAsString($aData),
            $iRetryMs,
            $fDelta
        );
    }


    /**
     * @param string $sPath
     * @return string
     */
    private static function getNsClassFilesMapPath(string $sPath): string
    {
        return self::xetCacheDir() . DIRECTORY_SEPARATOR . self::$oWiringPaths->getPaths()[$sPath] . '_NsClassFilesMap.php';
    }

    /**
     * @param string $sPath
     * @return string
     */
    private static function getNsClassFilesMapPathLastCheckTs(string $sPath): string
    {
        return self::xetCacheDir() . DIRECTORY_SEPARATOR . self::$oWiringPaths->getPaths()[$sPath] . '_CheckTs';
    }

    /**
     * @return string
     */
    private static function getInterfaceToConcretePath(): string
    {
        return self::xetCacheDir() . DIRECTORY_SEPARATOR .
            self::$oWiringPaths->getHash() .
            '_ClassInterfaceToConcrete.php';
    }

    /**
     * @return string
     */
    private static function getFailedClassPermanentSkipFile(): string
    {
        return self::xetCacheDir() . DIRECTORY_SEPARATOR .
            self::$oWiringPaths->getHash() .
            '_FailedClassesPermanentlySkipped.php';
    }

    /**
     * @return array
     */
    private static function getClassPermanentSkipClasses(): array
    {
        if (is_file(self::getFailedClassPermanentSkipFile())) {
            return (array)(include(self::getFailedClassPermanentSkipFile()));
        }
        return [];
    }


    /**
     * @param $sPath
     * @return string
     */
    private static function getMapDirCachePath($sPath): string
    {
        return self::xetCacheDir() . DIRECTORY_SEPARATOR . self::$oWiringPaths->getPaths()[$sPath];
    }

    /**
     * @param string $sDirPath
     * @param int $iMaxTimestamp
     * @return int
     */
    protected static function getMaxDirTs(string $sDirPath, int $iMaxTimestamp = 0): int
    {
        $aSubDirs = [];
        $sDirPath = strtr($sDirPath, DIRECTORY_SEPARATOR === '/' ? '\\' : '/', DIRECTORY_SEPARATOR);
        if ($sDirPath === self::xetCacheDir()) { //ignore current cache dir
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
     * @param string $sCacheDir
     * @return string
     */
    protected static function xetCacheDir(string $sCacheDir = ''): string
    {
        $bSet = false;
        if (strlen($sCacheDir)) { //set
            $sCacheDir = (string)realpath($sCacheDir);
            if (strlen($sCacheDir)) {
                $bSet = true;
                self::$sCacheDir = $sCacheDir;
            }
        }

        if (empty(self::$sCacheDir)) { //fallback
            $bSet = true;
            self::$sCacheDir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
        }
        if ($bSet && !is_file(self::$sCacheDir . DIRECTORY_SEPARATOR . '.gitignore')) {
            file_put_contents(self::$sCacheDir . DIRECTORY_SEPARATOR . '.gitignore', '*');
        }
        return self::$sCacheDir; //get
    }

}