<?php

namespace Estante\Shared\Domain;

/**
 * Base class for every broken business rule. The HTTP layer turns these
 * into validation-style errors, so the domain never needs to know about HTTP.
 */
abstract class DomainRuleViolation extends \DomainException {}
