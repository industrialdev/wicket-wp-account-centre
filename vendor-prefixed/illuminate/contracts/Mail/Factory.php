<?php

namespace WicketAcc\Illuminate\Contracts\Mail;

interface Factory
{
    /**
     * Get a mailer instance by name.
     *
     * @param  string|null  $name
     * @return \WicketAcc\Illuminate\Contracts\Mail\Mailer
     */
    public function mailer($name = null);
}
