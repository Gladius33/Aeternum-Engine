<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Reviewed PHP-side gameplay extension. Implementations must be deployed and
 * version-pinned by an operator; untrusted JSON is NEVER executable code.
 *
 * The digest identifies the reviewed implementation artifact, not just its
 * class name. Changing code behavior requires a new digest.
 */
interface ApprovedPackCommand extends CommandHandler
{
    /** @return non-empty-list<string> Namespaced types, e.g. pack.guild.research */
    public function supportedTypes(): array;

    /** SHA-256 digest of the independently reviewed handler artifact. */
    public function codeDigest(): string;
}
