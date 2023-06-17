<?php

namespace Autoframe\InterfaceToConcrete;


use Autoframe\ClassDependency\AfrClassDependencyException;
use Autoframe\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;

/**
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
 * This will make a configuration object that contains the paths to be wired:
 *
 * $oAfrConfigWiredPaths = new AfrConfigWiredPaths(['src','vendor']);
 * AfrMultiClassMapper::setAfrConfigWiredPaths($oAfrConfigWiredPaths);
 * AfrMultiClassMapper::xetRegenerateAll(true/false);
 * register_shutdown_function(function(){ print_r(AfrClassDependency::getDependencyInfo());});
 * $aMaps = AfrMultiClassMapper::getInterfaceToConcrete();
 */
interface AfrInterfaceToConcreteInterface
{
    /**
     * @return array
     * @throws AfrInterfaceToConcreteException
     * @throws AfrClassDependencyException
     */
    public function getClassInterfaceToConcrete(): array;

    /**
     * @return AfrInterfaceToConcreteInterface|null
     */
    public static function getLatestInstance(): ?AfrInterfaceToConcreteInterface;

    /**
     * @return array
     */
    public function getEnvSettings(): array;

    /**
     * @param string $s
     * @return string
     */
    public function hashV(string $s): string;

    /**
     * @return array
     */
    public function getPaths(): array;

    /**
     * @return AfrToConcreteStrategiesInterface
     */
    public function getAfrToConcreteStrategies(): AfrToConcreteStrategiesInterface;

    /**
     * @param AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies
     * @return AfrToConcreteStrategiesInterface
     */
    public function setAfrToConcreteStrategies(AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies): AfrToConcreteStrategiesInterface;

    /**
     * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @param string $sNotConcreteFQCN
     * @param string|null $sTemporaryContextOverwrite
     * @param string|null $sTemporaryPriorityRuleOverwrite
     * @return string
     * @throws AfrClassDependencyException
     * @throws AfrInterfaceToConcreteException
     */
    public function resolve(
        string $sNotConcreteFQCN,
        string $sTemporaryContextOverwrite = null,
        string $sTemporaryPriorityRuleOverwrite = null
    ): string;

}