<?php

namespace Autoframe\InterfaceToConcrete;

use Autoframe\ClassDependency\AfrClassDependencyException;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;

interface AfrToConcreteStrategiesInterface
{
    /**
     * Get the latest initiation or instantiate self
     * @return AfrToConcreteStrategiesInterface
     */
    public static function getLatestInstance(): AfrToConcreteStrategiesInterface;

    /**
     * Defined in AfrToConcreteStrategiesInterface->resolve(...)
     * @return string
     */
    public function getNotConcreteFQCN(): string;

    /**
     * @param string $sContext
     * @return AfrToConcreteStrategiesInterface
     */
    public function setContext(string $sContext): AfrToConcreteStrategiesInterface;

    /**
     * @return string
     */
    public function getContext(): string;

    /**
     * callable(AfrToConcreteStrategiesInterface $oStrategiesInterface, array $aMap):array $aMap[$sFQCN=>'1:2',$sFQCN2=>'1:2',];
     * 1 is instantiable and 2 is singleton
     * @param callable $closure
     * @return AfrToConcreteStrategiesInterface
     */
    public function extendStrategyClosureFn(callable $closure): AfrToConcreteStrategiesInterface;

    /**
     * @return array
     */
    public function getClosureFns(): array;

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
    ): AfrToConcreteStrategiesInterface;

    /**
     * @return array
     */
    public function getContextBounded(): array;

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
    ): AfrToConcreteStrategiesInterface;

    /**
     * @return array
     */
    public function getContextHttpRequestUriRegex(): array;

    /**
     * @param string $sNamespace
     * @param string $sContext
     * @return AfrToConcreteStrategiesInterface
     */
    public function extendStrategyStrategyContextNamespaceFilterArr(
        string $sNamespace,
        string $sContext = ''
    ): AfrToConcreteStrategiesInterface;

    /**
     * @return array
     */
    public function getContextNamespaceFilterArr(): array;

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
        string $notConcreteFQCN,
        AfrInterfaceToConcreteInterface $oAfrInterfaceToConcreteInterface,
        bool $bCache = true
    ): string;

    /**
     * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @param array $aMappings
     * @param string $notConcreteFQCN
     * @param bool $bCache
     * @return string
     */
    public function resolveMap(array $aMappings, string $notConcreteFQCN, bool $bCache = true): string;

    /**
     * @param string $sPriorityRule
     * @return AfrToConcreteStrategiesInterface
     * @throws AfrInterfaceToConcreteException
     */
    public function setPriorityRule(string $sPriorityRule): AfrToConcreteStrategiesInterface;

    /**
     * @return string
     */
    public function getPriorityRule(): string;

    /**
     * @return array
     */
    public function getPriorityRules(): array;

    /**
     * @param string $sName
     * @param array $aPriorities
     * @return AfrToConcreteStrategiesInterface
     * @throws AfrInterfaceToConcreteException
     */
    public function addPriorityRules(string $sName, array $aPriorities): AfrToConcreteStrategiesInterface;

    /**
     * @param string $sName
     * @param callable $closure
     * @return AfrToConcreteStrategiesInterface
     */
    public function addStrategy(string $sName, callable $closure): AfrToConcreteStrategiesInterface;

    /**
     * @return array
     */
    public function getStrategies(): array;
}