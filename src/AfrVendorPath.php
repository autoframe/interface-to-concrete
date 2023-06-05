<?php
declare(strict_types=1);

namespace Autoframe\InterfaceToConcrete;

use Composer\ClassMapGenerator\ClassMapGenerator;

use function is_array;
use function spl_autoload_functions;
use function class_exists;
use function explode;
use function realpath;
use function count;
use function substr;
use function in_array;
use function implode;
use function is_file;
use function str_repeat;
use function filemtime;

/**
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
 * Requires composer package: composer/class-map-generator
 * This will detect the real vendor path:
 * /shared/app/afr/vendor
 * C:\xampp\htdocs\afr\vendor
 * Empty is returned on detection fail
 */
class AfrVendorPath
{
    protected static string $sVendor = 'vendor';
    protected static string $sInstalledJson = '/composer/installed.json';
    protected static string $sVendorPath;
    protected static string $sBaseDirPath;
    protected static array $aComposerJsonData;

    /**
     * This will detect the real vendor path:
     * /shared/app/afr/vendor
     * C:\xampp\htdocs\afr\vendor
     * Empty string '' is returned on detection fail
     * @return string
     */
    public static function getVendorPath(): string
    {
        if (!isset(self::$sVendorPath)) {
            self::$sVendorPath = self::detectVendorPath();
        }
        return self::$sVendorPath;
    }

    /**
     * This will get the base path set in composer config files
     * For multiple apps that use a common vendor/composer dir,
     * this path may be outside the current app path and may be
     * different from the current app path!
     * Empty string '' is returned on detection fail
     * @return string
     */
    public static function getBaseDirPath(): string
    {
        if (!isset(self::$sBaseDirPath)) {
            $sVendorPath = self::getVendorPath();
            if (!$sVendorPath) {
                return self::$sBaseDirPath = '';
            }
            return self::$sBaseDirPath = substr(
                $sVendorPath,
                0,
                -strlen(self::$sVendor) - 1
            );
        }
        return self::$sBaseDirPath;
    }

    /**
     * @return array
     */
    public static function getComposerJson(): array
    {
        if (isset(self::$aComposerJsonData)) {
            return self::$aComposerJsonData;
        }
        $sBasePath = self::getBaseDirPath();

        return self::$aComposerJsonData = ($sBasePath ? json_decode(
            file_get_contents($sBasePath . DIRECTORY_SEPARATOR . 'composer.json'),
            true
        ) : []);
    }


    /**
     * @return int
     * Empty 0 is returned on detection fail
     */
    public static function getComposerTs(): int
    {
        if (!self::getVendorPath()) {
            return 0;
        }
        $sFile = self::getVendorPath() . self::$sInstalledJson;
        return is_file($sFile) ? (int)filemtime($sFile) : 0;
    }

    /**
     * @param $sPath
     * @return array
     */
    public static function createMap($sPath): array
    {
        return ClassMapGenerator::createMap($sPath);
    }

    /**
     * @return array
     * Empty array is returned on detection fail
     */
    public static function getFullClassFilesMap(): array
    {
        if (!self::getVendorPath()) {
            return [];
        }
        return array_merge(
            self::getComposerAutoloadClassmap(),
            self::getComposerAutoloadPsrX(4),
            self::getComposerAutoloadPsrX(0)
        );
    }

    /**
     * Empty array is returned on detection fail
     * @param bool $bScanPsrClassMap
     * @return array
     */
    public static function getComposerAutoloadX(bool $bScanPsrClassMap = false): array
    {
        $vendorDir = self::getVendorPath();
        $iLenVendorDir = strlen($vendorDir);
        $aOut = [
            'vendor' => [
                'classmap' => [],
                'psr4' => [],
                'psr0' => [],
            ],
            'autoload' => [
                'classmap' => [],
                'psr4' => [],
                'psr0' => [],
            ]
        ];
        if (!$vendorDir) {
            return $aOut;
        }

        foreach (self::getIncludedPhpArr('autoload_classmap') as $sNsCl => $sPath) {
            $sVa = substr($sPath, 0, $iLenVendorDir) === $vendorDir ? 'vendor' : 'autoload';
            $aOut[$sVa]['classmap'][$sNsCl] = $sPath;
        }

        // https://getcomposer.org/doc/articles/autoloader-optimization.md#how-to-run-it-
        $aComposerJson = self::getComposerJson();
        if (!empty($aComposerJson['classmap-authoritative'])) {
            // !empty($aComposerJson['optimize-autoloader'])
            return $aOut; //CLASS MAP HAS EVERYTHING THERE! PSR4 and PSR0 will be missed
        }

        foreach (['autoload_psr4' => 'psr4', 'autoload_namespaces' => 'psr0'] as $sPhpIncl => $sPsrX) {
            foreach (self::getIncludedPhpArr($sPhpIncl) as $sNsCl => $aDirs) {
                foreach ($aDirs as $sPackageDir) {
                    $sVa = substr($sPackageDir, 0, $iLenVendorDir) === $vendorDir ? 'vendor' : 'autoload';
                    if ($bScanPsrClassMap) {
                        $aOut[$sVa][$sPsrX] = array_merge($aOut[$sPsrX][$sVa], self::createMap($sPackageDir));
                    } else {
                        $aOut[$sVa][$sPsrX][$sNsCl][] = $sPackageDir;
                    }
                }
            }
        }

        return $aOut;
    }

