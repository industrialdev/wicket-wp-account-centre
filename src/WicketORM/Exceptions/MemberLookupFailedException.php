<?php

declare(strict_types=1);

namespace WicketORM\Exceptions;

/**
 * Thrown when a member lookup cannot be completed (API transport error,
 * client unavailable, enrichment failure).
 *
 * Distinct from a "not found" result: callers must not treat this as
 * evidence that a member is absent from the roster.
 */
final class MemberLookupFailedException extends \RuntimeException {}
