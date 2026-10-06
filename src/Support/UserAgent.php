<?php

namespace Calisero\LaravelSms\Support;

use Calisero\LaravelSms\SmsClient;

/**
 * The User-Agent header of every request, as the other Calisero libraries name
 * theirs: the package, PHP, Laravel and the platform, e.g.
 * `Calisero-SMS-Laravel/1.3.1 (PHP 8.5.3; Laravel 13.4.0; linux x86_64)`.
 *
 * A request whose User-Agent starts with `Calisero-SMS-Laravel/` comes from this
 * package; it cannot be configured.
 *
 * @internal
 */
final class UserAgent
{
    public static function build(): string
    {
        $runtime = ['PHP ' . PHP_VERSION, 'Laravel ' . app()->version()];

        // php_uname() may be disabled on shared hosting; the machine is left out then
        $machine = function_exists('php_uname') ? strtolower(php_uname('m')) : '';
        $runtime[] = trim(strtolower(PHP_OS_FAMILY) . ' ' . $machine);

        return sprintf('Calisero-SMS-Laravel/%s (%s)', SmsClient::VERSION, implode('; ', $runtime));
    }
}
