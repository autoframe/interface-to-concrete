<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\InterfaceToConcrete\AfrInterfaceToConcreteClass;
use Autoframe\InterfaceToConcrete\AfrVendorPath;
use PHPUnit\Framework\TestCase;
use Autoframe\InterfaceToConcrete\AfrToConcreteStrategiesClass;
use Autoframe\InterfaceToConcrete\AfrToConcreteStrategiesInterface;

class AfrToConcreteStrategiesClassTest extends TestCase
{
    protected AfrInterfaceToConcreteClass $oAfrInterfaceToConcreteClass;

    protected function setUp(): void
    {
        $this->oAfrInterfaceToConcreteClass = new AfrInterfaceToConcreteClass('DEV');
    }

    protected function tearDown(): void
    {
        //cleanup between tests for static
    }

    /**
     * @test
     */
    public function getLatestInstanceTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $iNew = new AfrToConcreteStrategiesClass();
        $oLatestInstance = AfrToConcreteStrategiesClass::getLatestInstance();
        $this->assertSame($iNew, $oLatestInstance);

        $iNew2 = new AfrToConcreteStrategiesClass();
        $oLatestInstance2 = AfrToConcreteStrategiesClass::getLatestInstance();
        $this->assertSame($iNew2, $oLatestInstance2);

