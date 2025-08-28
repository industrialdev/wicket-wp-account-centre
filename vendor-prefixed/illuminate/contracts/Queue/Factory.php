<?php

namespace WicketAcc\Illuminate\Contracts\Queue;

interface Factory
{
    /**
     * Resolve a queue connection instance.
     *
     * @param  string|null  $name
     * @return Queue
     */
    public function connection($name = null);
}
