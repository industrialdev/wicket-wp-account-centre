<?php

namespace WicketAcc\Illuminate\Support;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Ulid;
use WicketAcc\Carbon\Carbon as BaseCarbon;
use WicketAcc\Carbon\CarbonImmutable as BaseCarbonImmutable;
use WicketAcc\Illuminate\Support\Traits\Conditionable;

class Carbon extends BaseCarbon
{
    use Conditionable;

    /**
     * @inheritdoc
     */
    public static function setTestNow($testNow = null)
    {
        BaseCarbon::setTestNow($testNow);
        BaseCarbonImmutable::setTestNow($testNow);
    }

    /**
     * Create a Carbon instance from a given ordered UUID or ULID.
     *
     * @param  Uuid|Ulid|string  $id
     * @return Carbon
     */
    public static function createFromId($id)
    {
        if (is_string($id)) {
            $id = Ulid::isValid($id) ? Ulid::fromString($id) : Uuid::fromString($id);
        }

        return static::createFromInterface($id->getDateTime());
    }

    /**
     * Dump the instance and end the script.
     *
     * @param  mixed  ...$args
     * @return never
     */
    public function dd(...$args)
    {
        dd($this, ...$args);
    }

    /**
     * Dump the instance.
     *
     * @return $this
     */
    public function dump()
    {
        dump($this);

        return $this;
    }
}
