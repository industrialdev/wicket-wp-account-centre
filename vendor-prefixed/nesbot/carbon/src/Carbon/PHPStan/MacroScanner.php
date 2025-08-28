<?php

/**
 * This file is part of the Carbon package.
 *
 * (c) Brian Nesbitt <brian@nesbot.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WicketAcc\Carbon\PHPStan;

use PHPStan\Reflection\ReflectionProvider;
use ReflectionClass;
use ReflectionException;
use WicketAcc\Carbon\CarbonInterface;

final class MacroScanner
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * MacroScanner constructor.
     *
     * @param ReflectionProvider $reflectionProvider
     */
    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * Return true if the given pair class-method is a Carbon macro.
     *
     * @param class-string $className
     * @param string       $methodName
     *
     * @return bool
     */
    public function hasMethod(string $className, string $methodName): bool
    {
        $classReflection = $this->reflectionProvider->getClass($className);

        if (
            $classReflection->getName() !== CarbonInterface::class &&
            !$classReflection->isSubclassOf(CarbonInterface::class)
        ) {
            return false;
        }

        return \is_callable([$className, 'hasMacro']) &&
            $className::hasMacro($methodName);
    }

    /**
     * Return the Macro for a given pair class-method.
     *
     * @param class-string $className
     * @param string       $methodName
     *
     * @throws ReflectionException
     *
     * @return Macro
     */
    public function getMethod(string $className, string $methodName): Macro
    {
        $reflectionClass = new ReflectionClass($className);
        $property = $reflectionClass->getProperty('globalMacros');

        $property->setAccessible(true);
        $macro = $property->getValue()[$methodName];

        return new Macro(
            $className,
            $methodName,
            $macro
        );
    }
}
