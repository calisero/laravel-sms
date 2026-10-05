<?php

namespace Calisero\LaravelSms\Notification;

use Calisero\LaravelSms\Support\ScheduleAt;

class SmsMessage
{
    public function __construct(
        public string $content = '',
        public ?string $from = null,
        public ?string $to = null,
        public ?string $scheduleAt = null,
        public ?string $idempotencyKey = null,
        public ?bool $shortenUrls = null,
        public ?string $visibleBody = null,
        public ?int $validity = null,
        public ?string $callbackUrl = null
    ) {
    }

    /**
     * Create a new SMS message instance.
     *
     * @param string $content
     * @return self
     */
    public static function create(string $content = ''): self
    {
        return new self($content);
    }

    /**
     * Set the message content.
     *
     * @param string $content
     * @return $this
     */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the sender ID.
     *
     * @param string $from
     * @return $this
     */
    public function from(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set the recipient phone number.
     *
     * @param string $to
     * @return $this
     */
    public function to(string $to): static
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Schedule the message for later delivery.
     *
     * A date-time is converted to Romania time, the API's; a string must already
     * be a 'Y-m-d H:i:s' in Romania time.
     *
     * @param \DateTimeInterface|string $scheduleAt
     * @return $this
     */
    public function scheduleAt(\DateTimeInterface|string $scheduleAt): static
    {
        $this->scheduleAt = ScheduleAt::format($scheduleAt);

        return $this;
    }

    /**
     * Set an idempotency key.
     *
     * @deprecated since 1.3.0: the Calisero API takes no idempotency key, so the
     *             package's client ignores it. The API refuses (422) the same
     *             message to the same recipient sent again within a few seconds.
     *
     * @param string $idempotencyKey
     * @return $this
     */
    public function idempotencyKey(string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    /**
     * Have Calisero shorten the http:// and https:// links of the content; the short
     * links and their click counts come back in the message's shortened URLs.
     *
     * @return $this
     */
    public function shortenUrls(bool $shortenUrls = true): static
    {
        $this->shortenUrls = $shortenUrls;

        return $this;
    }

    /**
     * Set the body shown in the Calisero dashboard and API instead of the content,
     * e.g. to keep a code out of the logs.
     *
     * @return $this
     */
    public function visibleBody(string $visibleBody): static
    {
        $this->visibleBody = $visibleBody;

        return $this;
    }

    /**
     * Set the message's validity period, in hours.
     *
     * @return $this
     */
    public function validity(int $hours): static
    {
        $this->validity = $hours;

        return $this;
    }

    /**
     * Set the URL Calisero posts the delivery status to, instead of the package's
     * webhook route.
     *
     * @return $this
     */
    public function callbackUrl(string $callbackUrl): static
    {
        $this->callbackUrl = $callbackUrl;

        return $this;
    }
}
