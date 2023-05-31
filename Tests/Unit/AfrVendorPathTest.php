<?php
declare(strict_types=1);

namespace Unit;

use PHPUnit\Framework\TestCase;
use Autoframe\InterfaceToConcrete\AfrVendorPath;

class AfrVendorPathTest extends TestCase
{
    function getVendorPathProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        return [ [''],  ];
    }


    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getVendorPathTest($sNot): void
    {
        $this->assertNotEquals($sNot, AfrVendorPath::getVendorPath());
    }


    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getBaseDirPathTest($sNot): void
    {
        $this->assertNotEquals($sNot, AfrVendorPath::getBaseDirPath());
    }


    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getComposerJsonTest($sNot): void
    {
        $this->assertEquals(true, count(AfrVendorPath::getComposerJson()) > 2);
    }






    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getComposerTsTest($sNot): void
    {
        $this->assertGreaterThan(strtotime('2023-05-01'), AfrVendorPath::getComposerTs());
    }

    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getComposerAutoloadXTest($sNot): void
    {
        $aX = AfrVendorPath::getComposerAutoloadX();
        $this->assertEquals(true, count($aX) === 2);
        $this->assertEquals(true, isset($aX['vendor']));
        $this->assertEquals(true, isset($aX['autoload']));
        $this->assertEquals(true, count($aX['vendor']) === 3);
        $this->assertEquals(true, count($aX['autoload']) === 3);
    }



}