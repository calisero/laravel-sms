<?php

namespace Calisero\LaravelSms\Support;

/**
 * @internal
 */
final class WebhookConfig
{
    /**
     * Whether calisero.webhook.enabled is on, read like a boolean whatever its
     * spelling: env() turns "true" into true but leaves "1" a string. The route
     * and the callback_url injection must agree, or Calisero is sent a callback
     * URL nothing answers.
     */
    public static function enabled(): bool
    {
        return filter_var(config('calisero.webhook.enabled'), FILTER_VALIDATE_BOOLEAN);
    }
}
