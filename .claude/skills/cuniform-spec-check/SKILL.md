---
name: cuniform-spec-check
description: Audit an implemented component against its specification section — checks requirements coverage, build-blocking conditions, and drift between code and docs/SPEC.md.
argument-hint: "[component or spec section]"
---

Audit an implementation against `docs/SPEC.md`.

1. Identify the spec sections governing the named component.
2. Extract every normative statement from those sections — MUST, "build fails", "is a build
   error", "never", "always". List them as numbered requirements.
3. For each, state: implemented / partially implemented / missing / contradicted, with the
   file and line as evidence. "Looks fine" is not a finding; cite the code.
4. Pay particular attention to the failure paths. The spec's build-blocking conditions
   (§5.5, §10.3) are the requirements most often implemented as warnings by accident.
5. Report drift in both directions. If the code does something sensible that the spec does
   not describe, that is a spec bug — say so, and propose the wording rather than silently
   accepting the code as the source of truth.

Produce a table, then a short list of recommended fixes ordered by severity. Do not change
code during an audit.
