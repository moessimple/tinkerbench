<?php

declare(strict_types=1);

namespace Tests\TestSupport\PHPStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\StringType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;

/**
 * A single custom expectation from tests/Pest.php, presented to PHPStan as a public method on
 * Pest\Expectation that takes one string and returns the same expectation for chaining.
 */
final class PestCustomExpectationMethodReflection implements MethodReflection
{
    public function __construct(
        private readonly ClassReflection $classReflection,
        private readonly string $methodName,
    ) {}

    public function getDeclaringClass(): ClassReflection
    {
        return $this->classReflection;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return $this->methodName;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    /** @return list<FunctionVariant> */
    public function getVariants(): array
    {
        $parameters = $this->methodName === 'toBeOne' ? [] : [
            new SimpleParameter(
                $this->methodName === 'toUseMiddleware' ? 'middleware' : 'type',
                new StringType(),
            ),
        ];

        return [
            new FunctionVariant(
                TemplateTypeMap::createEmpty(),
                null,
                $parameters,
                false,
                new ThisType($this->classReflection),
            ),
        ];
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createYes();
    }
}
