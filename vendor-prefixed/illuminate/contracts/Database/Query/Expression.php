<?php

namespace WicketAcc\Illuminate\Contracts\Database\Query;

use Illuminate\Database\Grammar;

interface Expression
{
    /**
     * Get the value of the expression.
     *
     * @param  Grammar  $grammar
     * @return string|int|float
     */
    public function getValue(Grammar $grammar);
}
