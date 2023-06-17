<?php

namespace Autoframe\InterfaceToConcrete;

use Autoframe\ClassDependency\AfrClassDependency;
use Autoframe\ClassDependency\AfrClassDependencyException;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;

class AfrToConcreteStrategiesClass implements AfrToConcreteStrategiesInterface
{
    //fixed solving
    public const StrategyClosureFn = 'StrategyClosureFn';

    //context
    public const StrategyContextBound = 'StrategyContextBound'; //multiple
    public const StrategyContextNamespaceFilterArr = 'StrategyContextNamespaceFilterArr';//multiple
    public const StrategyContextHttpRequestUriRegex = 'StrategyContextHttpRequestUriRegex'; //multiple

    //multiple
    public const StrategyGetDeclaredClasses = 'StrategyGetDeclaredClasses'; //multiple
    public const StrategyOtherNamespaceThanNotInstantiable = 'StrategyOtherNamespaceThanNotInstantiable'; //multiple
    public const StrategyProjectComposerPsrNamespaces = 'StrategyProjectComposerPsrNamespaces'; //multiple

    //Last try
    public const StrategyFirstFoundWithWarning = 'StrategyFirstFoundWithWarning';//single
    public const StrategyFirstFoundWithoutWarning = 'StrategyFirstFoundWithoutWarning';//single
    public const StrategyShuffle = 'StrategyShuffle';//single
    public const StrategyFail = 'StrategyFail'; //empty

    protected static AfrToConcreteStrategiesInterface $oLatestInstance;
    protected string $sPriorityRule = 'neverFail';
    protected string $sContext = '';
    protected string $notConcreteFQCN = '';
    protected array $aStrategies;
    protected array $aClosureFns = [];
    protected array $aContextBound = [];
    protected array $aContextHttpRequestUriRegex = [];
    protected array $aContextNamespaceFilterArr = [];
    protected array $aCache = [];

    //you can change / reorder / overwrite any using $this->addPriorityRules as you see fit
    protected array $aPriorityRules = [


        'fail' => [ //when the bound is nor defined into Laravel controller
            self::StrategyFail,  //empty
        ],
        //only if a custom closure rule is set, then resolve, but you can so this in Laravel controller
        'onlyClosureFn' => [
            self::StrategyClosureFn, //multiple
            self::StrategyFail,  //empty
        ],
        //advanced
        'neverFail' => [
            self::StrategyClosureFn, //multiple
            self::StrategyContextBound, //single
            self::StrategyContextNamespaceFilterArr, //multiple
            self::StrategyContextHttpRequestUriRegex, //multiple
            self::StrategyGetDeclaredClasses, //multiple
            self::StrategyOtherNamespaceThanNotInstantiable, //multiple
            self::StrategyProjectComposerPsrNamespaces, //multiple
            self::StrategyFirstFoundWithWarning,  //single
            //    self::StrategyFirstFoundWithoutWarning,  //single
            //    self::StrategyShuffle,  //single
            //    self::StrategyFail,  //empty
        ],
        'neverFailWithoutWarnings' => [
            self::StrategyClosureFn, //multiple
            self::StrategyContextBound, //single
            self::StrategyContextNamespaceFilterArr, //multiple
            self::StrategyContextHttpRequestUriRegex, //multiple
            self::StrategyGetDeclaredClasses, //multiple
            self::StrategyOtherNamespaceThanNotInstantiable, //multiple
            self::StrategyProjectComposerPsrNamespaces, //multiple
            //self::StrategyFirstFoundWithWarning,  //single
            self::StrategyFirstFoundWithoutWarning,  //single
            //    self::StrategyShuffle,  //single
            //    self::StrategyFail,  //empty
        ],

    ];

    public function __construct()
    {
        self::$oLatestInstance = $this;
    }

    /**
     * Get the latest initiation or instantiate self
     * @return AfrToConcreteStrategiesInterface
     */
    public static function getLatestInstance(): AfrToConcreteStrategiesInterface
    {
        if (empty(self::$oLatestInstance)) {
            self::$oLatestInstance = new static();
        }
        return self::$oLatestInstance;
    }

