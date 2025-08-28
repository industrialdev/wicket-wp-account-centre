<?php

namespace Carbon_Fields\Event;

class SingleEventListener extends PersistentListener
{
    /**
     * Flag if the event has been called.
     *
     * @var bool
     */
    protected $called = false;

    /**
     * @inheritDoc
     */
    public function is_valid()
    {
        return !$this->called;
    }

    /**
     * @inheritDoc
     */
    public function notify()
    {
        $this->called = true;

        return call_user_func_array([$this, 'parent::notify'], func_get_args());
    }
}
