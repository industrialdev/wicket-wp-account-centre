<?php

declare(strict_types=1);

namespace WicketAcc\Carbon\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\VarDateTimeType;

class DateTimeType extends VarDateTimeType implements CarbonDoctrineType
{
    /** @use \CarbonTypeConverter<WicketAcc\Carbon> */
    use CarbonTypeConverter;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?WicketAcc\Carbon
    {
        return $this->doConvertToPHPValue($value);
    }
}
