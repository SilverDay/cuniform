---
name: cuniform-task
description: Start the next Cuniform build task. Reads docs/BUILD-ORDER.md, picks the first task whose dependencies are met, loads the spec sections it cites, and restates the acceptance criteria before writing code.
argument-hint: "[task-id, optional]"
disable-model-invocation: true
---

Start a Cuniform implementation task.

1. Read `docs/BUILD-ORDER.md`. If a task id was given, use it. Otherwise pick the first task
   that is not marked done and whose dependencies are all done.
2. Read every SPEC section the task cites. Do not work from the task summary alone — the
   summary is an index, the spec is the requirement.
3. Restate, in three or four lines: what the task delivers, which spec sections govern it,
   and the acceptance criteria you will be judged against.
4. Name anything the spec leaves ambiguous for this task. If something is genuinely
   underspecified, stop and ask rather than deciding — the spec is maintained and gaps get
   closed there, not in code comments.
5. Write the tests and the implementation together. Then run `make check`.
6. Report which acceptance criteria now pass and which do not. Do not mark the task done in
   `docs/BUILD-ORDER.md` unless all of them pass.

Do not start a second task in the same turn.
