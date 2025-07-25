<?php

namespace WicketAcc\Illuminate\Support\Testing\Fakes;

use Closure;
use WicketAcc\Illuminate\Contracts\Mail\Factory;
use WicketAcc\Illuminate\Contracts\Mail\Mailable;
use WicketAcc\Illuminate\Contracts\Mail\Mailer;
use WicketAcc\Illuminate\Contracts\Mail\MailQueue;
use WicketAcc\Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\MailManager;
use WicketAcc\Illuminate\Support\Traits\ForwardsCalls;
use WicketAcc\Illuminate\Support\Traits\ReflectsClosures;
use PHPUnit\Framework\Assert as PHPUnit;

class MailFake implements Factory, Fake, Mailer, MailQueue
{
    use ForwardsCalls, ReflectsClosures;

    /**
     * The mailer instance.
     *
     * @var MailManager
     */
    public $manager;

    /**
     * The mailer currently being used to send a message.
     *
     * @var string
     */
    protected $currentMailer;

    /**
     * All of the mailables that have been sent.
     *
     * @var array
     */
    protected $mailables = [];

    /**
     * All of the mailables that have been queued.
     *
     * @var array
     */
    protected $queuedMailables = [];

    /**
     * Create a new mail fake.
     *
     * @param  MailManager  $manager
     * @return void
     */
    public function __construct(MailManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Assert if a mailable was sent based on a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|int|null  $callback
     * @return void
     */
    public function assertSent($mailable, $callback = null)
    {
        [$mailable, $callback] = $this->prepareMailableAndCallback($mailable, $callback);

        if (is_numeric($callback)) {
            return $this->assertSentTimes($mailable, $callback);
        }

        $message = "The expected [{$mailable}] mailable was not sent.";

        if (count($this->queuedMailables) > 0) {
            $message .= ' Did you mean to use assertQueued() instead?';
        }

        PHPUnit::assertTrue(
            $this->sent($mailable, $callback)->count() > 0,
            $message
        );
    }

    /**
     * Assert if a mailable was sent a number of times.
     *
     * @param  string  $mailable
     * @param  int  $times
     * @return void
     */
    protected function assertSentTimes($mailable, $times = 1)
    {
        $count = $this->sent($mailable)->count();

        PHPUnit::assertSame(
            $times, $count,
            "The expected [{$mailable}] mailable was sent {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if a mailable was not sent or queued to be sent based on a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|null  $callback
     * @return void
     */
    public function assertNotOutgoing($mailable, $callback = null)
    {
        $this->assertNotSent($mailable, $callback);
        $this->assertNotQueued($mailable, $callback);
    }

    /**
     * Determine if a mailable was not sent based on a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|null  $callback
     * @return void
     */
    public function assertNotSent($mailable, $callback = null)
    {
        [$mailable, $callback] = $this->prepareMailableAndCallback($mailable, $callback);

        PHPUnit::assertCount(
            0, $this->sent($mailable, $callback),
            "The unexpected [{$mailable}] mailable was sent."
        );
    }

    /**
     * Assert that no mailables were sent or queued to be sent.
     *
     * @return void
     */
    public function assertNothingOutgoing()
    {
        $this->assertNothingSent();
        $this->assertNothingQueued();
    }

    /**
     * Assert that no mailables were sent.
     *
     * @return void
     */
    public function assertNothingSent()
    {
        $mailableNames = wicketacc_collect($this->mailables)->map(
            fn ($mailable) => get_class($mailable)
        )->join(', ');

        PHPUnit::assertEmpty($this->mailables, 'The following mailables were sent unexpectedly: '.$mailableNames);
    }

    /**
     * Assert if a mailable was queued based on a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|int|null  $callback
     * @return void
     */
    public function assertQueued($mailable, $callback = null)
    {
        [$mailable, $callback] = $this->prepareMailableAndCallback($mailable, $callback);

        if (is_numeric($callback)) {
            return $this->assertQueuedTimes($mailable, $callback);
        }

        PHPUnit::assertTrue(
            $this->queued($mailable, $callback)->count() > 0,
            "The expected [{$mailable}] mailable was not queued."
        );
    }

    /**
     * Assert if a mailable was queued a number of times.
     *
     * @param  string  $mailable
     * @param  int  $times
     * @return void
     */
    protected function assertQueuedTimes($mailable, $times = 1)
    {
        $count = $this->queued($mailable)->count();

        PHPUnit::assertSame(
            $times, $count,
            "The expected [{$mailable}] mailable was queued {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if a mailable was not queued based on a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|null  $callback
     * @return void
     */
    public function assertNotQueued($mailable, $callback = null)
    {
        [$mailable, $callback] = $this->prepareMailableAndCallback($mailable, $callback);

        PHPUnit::assertCount(
            0, $this->queued($mailable, $callback),
            "The unexpected [{$mailable}] mailable was queued."
        );
    }

    /**
     * Assert that no mailables were queued.
     *
     * @return void
     */
    public function assertNothingQueued()
    {
        $mailableNames = wicketacc_collect($this->queuedMailables)->map(
            fn ($mailable) => get_class($mailable)
        )->join(', ');

        PHPUnit::assertEmpty($this->queuedMailables, 'The following mailables were queued unexpectedly: '.$mailableNames);
    }

    /**
     * Assert the total number of mailables that were sent.
     *
     * @param  int  $count
     * @return void
     */
    public function assertSentCount($count)
    {
        $total = wicketacc_collect($this->mailables)->count();

        PHPUnit::assertSame(
            $count, $total,
            "The total number of mailables sent was {$total} instead of {$count}."
        );
    }

    /**
     * Assert the total number of mailables that were queued.
     *
     * @param  int  $count
     * @return void
     */
    public function assertQueuedCount($count)
    {
        $total = wicketacc_collect($this->queuedMailables)->count();

        PHPUnit::assertSame(
            $count, $total,
            "The total number of mailables queued was {$total} instead of {$count}."
        );
    }

    /**
     * Assert the total number of mailables that were sent or queued.
     *
     * @param  int  $count
     * @return void
     */
    public function assertOutgoingCount($count)
    {
        $total = wicketacc_collect($this->mailables)
            ->concat($this->queuedMailables)
            ->count();

        PHPUnit::assertSame(
            $count, $total,
            "The total number of outgoing mailables was {$total} instead of {$count}."
        );
    }

    /**
     * Get all of the mailables matching a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|null  $callback
     * @return \WicketAcc\Illuminate\Support\Collection
     */
    public function sent($mailable, $callback = null)
    {
        [$mailable, $callback] = $this->prepareMailableAndCallback($mailable, $callback);

        if (! $this->hasSent($mailable)) {
            return wicketacc_collect();
        }

        $callback = $callback ?: fn () => true;

        return $this->mailablesOf($mailable)->filter(fn ($mailable) => $callback($mailable));
    }

    /**
     * Determine if the given mailable has been sent.
     *
     * @param  string  $mailable
     * @return bool
     */
    public function hasSent($mailable)
    {
        return $this->mailablesOf($mailable)->count() > 0;
    }

    /**
     * Get all of the queued mailables matching a truth-test callback.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|null  $callback
     * @return \WicketAcc\Illuminate\Support\Collection
     */
    public function queued($mailable, $callback = null)
    {
        [$mailable, $callback] = $this->prepareMailableAndCallback($mailable, $callback);

        if (! $this->hasQueued($mailable)) {
            return wicketacc_collect();
        }

        $callback = $callback ?: fn () => true;

        return $this->queuedMailablesOf($mailable)->filter(fn ($mailable) => $callback($mailable));
    }

    /**
     * Determine if the given mailable has been queued.
     *
     * @param  string  $mailable
     * @return bool
     */
    public function hasQueued($mailable)
    {
        return $this->queuedMailablesOf($mailable)->count() > 0;
    }

    /**
     * Get all of the mailed mailables for a given type.
     *
     * @param  string  $type
     * @return \WicketAcc\Illuminate\Support\Collection
     */
    protected function mailablesOf($type)
    {
        return wicketacc_collect($this->mailables)->filter(fn ($mailable) => $mailable instanceof $type);
    }

    /**
     * Get all of the mailed mailables for a given type.
     *
     * @param  string  $type
     * @return \WicketAcc\Illuminate\Support\Collection
     */
    protected function queuedMailablesOf($type)
    {
        return wicketacc_collect($this->queuedMailables)->filter(fn ($mailable) => $mailable instanceof $type);
    }

    /**
     * Get a mailer instance by name.
     *
     * @param  string|null  $name
     * @return \WicketAcc\Illuminate\Contracts\Mail\Mailer
     */
    public function mailer($name = null)
    {
        $this->currentMailer = $name;

        return $this;
    }

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param  mixed  $users
     * @return \Illuminate\Mail\PendingMail
     */
    public function to($users)
    {
        return (new PendingMailFake($this))->to($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param  mixed  $users
     * @return \Illuminate\Mail\PendingMail
     */
    public function cc($users)
    {
        return (new PendingMailFake($this))->cc($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param  mixed  $users
     * @return \Illuminate\Mail\PendingMail
     */
    public function bcc($users)
    {
        return (new PendingMailFake($this))->bcc($users);
    }

    /**
     * Send a new message with only a raw text part.
     *
     * @param  string  $text
     * @param  \Closure|string  $callback
     * @return void
     */
    public function raw($text, $callback)
    {
        //
    }

    /**
     * Send a new message using a view.
     *
     * @param  \WicketAcc\Illuminate\Contracts\Mail\Mailable|string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     * @return void
     */
    public function send($view, array $data = [], $callback = null)
    {
        if (! $view instanceof Mailable) {
            return;
        }

        $view->mailer($this->currentMailer);

        if ($view instanceof ShouldQueue) {
            return $this->queue($view, $data);
        }

        $this->currentMailer = null;

        $this->mailables[] = $view;
    }

    /**
     * Queue a new e-mail message for sending.
     *
     * @param  \WicketAcc\Illuminate\Contracts\Mail\Mailable|string|array  $view
     * @param  string|null  $queue
     * @return mixed
     */
    public function queue($view, $queue = null)
    {
        if (! $view instanceof Mailable) {
            return;
        }

        $view->mailer($this->currentMailer);

        $this->currentMailer = null;

        $this->queuedMailables[] = $view;
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  \WicketAcc\Illuminate\Contracts\Mail\Mailable|string|array  $view
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $view, $queue = null)
    {
        $this->queue($view, $queue);
    }

    /**
     * Infer mailable class using reflection if a typehinted closure is passed to assertion.
     *
     * @param  string|\Closure  $mailable
     * @param  callable|null  $callback
     * @return array
     */
    protected function prepareMailableAndCallback($mailable, $callback)
    {
        if ($mailable instanceof Closure) {
            return [$this->firstClosureParameterType($mailable), $mailable];
        }

        return [$mailable, $callback];
    }

    /**
     * Forget all of the resolved mailer instances.
     *
     * @return $this
     */
    public function forgetMailers()
    {
        $this->currentMailer = null;

        return $this;
    }

    /**
     * Handle dynamic method calls to the mailer.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->manager, $method, $parameters);
    }
}
