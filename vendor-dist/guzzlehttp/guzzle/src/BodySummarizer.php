<?php

namespace WicketAcc\GuzzleHttp;

use WicketAcc\Psr\Http\Message\MessageInterface;
final class BodySummarizer implements BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;
    public function __construct(?int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string
    {
        return $this->truncateAt === null ? \WicketAcc\GuzzleHttp\Psr7\Message::bodySummary($message) : \WicketAcc\GuzzleHttp\Psr7\Message::bodySummary($message, $this->truncateAt);
    }
}