<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Support;

/**
 * Phone numbers for use in tests.
 *
 * Every number here uses country code +999, which ITU-T E.164 reserves and has never
 * assigned to any country, so none of them can route to a real subscriber. Do not put
 * a number with a live country code (+40, +1, +44, ...) in a test, even a made-up one:
 * those ranges are allocated, and a plausible-looking number may belong to somebody.
 */
final class TestPhones
{
    /** A valid E.164 number, for tests that just need one recipient. */
    public const DEFAULT = '+9995550100';

    /** A second distinct number, for tests that must tell two recipients apart. */
    public const ALTERNATE = '+9995550101';

    /** A third distinct number, for tests that need to tell three recipients apart. */
    public const OTHER = '+9995550102';

    /** Shortest number the E.164 rule accepts: 7 digits. */
    public const SHORTEST_VALID = '+9995501';

    /** Longest number the E.164 rule accepts: 15 digits. */
    public const LONGEST_VALID = '+999555010012345';

    /** 16 digits: one past the E.164 maximum. */
    public const TOO_LONG = '+9995550100123456';

    /** 6 digits: one short of the E.164 minimum. */
    public const TOO_SHORT = '+999555';

    private function __construct()
    {
    }
}
