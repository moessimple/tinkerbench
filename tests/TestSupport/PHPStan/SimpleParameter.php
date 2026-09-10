<?php

declare(strict_types=1);

namespace Tests\TestSupport\PHPStan;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

/**
 * A required, by-value, non-variadic parameter for a synthetic method signature.
 */
final class SimpleParameter implements ParameterReflection
{
    public function __construct(
        private readonly string $name,
        private readonly Type $type,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function passedByReference(): PassedByReference
    {
        return PassedByReference::createNo();
    }

    public function isVariadic(): bool
    {
        return false;
    }

    public function getDefaultValue(): ?Type
    {
        return null;
    }
}
