<?php

namespace WicketAcc\Illuminate\Support\Testing\Fakes;

use Illuminate\Bus\PendingBatch;
use WicketAcc\Illuminate\Support\Collection;

class PendingBatchFake extends PendingBatch
{
    /**
     * The fake bus instance.
     *
     * @var \WicketAcc\Illuminate\Support\Testing\Fakes\BusFake
     */
    protected $bus;

    /**
     * Create a new pending batch instance.
     *
     * @param  \WicketAcc\Illuminate\Support\Testing\Fakes\BusFake  $bus
     * @param  \WicketAcc\Illuminate\Support\Collection  $jobs
     * @return void
     */
    public function __construct(BusFake $bus, Collection $jobs)
    {
        $this->bus = $bus;
        $this->jobs = $jobs;
    }

    /**
     * Dispatch the batch.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function dispatch()
    {
        return $this->bus->recordPendingBatch($this);
    }

    /**
     * Dispatch the batch after the response is sent to the browser.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function dispatchAfterResponse()
    {
        return $this->bus->recordPendingBatch($this);
    }
}