        $this->assertNotSame($oLatestInstance, $oLatestInstance2);
        $this->assertNotSame($iNew, $oLatestInstance2);
        $this->assertNotSame($iNew2, $oLatestInstance);
    }

    /**
     * @test
     */
    public function getNotConcreteFQCNTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $s1 = $obj->getNotConcreteFQCN();
        $obj->resolveInterfaceToConcrete(
            AfrToConcreteStrategiesClass::class,
            $this->oAfrInterfaceToConcreteClass,
            false
        );

        $this->assertSame(AfrToConcreteStrategiesClass::class, $obj->getNotConcreteFQCN());
        $this->assertNotSame($s1, $obj->getNotConcreteFQCN());
    }

    /**
     * @test
     */
    public function xetContextTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $sBefore = $obj->getContext();
        $sNew = (string)rand(222, 333);
        $obj->setContext($sNew);
        $sAfter = $obj->getContext();
        $this->assertNotSame($sBefore, $sAfter);
        $this->assertSame($sNew, $sAfter);

        $obj->setContext($sBefore);
        $this->assertSame($sBefore, $obj->getContext());
    }

    /**
     * @test
     */
    public function extendStrategyClosureFnTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules(__FUNCTION__, [AfrToConcreteStrategiesClass::StrategyClosureFn]);
        $obj->setPriorityRule(__FUNCTION__);

        $obj->extendStrategyClosureFn(function (
            AfrToConcreteStrategiesInterface $oStrategiesInterface,
            array                            $aMap
        ) {
            if ($oStrategiesInterface->getNotConcreteFQCN() === 'extendClosureFn') {
                //fake match something
                if (isset($aMap['clFake1'])) unset($aMap['clFake1']);
            } else {
                $aMap = [];
            }
            return $aMap;
        });

        $obj->extendStrategyClosureFn(function (
            AfrToConcreteStrategiesInterface $oStrategiesInterface,
            array                            $aMap
        ) {
            if ($oStrategiesInterface->getNotConcreteFQCN() === 'extendClosureFn2') {
                //fake match something
                if (isset($aMap['clFake3'])) unset($aMap['clFake3']);
            } else {
                $aMap = [];
            }
            return $aMap;
        });

        $this->assertSame('1|clFake2', $obj->resolveMap(
            ['clFake1' => true, 'clFake2' => true],
            'extendClosureFn'
        ));

        $this->assertSame('1|clFake4', $obj->resolveMap(
            ['clFake3' => true, 'clFake4' => true],
            'extendClosureFn2'
        ));

        $this->assertSame('0|extendClosureFn3', $obj->resolveMap(
            ['clFake5' => true, 'clFake6' => true],
            'extendClosureFn3'
        ));

        $this->assertSame('0|extendClosureFn4', $obj->resolveMap(
            [],
            'extendClosureFn4'
        ));

        //null return
        $obj->extendStrategyClosureFn(function (
            AfrToConcreteStrategiesInterface $oStrategiesInterface,
            array                            $aMap
        ) {
            return null;
        });
        $this->assertSame('0|extendClosureFn5', $obj->resolveMap(
            ['cl' . rand(0, 9) => false],
            'extendClosureFn5'
        ));

        $this->assertSame(3, count($obj->getClosureFns()));
    }


    /**
     * @test
     */
    public function extendStrategyContextBoundTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [AfrToConcreteStrategiesClass::StrategyContextBound]);
        $obj->setPriorityRule($fx);

        for ($i = 0; $i <= 2; $i++) {
            $sContext = $i ? 'Context' . $i : '';
            $obj->extendStrategyContextBound(
                $fx . 'NotConcreteFakeCB' . $i,
                $fx . 'ConcreteFakeCB' . $i,
                $sContext
            );
        }
        //same context
        for ($i = 0; $i <= 2; $i++) {
            $sContext = $i ? 'Context' . $i : '';
            $obj->setContext($sContext);

            //pick from 2 options
            $this->assertSame('1|' . $fx . 'ConcreteFakeCB' . $i, $obj->resolveMap(
                [$fx . 'ConcreteFakeCB' . $i => true, 'CBFake2' . $i => true],
                $fx . 'NotConcreteFakeCB' . $i
            ));

            //wrong map with cache
            $this->assertSame('1|' . $fx . 'ConcreteFakeCB' . $i, $obj->resolveMap(
                [$fx . 'ConcreteFakeCB' . $i => false, 'CBFake2' . $i => true],
                $fx . 'NotConcreteFakeCB' . $i,
                true
            ));

            //wrong map without cache
            $this->assertSame('1|CBFake2' . $i, $obj->resolveMap(
                [$fx . 'ConcreteFakeCB' . $i => false, 'CBFake2' . $i => true],
                $fx . 'NotConcreteFakeCB' . $i,
                false
            ));
        }

        $aExpected = [
            //c0 general
            ['1|' . $fx . 'ConcreteFakeCB0', '0|' . $fx . 'NotConcreteFakeCB1', '0|' . $fx . 'NotConcreteFakeCB2'],
            //c1
            ['1|' . $fx . 'ConcreteFakeCB0', '1|' . $fx . 'ConcreteFakeCB1', '0|' . $fx . 'NotConcreteFakeCB2'],
            //c2
            ['1|' . $fx . 'ConcreteFakeCB0', '0|' . $fx . 'NotConcreteFakeCB1', '1|' . $fx . 'ConcreteFakeCB2'],
        ];
        //cross context
        for ($c = 0; $c <= 2; $c++) {
            $sContext = $c ? 'Context' . $c : '';
            $obj->setContext($sContext);
            //general context

            for ($i = 0; $i <= 2; $i++) {
                //pick from 2 options
                $this->assertSame(
                    $aExpected[$c][$i], $obj->resolveMap(
                    [$fx . 'ConcreteFakeCB' . $i => true, 'CBFake2' . $i => true],
                    $fx . 'NotConcreteFakeCB' . $i
                ), "cross context[$sContext] c$c;i$i;{$aExpected[$c][$i]}");
            }
        }

        $this->assertSame(3, count($obj->getContextBounded()));

    }


    /**
     * @test
     */
    public function extendStrategyContextHttpRequestUriRegexTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;

        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyContextHttpRequestUriRegex,
        ])->setPriorityRule($fx);
        $this->assertSame(0, count($obj->getContextHttpRequestUriRegex()));

        $sNotConcrete = 'NotConcreteRgx';
        $sConcrete = 'FqcnConcreteRgx_BlankContext';
        $sConcreteCustom = 'FqcnConcreteRgx_customContextRegex';
        $_SERVER['REQUEST_URI'] = '/PHPUnit/XxX/Event/';
        $sRegEx = '@PHPUnit.{1,}Event@';
        $sContext2 = 'customContextRegex';
        $aMapFull = [$sConcrete => true, $sConcreteCustom => true, $fx => 2];

        //extendStrategyContextHttpRequestUriRegex
        //getContextHttpRequestUriRegex
        $obj->extendStrategyContextHttpRequestUriRegex(
            $sNotConcrete,
            $sConcrete,
            $sRegEx,
            ''
        );
        $obj->extendStrategyContextHttpRequestUriRegex(
            $sNotConcrete,
            $sConcreteCustom,
            $sRegEx,
            $sContext2,
        );

        $obj->extendStrategyContextHttpRequestUriRegex(
            $fx,
            $sConcrete,
            $sRegEx,
            $sContext2
        );
        $this->assertSame(2, count($obj->getContextHttpRequestUriRegex()));
        $this->assertSame(2, count($obj->getContextHttpRequestUriRegex()[$sContext2]));


        $obj->setContext('');
        $this->assertSame('1|' . $sConcrete, $obj->resolveMap($aMapFull, $sNotConcrete));

        $obj->setContext($sContext2);
        $this->assertSame('1|' . $sConcreteCustom, $obj->resolveMap($aMapFull, $sNotConcrete));
        $this->assertSame('1|' . $sConcrete, $obj->resolveMap($aMapFull, $fx));

        $obj->setContext($sContext2 . 'NotDefined');
        $this->assertSame('1|' . $sConcrete, $obj->resolveMap($aMapFull, $sNotConcrete));
    }

    /**
     * @test
     */
    public function extendStrategyStrategyContextNamespaceFilterArrTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;

        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyContextNamespaceFilterArr,
        ])->setPriorityRule($fx);

        $this->assertSame(0, count($obj->getContextNamespaceFilterArr()));
        $sNotC = 'nsx\NotConcreteNs';
        $sContext2 = 'customContextNs';

        $sConcreteNs1 = 'ns1\FqcnConcreteNs_BlankContext';
        $sConcreteNs11 = 'ns11\ns19\FqcnConcreteNs_BlankContext';
        $sOther = 'nsother\zzzzz';
        $sConcreteCustom2 = 'ns2\FqcnConcreteNs_customContext';
        $sConcreteCustom22 = 'ns22\ns29\FqcnConcreteNs_customContext';

        $obj->extendStrategyStrategyContextNamespaceFilterArr(
            'ns1',
            ''
        );
        $obj->extendStrategyStrategyContextNamespaceFilterArr(
            'ns11\\',
            ''
        );
        $obj->extendStrategyStrategyContextNamespaceFilterArr(
            'ns2',
            $sContext2
        );
        $obj->extendStrategyStrategyContextNamespaceFilterArr(
            'ns22\\',
            $sContext2
        );

        $aMap = [];


        $obj->setContext('');
        // default context ns1
        $aMap[0] = [$sConcreteNs1 => true, $sOther => true];
        $this->assertSame('1|' . $sConcreteNs1, $obj->resolveMap($aMap[0], $sNotC, false));

        // default context ns11\
        $aMap[1] = [$sConcreteNs11 => true, $sOther => true];
        $this->assertSame('1|' . $sConcreteNs11, $obj->resolveMap($aMap[1], $sNotC, false));

        $obj->setContext($sContext2);
        // custom context ns2
        $aMap[2] = [$sConcreteCustom2 => true, $sOther => true];
        $this->assertSame('1|' . $sConcreteCustom2, $obj->resolveMap($aMap[2], $sNotC, false));

        // custom context ns22\
        $aMap[3] = [$sConcreteCustom22 => true, $sOther => true];
        $this->assertSame('1|' . $sConcreteCustom22, $obj->resolveMap($aMap[3], $sNotC, false));

        // custom context ns fallback default
        $this->assertSame('1|' . $sConcreteNs1, $obj->resolveMap($aMap[0], $sNotC, false));
        // custom context ns\ fallback default
        $this->assertSame('1|' . $sConcreteNs11, $obj->resolveMap($aMap[1], $sNotC, false));

        //fail test
        $aMap[4] = ['ns1\nsfail\FqcnConcreteRgx_BlankContext' => true, $sOther => true];
        $this->assertSame('0|' . $sNotC, $obj->resolveMap($aMap[4], $sNotC, false));

        $this->assertSame(2, count($obj->getContextNamespaceFilterArr()));
        $this->assertSame(2, count($obj->getContextNamespaceFilterArr()['']));
        $this->assertSame(2, count($obj->getContextNamespaceFilterArr()[$sContext2]));

    }

    /**
     * @test
     */
    public function getPriorityRulesTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $this->assertSame(true, count($obj->getPriorityRules()) > 7);

    }

    /**
     * @test
     */
    public function addStrategyTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $sCustomStrategy = 'customStrategy';
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();

        $iStrategies = count($obj->getStrategies());
        $obj->addStrategy($sCustomStrategy, function (
            AfrToConcreteStrategiesInterface $oStrategiesInterface,
            array                            $aMap
        ) {
            if ($oStrategiesInterface->getNotConcreteFQCN() === 'extendClosureFn') {
                //fake match something
                if (isset($aMap['clFake1'])) unset($aMap['clFake1']);
            } else {
                $aMap = [];
            }
            return $aMap;
        });
        $this->assertSame($iStrategies + 1, count($obj->getStrategies()));

        $obj->addPriorityRules($fx, [$sCustomStrategy])->setPriorityRule($fx);
        $obj->setContext('');

        $this->assertSame('1|clFake2', $obj->resolveMap(
            ['clFake1' => true, 'clFake2' => true],
            'extendClosureFn'
        ));
        $this->assertSame('0|abc', $obj->resolveMap(
            ['aaa' => true, 'bbb' => true],
            'abc'
        ));

    }


    /**
     * @test
     */
    public function getStrategiesTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $this->assertSame(true, count($obj->getStrategies()) > 11);
    }

    /**
     * @test
     */
    public function strategyGetDeclaredClassesTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyGetDeclaredClasses,
        ])->setPriorityRule($fx);

        $this->assertSame(
            '1|' . AfrToConcreteStrategiesClass::class,
            $obj->resolveMap(
                [AfrToConcreteStrategiesClass::class => true, 'OtherClass' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );

        $sInt = 'Autoframe\Components\Arr\Sort\AfrArrSortBySubKeyInterface';
        $sCls = 'Autoframe\Components\Arr\Sort\AfrArrSortBySubKeyClass';
        $aCases = class_exists($sCls) ? ['1|' . $sCls] : ['0|' . $sInt, '1|' . $sCls];
        foreach ($aCases as $sExpected) {
            $this->assertSame(
                $sExpected,
                $obj->resolveMap(
                    [$sCls => true, 'OtherClass' => true],
                    $sInt,
                    false
                )
            );
            //autoload class
            class_exists($sCls, true);
        }

    }

    /**
     * @test
     */
    public function strategyOtherNamespaceThanNotInstantiableTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyOtherNamespaceThanNotInstantiable,
        ])->setPriorityRule($fx);
        $obj->setContext('');

        $this->assertSame(
            '1|OtherNs\OtherClass',
            $obj->resolveMap(
                [AfrToConcreteStrategiesClass::class => true, 'OtherNs\OtherClass' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
        $this->assertSame(
            '0|' . AfrToConcreteStrategiesInterface::class,
            $obj->resolveMap(
                ['OtherNs1\OtherClass2' => true, 'OtherNs\OtherClass' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
    }

    /**
     * @test
     */
    public function strategyProjectComposerPsrNamespacesTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyProjectComposerPsrNamespaces,
        ])->setPriorityRule($fx);
        $obj->setContext('');

        $bInsideVendorDir = strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;

        $this->assertSame(
            $bInsideVendorDir ? '0|' . AfrToConcreteStrategiesInterface::class : '1|' . AfrToConcreteStrategiesClass::class,
            $obj->resolveMap(
                [AfrToConcreteStrategiesClass::class => true, 'OtherNs\OtherClass' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );

        $this->assertSame(
            '0|' . AfrToConcreteStrategiesInterface::class,
            $obj->resolveMap(
                ['OtherNs1\OtherClass2' => true, 'OtherNs\OtherClass' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
    }


    /**
     * @test
     */
    public function strategyFirstFoundxWarningTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->setContext('');
        foreach ([
                     AfrToConcreteStrategiesClass::StrategyFirstFoundWithWarning,
                     AfrToConcreteStrategiesClass::StrategyFirstFoundWithoutWarning,
                 ] as $ii => $sStrategy) {
            $obj->addPriorityRules($fx . $ii, [$sStrategy])->setPriorityRule($fx . $ii);

            $this->assertSame(
                '1|AA\aa',
                $obj->resolveMap(
                    ['AA\aa' => true, 'BB\bb' => true],
                    'NN\nn',
                    false
                )
            );
            $this->assertSame(
                '0|NN\nn',
                $obj->resolveMap(
                    [],
                    'NN\nn',
                    false
                )
            );
        }
    }


    /**
     * @test
     */
    public function strategyShuffleTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyShuffle,
        ])->setPriorityRule($fx);
        $obj->setContext('');


        $aMap = [AfrToConcreteStrategiesClass::class => true, 'OtherNs\OtherClass' => true];
        $this->assertSame(
            true,
            in_array(
                $obj->resolveMap(
                    $aMap,
                    AfrToConcreteStrategiesInterface::class,
                    false
                ),
                ['1|' . AfrToConcreteStrategiesClass::class => true, '1|OtherNs\OtherClass' => true]
            )
        );

        $this->assertSame(
            '0|' . AfrToConcreteStrategiesInterface::class,
            $obj->resolveMap(
                [],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
    }

    /**
     * @test
     */
    public function strategyFailTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->addPriorityRules($fx, [
            AfrToConcreteStrategiesClass::StrategyFail,
        ])->setPriorityRule($fx);

        //fail
        $this->assertSame(
            '0|' . AfrToConcreteStrategiesInterface::class,
            $obj->resolveMap(
                ['AA\aa' => true, 'BB\bb' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );

        //resove if one:
        $this->assertSame(
            '1|AA\aa',
            $obj->resolveMap(
                ['AA\aa' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
    }

    /**
     * @test
     */
    public function neverFailTest(): void
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $fx = __FUNCTION__;
        $obj = AfrToConcreteStrategiesClass::getLatestInstance();
        $obj->setPriorityRule('neverFail');
        $obj->setContext('');
        $this->assertSame(
            '1|AA\aa',
            $obj->resolveMap(
                ['AA\aa' => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
        $sRand = rand(333, 444);
        $this->assertSame(
            '1|AA\aa',
            $obj->resolveMap(
                ['AA\aa' => true, 'x' . $sRand => true],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
        $this->assertSame(
            '1|x' . $sRand,
            $obj->resolveMap(
                ['x' . $sRand => true, 'AA\aa' => true,],
                AfrToConcreteStrategiesInterface::class,
                false
            )
        );
        $sContext = 'IContextN';
        $sNotConcrete = $fx . 'NeverFakeCB';
        $sConcrete = $fx . 'NeverConcreteFakeCB';

        $obj->extendStrategyContextBound(
            $sNotConcrete,
            $sConcrete,
            $sContext
        );

        $obj->setContext('IContextN');
        $this->assertSame(
            '1|' . $sConcrete,
            $obj->resolveMap(
                ['xxf\ddd' . $sRand => true, $sConcrete => true],
                $sNotConcrete,
                false
            )
        );
        //etc

    }
}