    /**
     * @param array $aClasses
     * @return void
     */
    protected static function fixDs(array &$aClasses)
    {
        $sDsReplace = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';
        foreach ($aClasses as &$sClPath) {
            if (is_array($sClPath)) {
                self::fixDs($sClPath);
            } else {
                $sClPath = strtr($sClPath, $sDsReplace, DIRECTORY_SEPARATOR);
            }

        }
    }

    /**
     * @return array
     */
    protected static function getComposerAutoloadClassmap(): array
    {
        return self::getIncludedPhpArr('autoload_classmap');
    }


    /**
     * @param int $iPsr
     * @return array
     */
    protected static function getComposerAutoloadPsrX(int $iPsr): array
    {
        $aNsDirs = [];

        if ($iPsr === 4) {
            $aNsDirs = self::getIncludedPhpArr('autoload_psr4');
        } elseif ($iPsr === 0) {
            $aNsDirs = self::getIncludedPhpArr('autoload_namespaces');
        }
        return self::createMapFromPsrX($aNsDirs);

    }

    /**
     * @param array $aNsDirs
     * @return array
     */
    public static function createMapFromPsrX(array $aNsDirs): array
    {
        $aClasses = [];
        foreach ($aNsDirs as $aDirs) {
            foreach ($aDirs as $sPackageDir) {
                $aClasses = array_merge(self::createMap($sPackageDir), $aClasses);
            }
        }
        return $aClasses;
    }


    /**
     * @param $sPhp
     * @return array
     */
    protected static function getIncludedPhpArr($sPhp): array
    {
        $aClasses = [];
        $sStaticMapFile = self::getVendorPath() . '/composer/' . $sPhp . '.php';
        if (file_exists($sStaticMapFile)) {
            $aClasses = (include $sStaticMapFile);
            if (!is_array($aClasses)) {
                $aClasses = [];
            }
        }
        self::fixDs($aClasses);
        return $aClasses;
    }


    /** Detects a valid vendor path in the file system
     * @return string
     */
    protected static function detectVendorPath(): string
    {
        $sDs = DIRECTORY_SEPARATOR;
        $sDsUp = '..' . $sDs;
        foreach ([
                     __DIR__, //production, already installed in composer
                     __DIR__ . $sDs . str_repeat($sDsUp, 2) . self::$sVendor, //local dev1
                     __DIR__ . $sDs . str_repeat($sDsUp, 3) . self::$sVendor, //local dev2
                     __DIR__ . $sDs . str_repeat($sDsUp, 4) . self::$sVendor, //local dev3
                     __DIR__ . $sDs . str_repeat($sDsUp, 5) . self::$sVendor, //local dev4
                     __DIR__ . $sDs . str_repeat($sDsUp, 6) . self::$sVendor, //local dev5
                 ] as $sDir) {
            $sVendorPath = self::checkForComposerVendorPath($sDir);
            if ($sVendorPath) {
                return $sVendorPath;
            }
        }

        //fallback: script is loaded outside composer vendor dir or other custom path:
        $i = 0;
        foreach (self::getSplComposerClassMap() as $sDir) {
            if ($i >= 10) {
                break; //limit to 10 entries is a reasonable value before failing
            }
            $i++;
            $sVendorPath = self::checkForComposerVendorPath($sDir);
            if ($sVendorPath) {
                return $sVendorPath;
            }
        }
        return ''; //fail!
    }

    /** Detects if composer is installed into a valid vendor path
     * @param string $sPath
     * @return string
     */
    protected static function checkForComposerVendorPath(string $sPath): string
    {
        $arRealPath = explode(self::$sVendor, (string)realpath($sPath));
        $iParts = count($arRealPath);
        if ($iParts < 2) {
            return '';
        }
        $sChrAfterVendor = substr($arRealPath [$iParts - 1], 0, 1);
        $sChrBeforeVendor = substr($arRealPath [$iParts - 2], -1, 1);
        if (
            !in_array($sChrAfterVendor, ['', '\\', '/']) ||
            !in_array($sChrBeforeVendor, ['\\', '/'])
        ) {
            return '';
        }
        $arRealPath [$iParts - 1] = ''; //clear last part
        $sRealPath = implode(self::$sVendor, $arRealPath);
        return is_file($sRealPath . self::$sInstalledJson) ? (string)realpath($sRealPath) : '';
    }

    /**
     * @return array
     * Fallback for vendor directory that is outside the app directory
     */
    protected static function getSplComposerClassMap(): array
    {
        $sClassLoader = 'Composer\\Autoload\\ClassLoader';
        if (!class_exists($sClassLoader)) {
            return [];
        }
        foreach ((array)spl_autoload_functions() as $aAutoloadFunction) {
            if (!is_array($aAutoloadFunction)) {
                continue;
            }
            foreach ($aAutoloadFunction as $mLoader) {
                if ($mLoader instanceof $sClassLoader) {
                    return (array)$mLoader->getClassMap();
                }
            }
        }
        return [];
    }

}