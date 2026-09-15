<?php

namespace App\Services\EmailIntelligence\Sources;

use App\Services\EmailIntelligence\SourceResult;

/**
 * Contract for a single public, passive email intelligence source.
 * Implementations must never authenticate, enumerate, or probe private areas.
 */
interface SourceInterface
{
    /** Human-readable platform label used in results and the UI. */
    public function platform(): string;

    /** Machine kind of the check (e.g. profile_api, identifier_probe). */
    public function kind(): string;

    /** Run the passive check for one normalized email address. */
    public function check(string $normalizedEmail): SourceResult;
}
