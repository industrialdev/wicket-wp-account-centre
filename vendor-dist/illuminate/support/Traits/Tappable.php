<?php

namespace WicketAcc\Illuminate\Support\Traits;

trait Tappable
{
    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param  callable|null  $callback
     * @return $this|\WicketAcc\Illuminate\Support\HigherOrderTapProxy
     */
    public function wicketacc_tap($callback = null)
    {
        return wicketacc_tap($this, $callback);
    }
}
