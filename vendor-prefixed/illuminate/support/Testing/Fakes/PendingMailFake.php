<?php

namespace WicketAcc\Illuminate\Support\Testing\Fakes;

use Illuminate\Mail\PendingMail;
use WicketAcc\Illuminate\Contracts\Mail\Mailable;

class PendingMailFake extends PendingMail
{
    /**
     * Create a new instance.
     *
     * @param  MailFake  $mailer
     * @return void
     */
    public function __construct($mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send a new mailable message instance.
     *
     * @param  Mailable  $mailable
     * @return void
     */
    public function send(Mailable $mailable)
    {
        $this->mailer->send($this->fill($mailable));
    }

    /**
     * Push the given mailable onto the queue.
     *
     * @param  Mailable  $mailable
     * @return mixed
     */
    public function queue(Mailable $mailable)
    {
        return $this->mailer->queue($this->fill($mailable));
    }
}
