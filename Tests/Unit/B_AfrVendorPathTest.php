<?php
declare(strict_types=1);

namespace Unit;

use PHPUnit\Framework\TestCase;
use Autoframe\InterfaceToConcrete\AfrVendorPath;

class B_AfrVendorPathTest extends TestCase
{
    public static function getVendorPathProvider(): array
    {
        return [ [''],  ];
    }


    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getVendorPathTest($sNot): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $this->assertNotSame($sNot, AfrVendorPath::getVendorPath());
    }


    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getBaseDirPathTest($sNot): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $this->assertNotSame($sNot, AfrVendorPath::getBaseDirPath());
    }


    /**
     * @test
     * @dataProvider getVendorPathProvider
     */
    public function getComposerJsonTest($sNot): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $this->assertSame(true, count(AfrVendorPath::getComposerJson()) > 2);
    }


    /**
     * @test
     */
    public function getComposerTsTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $this->assertGreaterThan(strtotime('2023-05-01'), AfrVendorPath::getComposerTs());
    }

    /**
     * @test
     */
    public function getComposerAutoloadXTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $aX = AfrVendorPath::getComposerAutoloadX();
        $this->assertSame(true, count($aX) === 2);
        $this->assertSame(true, isset($aX['vendor']));
        $this->assertSame(true, isset($aX['autoload']));
        $this->assertSame(true, count($aX['vendor']) === 3);
        $this->assertSame(true, count($aX['autoload']) === 3);
    }



}