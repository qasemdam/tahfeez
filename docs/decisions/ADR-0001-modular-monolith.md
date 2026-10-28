# ADR-0001 — Modular monolith backend, offline-first client

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
The system is a multi-tenant SaaS for Quran memorization centers with a mobile-first, offline-capable client. The domain is deeply relational: a weekly grade depends on records, assignments, attendance and a versioned rule set. The MVP team is small and the operational budget is a single deployment.

## Decision
Build a **modular monolith** in Laravel (one deployable, one database) with enforced module boundaries, and a **local-first Flutter client** that owns offline state. No microservices, no message broker, no CQRS in MVP.

## Consequences
- Atomicity is free: record + evaluation + audit commit in one transaction. This matters because the spec requires an unbroken audit trail.
- Module boundaries are enforced by directory + an architecture test, not by network calls. Extraction later is possible but not planned.
- Scaling is vertical first, then read replicas. Acceptable for the target envelope (tens of thousands of students).
- The client must implement measurement twice (PHP + Dart); this is mitigated by shared golden fixtures (ADR-0003).

## Alternatives rejected
- **Microservices** — distributed transactions across grading operations; operational cost unjustified at this scale.
- **Server-rendered web only** — violates the offline requirement.
- **Backend-for-frontend per platform** — two API surfaces to keep in sync for no MVP benefit.
