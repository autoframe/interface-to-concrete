<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;
use PHPUnit\Framework\TestCase;
use Autoframe\InterfaceToConcrete\AfrInterfaceToConcreteClass;


class AfrInterfaceToConcreteClassTest extends TestCase
{
    function getVendorPathProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        return [
            [[], 3600 * 24 * 365 * 30, false],
            [['vendor'], 3600 * 24 * 365 * 30, false],
            [[__DIR__], 3600 * 24 * 365 * 30, false],
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
        $obj = null;
        try {
            $obj = new AfrInterfaceToConcreteClass($aExtraPaths, $iAutoWireCacheExpireSeconds, $bForceRegenerateAllButVendor);

            $this->assertEquals($obj->getForceRegenerateAllButVendor(), $bForceRegenerateAllButVendor);
            $this->assertEquals(true, strlen($obj->getHash()) > 5);
            $this->assertEquals(true, count($obj->getPaths()) > 0);
            $this->assertEquals(true, $obj->getCacheExpire() > 0);
            $aMap = $obj->getClassInterfaceToConcrete();
            $this->assertEquals(true, is_array($aMap));
            $this->assertEquals(true, count($aMap) > 10);

            $i = 0;
            foreach ($aMap as $sFqcn => $aDeps) {
                if ($i > 2.4) {
                    break;
                }
                $this->assertEquals(true, is_array($aDeps));
                $this->assertEquals(true, interface_exists($sFqcn) || class_exists($sFqcn));
                $i++;
                foreach ($aDeps as $sDfqcn => $bInstantiable) {
                    $this->assertEquals(true, interface_exists($sDfqcn) || class_exists($sDfqcn));
                    $this->assertEquals(true, is_bool($bInstantiable));
                    $i += 0.2;
                }
                break;
            }
        } catch (AfrInterfaceToConcreteException $e) {

        }
        $this->assertEquals(true, $obj instanceof AfrInterfaceToConcreteClass);
    }


}