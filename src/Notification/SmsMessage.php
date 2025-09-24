<?php

namespace Calisero\LaravelSms\Notification;

class SmsMessage
{
    public function __construct(
        public string $content = '',
        public ?string $from = null,
        public ?string $to = null,
        public ?string $scheduleAt = null,
        public ?string $idempotencyKey = null
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
     * @param string $scheduleAt
     * @return $this
     */
    public function scheduleAt(string $scheduleAt): static
    {
        $this->scheduleAt = $scheduleAt;

        return $this;
    }

    /**
     * Set an idempotency key.
     *
     * @param string $idempotencyKey
     * @return $this
     */
    public function idempotencyKey(string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }
}
