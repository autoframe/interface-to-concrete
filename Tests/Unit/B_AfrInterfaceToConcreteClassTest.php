<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\ClassDependency\AfrClassDependency;
use Autoframe\Components\Exception\AfrException;
use Autoframe\InterfaceToConcrete\AfrMultiClassMapper;
use PHPUnit\Framework\TestCase;
use Autoframe\InterfaceToConcrete\AfrInterfaceToConcreteClass;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;


class B_AfrInterfaceToConcreteClassTest extends TestCase
{
    public static function getVendorPathProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $thirty_years = 3600 * 24 * 365 * 30;
        return [
            [[], 5, true, false, true, 'DEV'],
            [['vendor'], $thirty_years, false, false, false, 'PRODUCTION'],
            [[__DIR__], $thirty_years, false, false, true, 'DEBUG'],
        ];
    }

    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getVendorPathTest(array  $aExtraPaths,
                                      int    $iAutoWireCacheExpireSeconds,
                                      bool   $bForceRegenerateAllButVendor,
                                      bool   $bGetSilenceErrors,
                                      bool   $bDumpPhpFilePathAndMtime,
                                      string $sEnv
    ): void
    {
        $mixedTestRegExExtraDirs = in_array(__DIR__, $aExtraPaths);


        $aEnvSettings = [
            AfrMultiClassMapper::CacheExpireSeconds => $iAutoWireCacheExpireSeconds,
            AfrMultiClassMapper::ForceRegenerateAllButVendor => $bForceRegenerateAllButVendor,
            AfrMultiClassMapper::SilenceErrors => $bGetSilenceErrors,
            AfrMultiClassMapper::DumpPhpFilePathAndMtime => $bDumpPhpFilePathAndMtime,
        ];
        AfrClassDependency::flush();
        if ($mixedTestRegExExtraDirs) {
            AfrClassDependency::setSkipNamespaceInfo([], false);
            AfrClassDependency::setSkipClassInfo([], false);
            $aEnvSettings[AfrMultiClassMapper::ClassDependencySetSkipClassInfo] = [];
            $aEnvSettings[AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo] = [];
            $aEnvSettings[AfrMultiClassMapper::RegexExcludeFqcnsAndPaths] = ['@src.{1,}Exception@', '@PHPUnit.{1,}Telemetry@'];
            $aEnvSettings[AfrMultiClassMapper::CacheDir] = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
            if (!is_dir($aEnvSettings[AfrMultiClassMapper::CacheDir])) {
                mkdir($aEnvSettings[AfrMultiClassMapper::CacheDir], 0755);
            }
        }

        $obj = null;
        try {
            $obj = new AfrInterfaceToConcreteClass($sEnv, $aEnvSettings, $aExtraPaths); //['DEV', 'PRODUCTION', 'STAGING', 'DEBUG']

            $this->assertSame(
                $obj->getEnvSettings()[AfrMultiClassMapper::ForceRegenerateAllButVendor],
                $bForceRegenerateAllButVendor,
                '!$bForceRegenerateAllButVendor'
            );

            $this->assertSame(
                $obj->getEnvSettings()[AfrMultiClassMapper::SilenceErrors],
                $bGetSilenceErrors,
                '!$bGetSilenceErrors'
            );
            $this->assertSame(true, count($obj->getPaths()) > 0, '!getPaths');

            $iCacheExpireSeconds = $obj->getEnvSettings()[AfrMultiClassMapper::CacheExpireSeconds];
            $this->assertSame(
                true,
                is_int($iCacheExpireSeconds) && $iCacheExpireSeconds > 1,
                '!getCacheExpire'
            );
            $aMap = $obj->getClassInterfaceToConcrete();
            $this->assertSame(true, is_array($aMap), '!is_array($aMap)');
            if ($mixedTestRegExExtraDirs) {
                $sTelemetryNs = 'PHPUnit\\Event\\Telemetry';
                $iLenTelemetryNs = strlen($sTelemetryNs);
                foreach ($aMap as $sFqcn => $aDeps) {
                    if ($sFqcn === AfrInterfaceToConcreteException::class) {
                        $this->assertSame(true, false, 'Regex failed: ' . $aEnvSettings[AfrMultiClassMapper::RegexExcludeFqcnsAndPaths][0] . print_r($aMap, true));
                        break;
                    }
                    if (substr($sFqcn, 0, $iLenTelemetryNs) === $sTelemetryNs) {
                        $this->assertSame(true, false, 'Regex failed: ' . $aEnvSettings[AfrMultiClassMapper::RegexExcludeFqcnsAndPaths][1] . print_r($aMap, true));
                        break;
                    }
                }
            }

            $i = 0;
            foreach ($aMap as $sFqcn => $aDeps) {
                if ($i > 2.4) {
                    break;
                }
                $this->assertSame(true, interface_exists($sFqcn) || class_exists($sFqcn), 'interface||class');
                $this->assertSame(true, is_array($aDeps) || is_bool($aDeps), '!is_array($aDeps)');
                if (!is_array($aDeps)) {
                    continue;
                }
                $i++;
                foreach ($aDeps as $sDfqcn => $bInstantiable) {
                    $this->assertSame(true, interface_exists($sDfqcn) || class_exists($sDfqcn), 'interface|2|class');
                    $this->assertSame(true, is_bool($bInstantiable), '!is_bool($bInstantiable)');
                    $i += 0.2;
                }
                break;
            }
        } catch (AfrException $e) {
            $this->assertSame(true, $e->getMessage(), $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        $this->assertSame(true, $obj instanceof AfrInterfaceToConcreteClass, '!$obj instanceof AfrInterfaceToConcreteClass');
        AfrClassDependency::clearDebugFatalError();
        AfrClassDependency::clearDependencyInfo();
        AfrClassDependency::setSkipClassInfo([]);
        AfrClassDependency::setSkipNamespaceInfo([]);
    }


}