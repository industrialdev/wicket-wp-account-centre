<?php

namespace WicketAcc\Illuminate\Contracts\Support;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \WicketAcc\Illuminate\Contracts\Support\MessageBag
     */
    public function getMessageBag();
}