    /**
     * Defined in $this->resolveMap(...)
     * @return string
     */
    public function getNotConcreteFQCN(): string
    {
        return $this->notConcreteFQCN;
    }

    /**
     * @param string $sContext
     * @return AfrToConcreteStrategiesInterface
     */
    public function setContext(string $sContext): AfrToConcreteStrategiesInterface
    {
        $this->sContext = $sContext;
        return $this;
    }

    /**
     * @return string
     */
    public function getContext(): string
    {
        return $this->sContext;
    }


    /**
     * callable(AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap):array $aMap[$sFQCN=>'1:2',$sFQCN2=>'1:2',];
     * 1 is instantiable and 2 is singleton
     * @param callable $closure
     * @return AfrToConcreteStrategiesInterface
     */
    public function extendStrategyClosureFn(callable $closure): AfrToConcreteStrategiesInterface
    {
        $this->aClosureFns[] = $closure;
        $this->aCache = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getClosureFns(): array
    {
        return $this->aClosureFns;
    }

    /**
     * @param string $sNotConcrete
     * @param string $sConcrete
     * @param string $sContext
     * @return AfrToConcreteStrategiesInterface
     */
    public function extendStrategyContextBound(
        string $sNotConcrete,
        string $sConcrete,
        string $sContext = ''
    ): AfrToConcreteStrategiesInterface
    {
        $this->aContextBound[$sContext][$sNotConcrete] = $sConcrete;
        $this->aCache = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getContextBounded(): array
    {
        return $this->aContextBound;
    }

    /**
     * @param string $sNotConcrete
     * @param string $sConcrete
     * @param string $sRegex
     * @param string $sContext
     * @return AfrToConcreteStrategiesInterface
     */
    public function extendStrategyContextHttpRequestUriRegex(
        string $sNotConcrete,
        string $sConcrete,
        string $sRegex,
        string $sContext = ''
    ): AfrToConcreteStrategiesInterface
    {
        $this->aContextHttpRequestUriRegex[$sContext][$sNotConcrete][$sConcrete] = $sRegex;
        $this->aCache = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getContextHttpRequestUriRegex(): array
    {
        return $this->aContextHttpRequestUriRegex;
    }

    /**
     * @param string $sNamespace
     * @param string $sContext
     * @return AfrToConcreteStrategiesInterface
     */
    public function extendStrategyStrategyContextNamespaceFilterArr(
        string $sNamespace,
        string $sContext = '',
    ): AfrToConcreteStrategiesInterface
    {
        $this->aContextNamespaceFilterArr[$sContext][$sNamespace] = true;
        $this->aCache = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getContextNamespaceFilterArr(): array
    {
        return $this->aContextNamespaceFilterArr;
    }


    /**
     * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @param string $notConcreteFQCN
     * @param AfrInterfaceToConcreteInterface $oAfrInterfaceToConcreteInterface
     * @param bool $bCache
     * @return string
     * @throws AfrClassDependencyException
     * @throws AfrInterfaceToConcreteException
     */
    public function resolveInterfaceToConcrete(
        string                          $notConcreteFQCN,
        AfrInterfaceToConcreteInterface $oAfrInterfaceToConcreteInterface,
        bool                            $bCache = true
    ): string
    {
        $aMap = $oAfrInterfaceToConcreteInterface->getClassInterfaceToConcrete();
        $this->notConcreteFQCN = $notConcreteFQCN;
        return $this->resolveMap(
            !empty($aMap[$notConcreteFQCN]) && is_array($aMap[$notConcreteFQCN]) ? $aMap[$notConcreteFQCN] : [],
            $notConcreteFQCN,
            $bCache
        );
    }

    /**
     * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @param array $aMappings
     * @param string $notConcreteFQCN
     * @param bool $bCache
     * @return string
     */
    public function resolveMap(array $aMappings, string $notConcreteFQCN, bool $bCache = true): string
    {
        $this->notConcreteFQCN = $notConcreteFQCN;
        $sCacheKey = $this->getPriorityRule() . '|' . $this->getContext() . '|' . $notConcreteFQCN;
        if ($bCache && isset($this->aCache[$sCacheKey])) {
            return $this->aCache[$sCacheKey];
        }

        foreach ($aMappings as $implementingClass => $bInstantiable) {
            //default values are bool, but known overwrites are allowed: 0,1,2
            if (
                $bInstantiable === true ||
                $bInstantiable === 1 || $bInstantiable === '1' ||
                $bInstantiable === 2 || $bInstantiable === '2'
            ) {
                //1:instantiable; 2:singleton pretested
                $aMappings[$implementingClass] = (string)$bInstantiable;
                continue;
            } elseif ($bInstantiable === false) {
                if (AfrClassDependency::getClassInfo($implementingClass)->isSingleton()) {
                    $aMappings[$implementingClass] = '2';
                    continue;
                }
            }
            unset($aMappings[$implementingClass]);
        }
        //only one possible mapping, so go fo it!
        if (count($aMappings) === 1) {
            foreach ($aMappings as $implementingClass => $sInstantiable) {
                if ($bCache) {
                    $this->aCache[$sCacheKey] = $sInstantiable . '|' . $implementingClass;
                }
                return $sInstantiable . '|' . $implementingClass;
            }
        }
        //more than one or none

        $this->initStrategyHandlers();
        if (count($aMappings) < 1) {
            return $this->aCache[$sCacheKey] = '0|' . $notConcreteFQCN;
        }
        foreach ($this->getPriorityRules()[$this->sPriorityRule] as $sStrategyClosureName) {
            if (!isset($this->aStrategies[$sStrategyClosureName])) {
                trigger_error('Undefined strategy: ' . $sStrategyClosureName);
                continue;
            }

            $aFoundMappings = $this->aStrategies[$sStrategyClosureName]($this, $aMappings);
            if (!is_array($aFoundMappings)) {
                if ($sStrategyClosureName === self::StrategyFail) {
                    break;
                }
                trigger_error('Closure strategy must return array(sFQCN=>1|2,sFQCN=>1|2,)');
                continue;
            }
            $iFoundMappings = count($aFoundMappings);
            if ($iFoundMappings === 0) {
                //nothing was validated so we continue with the next strategy
                continue;
            } elseif ($iFoundMappings === 1) {
                //one match found!
                foreach ($aFoundMappings as $implementingClass => $sInstantiable) {
                    if ($bCache) {
                        $this->aCache[$sCacheKey] = $sInstantiable . '|' . $implementingClass;
                    }
                    return $sInstantiable . '|' . $implementingClass;
                }
            } else {
                // update mappings array with new obtain array
                $aMappings = $aFoundMappings;
            }
        }

        return $this->aCache[$sCacheKey] = '0|' . $notConcreteFQCN;
    }

    /**
     * @param string $sPriorityRule
     * @return AfrToConcreteStrategiesInterface
     * @throws AfrInterfaceToConcreteException
     */
    public function setPriorityRule(string $sPriorityRule): AfrToConcreteStrategiesInterface
    {
        if (!isset($this->aPriorityRules[$sPriorityRule])) {
            throw new AfrInterfaceToConcreteException('Priority rule not defined');
        }
        $this->sPriorityRule = $sPriorityRule;
        return $this;
    }

    /**
     * @return string
     */
    public function getPriorityRule(): string
    {
        return $this->sPriorityRule;
    }


    /**
     * @return array
     */
    public function getPriorityRules(): array
    {
        return $this->aPriorityRules;
    }


    /**
     * @param string $sName
     * @param array $aPriorities
     * @return self
     * @throws AfrInterfaceToConcreteException
     */
    public function addPriorityRules(string $sName, array $aPriorities): AfrToConcreteStrategiesInterface
    {
        $this->initStrategyHandlers();
        foreach ($aPriorities as $sKey => $sStrategy) {
            if (!isset($this->aStrategies[$sStrategy])) {
                throw new AfrInterfaceToConcreteException('Strategy $aPriorities[' . $sKey . '] =' . $sStrategy . ' is not registered!');
            }
        }
        $this->aPriorityRules[$sName] = $aPriorities;
        $this->aCache = [];
        return $this;
    }

    /**
     * @param string $sName
     * @param callable $closure
     * @return self
     */
    public function addStrategy(string $sName, callable $closure): AfrToConcreteStrategiesInterface
    {
        $this->initStrategyHandlers();
        $this->aStrategies[$sName] = $closure;
        $this->aCache = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getStrategies(): array
    {
        $this->initStrategyHandlers();
        return $this->aStrategies;
    }


    /**
     * @return void
     */
    protected function initStrategyHandlers(): void
    {
        if (!empty($this->aStrategies)) {
            return;
        }
        $this->aStrategies = [

            /**
             * Returns multiple or single matches or empty on fail
             * Muse be callable(AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap)
             */
            self::StrategyClosureFn => function (
                AfrToConcreteStrategiesInterface $oStrategiesInterface,
                array                            $aMap
            ): array {
                foreach ($oStrategiesInterface->getClosureFns() as $callable) {
                    $aNewMap = $callable($oStrategiesInterface, $aMap);
                    if (is_array($aNewMap) && count($aNewMap) > 0) {
                        $aMatched = [];
                        foreach ($aNewMap as $sResolvedClass => $m) {
                            if (isset($aMap[$sResolvedClass])) {
                                $aMatched[$sResolvedClass] = $aMap[$sResolvedClass];
                            }
                        }
                        if (count($aMatched) > 0) {
                            return $aMatched;
                        }
                    }
                }
                return [];
            },

            /**
             * Returns single match or empty on fail
             * Requires a valid context and a binding to be previously defined
             */
            self::StrategyContextBound => function (
                AfrToConcreteStrategiesInterface $oStrategiesInterface,
                array                            $aMap
            ): array {
                $sContext = $oStrategiesInterface->getContext();
                $aContext = $sContext ? [$sContext, ''] : [''];
                foreach ($aContext as $sContextToCheck) {
                    $sBound = $this->aContextBound[$sContextToCheck][$oStrategiesInterface->getNotConcreteFQCN()] ?? '';
                    if ($sBound && isset($aMap[$sBound])) {
                        return [$sBound => $aMap[$sBound]];
                    }
                }

                return [];
            },

            /**
             * Returns multiple or single matches or empty on fail
             */
            self::StrategyContextHttpRequestUriRegex => function (
                AfrToConcreteStrategiesInterface $obj,
                array                            $aMap
            ): array {
                if (empty($_SERVER['REQUEST_URI'])) {
                    return [];
                }
                $sContext = $obj->getContext();
                $aContext = $sContext ? [$sContext, ''] : [''];
                foreach ($aContext as $sContextToCheck) {
                    $aToCheck = $this->aContextHttpRequestUriRegex[$sContextToCheck][$obj->getNotConcreteFQCN()] ?? [];
                    $aMatched = [];
                    foreach ($aToCheck as $sConcrete => $sRegex) {
                        if (preg_match($sRegex, $_SERVER['REQUEST_URI'])) {
                            $aMatched[$sConcrete] = $aMap[$sConcrete];
                        }
                    }
                    if ($aMatched) {
                        return $aMatched;
                    }
                }
                return [];

            },

            self::StrategyContextNamespaceFilterArr => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap): array {
                //extract class name from namespace
                $sContext = $oStrategiesInterface->getContext();
                $aContext = $sContext ? [$sContext, ''] : [''];
                foreach ($aContext as $sContextToCheck) {
                    $aMatched = [];
                    $aToCheck = $this->getContextNamespaceFilterArr()[$sContextToCheck] ?? [];
                    foreach ($aToCheck as $sNamespace => $b) {
                        $bMatchChildNamespaces = substr($sNamespace, -1, 1) === '\\';
                        if (!$bMatchChildNamespaces) {
                            $sNamespace .= '\\';
                        }
                        foreach ($aMap as $sImplementation => $bInstantiable) {
                            $sMapNs = substr($sImplementation, 0, -strpos(strrev($sImplementation), '\\'));
                            if (
                                //exact match
                                $sNamespace === $sMapNs ||
                                //$sMapNs resides into a child namespace compared to $sNamespace
                                $bMatchChildNamespaces && $sNamespace === substr($sMapNs, 0, strlen($sNamespace))
                            ) {
                                $aMatched[$sImplementation] = $bInstantiable;
                            }
                        }
                    }
                    if (count($aMatched) > 0) {
                        return $aMatched;
                    }
                }
                return [];
            },


            /**
             * If the class is loaded into php's memory, go for it!
             */
            self::StrategyGetDeclaredClasses => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap): array {
                $aMatched = [];
                foreach (get_declared_classes() as $sDeclared) {
                    if (isset($aMap[$sDeclared])) {
                        $aMatched[$sDeclared] = $aMap[$sDeclared];
                    }
                }
                return $aMatched;
            },


            /**
             * It will match in the namespaces that are different from the interface namespace
             */
            self::StrategyOtherNamespaceThanNotInstantiable => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap): array {
                $notConcrete = $oStrategiesInterface->getNotConcreteFQCN();
                //extract class name from namespace
                $sBaseNamespace = substr($notConcrete, 0, -strpos(strrev($notConcrete), '\\'));
                $aMatched = [];
                foreach ($aMap as $implementingClass => $bInstantiable) {
                    if ($sBaseNamespace !== substr($implementingClass, 0, -strpos(strrev($implementingClass), '\\'))) {
                        $aMatched[$implementingClass] = $bInstantiable;
                    }
                }
                return $aMatched;
            },


            /**
             * Match multiple if the instantiable class is loaded under a composer autoload psr namespace
             */
            self::StrategyProjectComposerPsrNamespaces => function (
                AfrToConcreteStrategiesInterface $oStrategiesInterface
                , array                          $aMap
            ): array {
                $aComposerConfig = AfrVendorPath::getComposerJson();
                $aAutoload = [];
                foreach (['autoload', 'autoload-dev'] as $autoload) {
                    foreach (['psr-4', 'psr-0', 'psr'] as $psr) {
                        if (isset($aComposerConfig[$autoload][$psr])) {
                            $aAutoload = array_merge($aAutoload, (array)$aComposerConfig[$autoload][$psr]);
                        }
                    }
                }
                $aMatched = [];
                foreach ($aAutoload as $sNamespace => $mPaths) {
                    $iNsLen = strlen($sNamespace);
                    foreach ($aMap as $implementingClass => $bInstantiable) {
                        if (substr($implementingClass, 0, $iNsLen) === $sNamespace) {
                            $aMatched[$implementingClass] = $bInstantiable;
                        }
                    }
                }
                return $aMatched;
            },


            /**
             * Returns the first mapping in the map array and emits a warning.
             * Be warned that the order may change in the mappings from the drive
             */
            self::StrategyFirstFoundWithWarning => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap): array {
                foreach ($aMap as $implementingClass => $bInstantiable) {
                    if (!class_exists('PHPUnit\Framework\TestCase', false)) {
                        unset($aMap[$implementingClass]);
                        trigger_error(
                            $oStrategiesInterface->getNotConcreteFQCN() . ' was auto-resolved as ' . $implementingClass .
                            '; Other options are: ' . implode(', ', array_keys($aMap))
                        );
                    }
                    return [$implementingClass => $bInstantiable];
                }
                return [];
            },


            /**
             * Returns the first mapping in the map array without any warning
             * Be warned that the order may change in the mappings from the drive
             */
            self::StrategyFirstFoundWithoutWarning => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap): array {
                foreach ($aMap as $implementingClass => $bInstantiable) {
                    return [$implementingClass => $bInstantiable];
                }
                return [];
            },


            /**
             * Randomly returns a single mapping :)
             */
            self::StrategyShuffle => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap): array {
                shuffle($aMap);
                foreach ($aMap as $implementingClass => $bInstantiable) {
                    return [$implementingClass => $bInstantiable];
                }
                return [];
            },


            /**
             * Returns null. The mapping fails
             */
            self::StrategyFail => function (AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap) {
                return null;
            },

        ];
    }


}