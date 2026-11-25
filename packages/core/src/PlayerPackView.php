<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Explicit reviewed own-account read projection, never arbitrary state export. */
interface PlayerPackView
{
    public function viewId(): string;
    public function ownView(array $state,string $account): array;
}
