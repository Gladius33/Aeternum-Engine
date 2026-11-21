<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Trusted application configuration, not a sandbox for user-supplied PHP. */
final readonly class RuleSet
{
    public string $key;

    public function __construct(
        public string $id,
        public string $version,
        public string $artifactDigest,
        public CommandHandler $commands,
        public EventHandler $events,
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D',$id)
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.+-]{0,63}$/D',$version)
            || !preg_match('/^[0-9a-f]{64}$/D',$artifactDigest)) {
            throw new \InvalidArgumentException('Invalid rule set identity');
        }
        $this->key = hash('sha256',json_encode([$id,$version,$artifactDigest],JSON_THROW_ON_ERROR));
    }
}
