<?php

declare(strict_types=1);

namespace WicketAcc\Carbon\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\VarDateTimeImmutableType;
use WicketAcc\Carbon\CarbonImmutable;

class DateTimeImmutableType extends VarDateTimeImmutableType implements CarbonDoctrineType
{
    /** @use \CarbonTypeConverter<CarbonImmutable> */
    use CarbonTypeConverter;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CarbonImmutable
    {
        return $this->doConvertToPHPValue($value);
    }

    /**
     * @return class-string<CarbonImmutable>
     */
    protected function getCarbonClassName(): string
    {
        return CarbonImmutable::class;
    }
}
