<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\ClassDependency\AfrClassDependency;
use Autoframe\Components\Exception\AfrException;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;
use PHPUnit\Framework\TestCase;
use Autoframe\InterfaceToConcrete\AfrInterfaceToConcreteClass;


class AfrInterfaceToConcreteClassTest extends TestCase
{
    function getVendorPathProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $thirty_years = 3600 * 24 * 365 * 30;
        return [
            [[], $thirty_years, true],
            [['vendor'], $thirty_years, false],
            [[__DIR__], $thirty_years, false],
        ];
    }

    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getVendorPathTest(array $aExtraPaths,
                                      int   $iAutoWireCacheExpireSeconds,
                                      bool  $bForceRegenerateAllButVendor
    ): void
    {
        if(in_array(__DIR__,$aExtraPaths)){
            AfrClassDependency::setSkipNamespaceInfo([''], true); //skip some global classes
        }

        $obj = null;
        try {
            $obj = new AfrInterfaceToConcreteClass($aExtraPaths, $iAutoWireCacheExpireSeconds, $bForceRegenerateAllButVendor);

            $this->assertEquals($obj->getForceRegenerateAllButVendor(), $bForceRegenerateAllButVendor,'!$bForceRegenerateAllButVendor');
            $this->assertEquals(true, strlen($obj->getHash()) > 5,'!getHash');
            $this->assertEquals(true, count($obj->getPaths()) > 0,'!getPaths');
            $this->assertEquals(true, $obj->getCacheExpire() > 0,'!getCacheExpire');
            $aMap = $obj->getClassInterfaceToConcrete();
            $this->assertEquals(true, is_array($aMap),'!is_array($aMap)');
            $this->assertEquals(true, count($aMap) > 10,'!count($aMap)');

            $i = 0;
            foreach ($aMap as $sFqcn => $aDeps) {
                if ($i > 2.4) {
                    break;
                }
                $this->assertEquals(true, interface_exists($sFqcn) || class_exists($sFqcn),'interface||class');
                $this->assertEquals(true, is_array($aDeps) || is_bool($aDeps),'!is_array($aDeps)');
                if(!is_array($aDeps)){
                    continue;
                }
                $i++;
                foreach ($aDeps as $sDfqcn => $bInstantiable) {
                    $this->assertEquals(true, interface_exists($sDfqcn) || class_exists($sDfqcn),'interface|2|class');
                    $this->assertEquals(true, is_bool($bInstantiable),'!is_bool($bInstantiable)');
                    $i += 0.2;
                }
                break;
            }
        } catch (AfrException $e) {

        }
        $this->assertEquals(true, $obj instanceof AfrInterfaceToConcreteClass,'!$obj instanceof AfrInterfaceToConcreteClass');
    }


}