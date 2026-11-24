<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** A consistency barrier, not a permission or gameplay-rule failure. */
final class DueEventsPending extends \DomainException {}
