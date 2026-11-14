# 1. Pragmatic DDD in Laravel

- Status: accepted
- Date: 2026-11-14

## Context

Estante is small, but it has real rules: an ISBN has a check digit, a book
cannot be finished before it was started, only finished books can be rated,
a reader holds one copy of each edition. We want those rules in one place,
tested without HTTP, and we want the code to read like the problem.

Strict hexagonal architecture (pure PHP domain, separate persistence models
and mappers) fights Laravel at every step: Eloquent, factories, route model
binding, policies. For an app this size the cost is not worth it.

## Decision

- Code is split by **bounded context** under `src/`: `Catalog`, `Shelf`
  (and later `Loans`). Each has `Domain`, `Application` and, when needed,
  `Infrastructure`.
- **Aggregates are Eloquent models**, but their state only changes through
  intention-revealing methods (`startReading`, `finishReading`, `rate`) that
  guard the rules and record domain events. Controllers never set attributes.
- **Value objects** (`Isbn`, `BookMetadata`) are plain `final readonly` PHP.
- **Ports and adapters only where there is a real outside world**: the
  `BookMetadataProvider` port has Open Library and Google Books adapters, and
  tests swap in an in-memory catalog.
- **Use cases** are small invokable classes in `Application`. They persist
  aggregates and dispatch the events the aggregates recorded.
- Broken rules throw subclasses of `DomainRuleViolation`; the HTTP layer
  renders them as form errors (or 422 for JSON).
- The rules are enforced by Pest architecture tests (`tests/Arch`):
  the domain does not use HTTP, Inertia, facades or adapters; `Catalog` never
  depends on `Shelf`; controllers never reach adapters directly.

## Context map

`Catalog` is upstream: it owns editions (`Book`) and knows nothing about
readers. `Shelf` is downstream and conforms to it: a `Copy` references a
`Book` by id and reads it for display.

## Consequences

- Domain tests boot the framework (Eloquent needs it) but never hit the
  database, so they stay fast.
- We accept that `Domain` depends on Eloquent. If persistence ever needs to
  change, that is the seam to cut.
- New contexts follow the same layout, and the architecture tests cover them
  by namespace.
