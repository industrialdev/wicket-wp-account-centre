<?php

namespace WicketAcc\Illuminate\Contracts\Mail;

interface Factory
{
    /**
     * Get a mailer instance by name.
     *
     * @param  string|null  $name
     * @return Mailer
     */
    public function mailer($name = null);
}
