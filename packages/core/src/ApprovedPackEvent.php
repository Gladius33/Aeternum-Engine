<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Trusted PHP implementation of game-specific, version-pinned event logic.
 * A data-only manifest cannot instantiate or locate implementations.
 */
interface ApprovedPackEvent extends EventEffectsHandler
{
    /** @return non-empty-list<string> Event types in pack.module.event namespace. */
    public function supportedTypes(): array;

    /** SHA-256 digest of the separately reviewed executable artifact. */
    public function codeDigest(): string;
}
