# Triage Labels

The skills speak in terms of five canonical triage roles. In this repo, those roles map onto **Linear workflow states**, not labels. When a skill applies a triage role, transition the issue to the mapped state via `mcp__linear__update_issue`.

| Canonical role    | Linear workflow state               | Meaning                                                          |
| ----------------- | ----------------------------------- | ---------------------------------------------------------------- |
| `needs-triage`    | `Triage`                            | Maintainer needs to evaluate this issue                          |
| `needs-info`      | `Triage` + label `needs-info`       | Waiting on reporter for more info — keep in Triage but flag      |
| `ready-for-agent` | `Backlog` + label `ready-for-agent` | Fully specified, ready for an AFK agent                          |
| `ready-for-human` | `Todo`                              | Requires human implementation                                    |
| `wontfix`         | `Cancelled`                         | Will not be actioned                                             |

Notes:
- `Triage`, `Backlog`, `Todo`, and `Cancelled` are Linear's built-in workflow states. If your team has renamed them, edit the right-hand column.
- `needs-info` and `ready-for-agent` ride on top of a workflow state because Linear has no native equivalent — the label adds the role distinction the skills need.
- When a skill mentions a role (e.g. "apply the AFK-ready triage label"), perform the mapped state transition *and* apply any listed label.
