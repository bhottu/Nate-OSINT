<?php

namespace App\Services\EmailIntelligence;

/**
 * Immutable DTO for a single source result.
 *
 * Statuses used: FOUND, NOT FOUND, UNVERIFIED, UNABLE TO VERIFY, ERROR.
 * A FOUND result is always an indication (possible match), never proof of
 * account ownership. The note field carries verification caveats shown in UI.
 */
final class SourceResult
{
    public const USER_AGENT = 'NateOSINT-EmailIntelligence/1.0 (passive public lookup; research tool)';

    public function __construct(
        public readonly string $platform,
        public readonly string $kind,
        public readonly ?string $identifier,
        public readonly ?string $profileUrl,
        public readonly string $status,
        public readonly ?string $confidence,
        public readonly ?string $evidence,
        public readonly ?string $sourceUrl,
        public readonly ?int $responseTime,
        public readonly ?string $errorMessage,
    ) {}

    /** Public evidence indicates a possible association (never ownership). */
    public static function found(
        string $platform,
        string $kind,
        ?string $identifier,
        ?string $profileUrl,
        string $evidence,
        ?string $sourceUrl = null,
        string $confidence = 'Low',
        string $note = '',
    ): self {
        return new self(
            platform: $platform,
            kind: $kind,
            identifier: $identifier,
            profileUrl: $profileUrl,
            status: 'FOUND',
            confidence: $confidence,
            evidence: $evidence,
            sourceUrl: $sourceUrl,
            responseTime: null,
            errorMessage: $note !== '' ? $note : null,
        );
    }

    /** The source answered authoritatively that nothing public was found. */
    public static function notFound(string $platform, string $kind, ?string $sourceUrl = null, string $evidence = 'No publicly verifiable data was found.'): self
    {
        return new self(
            platform: $platform,
            kind: $kind,
            identifier: null,
            profileUrl: null,
            status: 'NOT FOUND',
            confidence: null,
            evidence: $evidence,
            sourceUrl: $sourceUrl,
            responseTime: null,
            errorMessage: null,
        );
    }

    /** The source could not be checked honestly (blocked, rate limited). */
    public static function unableToVerify(string $platform, string $kind, ?string $sourceUrl = null, string $evidence = 'Unable to verify through public sources.'): self
    {
        return new self(
            platform: $platform,
            kind: $kind,
            identifier: null,
            profileUrl: null,
            status: 'UNABLE TO VERIFY',
            confidence: null,
            evidence: $evidence,
            sourceUrl: $sourceUrl,
            responseTime: null,
            errorMessage: null,
        );
    }

    /** The source failed technically; other sources were unaffected. */
    public static function error(string $platform, string $kind, string $message, ?string $sourceUrl = null): self
    {
        return new self(
            platform: $platform,
            kind: $kind,
            identifier: null,
            profileUrl: null,
            status: 'ERROR',
            confidence: null,
            evidence: 'Source check failed; this platform was not verified.',
            sourceUrl: $sourceUrl,
            responseTime: null,
            errorMessage: $message,
        );
    }
}
