<?php

namespace WicketAcc\Illuminate\Contracts\Auth;

interface PasswordBrokerFactory
{
    /**
     * Get a password broker instance by name.
     *
     * @param  string|null  $name
     * @return PasswordBroker
     */
    public function broker($name = null);
}
