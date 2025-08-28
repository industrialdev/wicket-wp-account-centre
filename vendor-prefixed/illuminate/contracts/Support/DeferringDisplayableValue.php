<?php

namespace WicketAcc\Illuminate\Contracts\Support;

interface DeferringDisplayableValue
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \WicketAcc\Illuminate\Contracts\Support\Htmlable|string
     */
    public function resolveDisplayableValue();
}
