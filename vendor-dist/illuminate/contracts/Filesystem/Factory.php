<?php

namespace WicketAcc\Illuminate\Contracts\Filesystem;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param  string|null  $name
     * @return \WicketAcc\Illuminate\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);
}
