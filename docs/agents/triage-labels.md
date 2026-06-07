# Triage Labels

The skills speak in terms of five canonical triage roles. In this repo, those roles map onto **GitHub labels and issue state**. When a skill applies a triage role, set the mapped label (and close the issue if applicable) via `gh issue edit` / `gh issue close`.

| Canonical role    | GitHub mapping                                 | Meaning                                  |
| ----------------- | ---------------------------------------------- | ---------------------------------------- |
| `needs-triage`    | open, label `needs-triage`                     | Maintainer needs to evaluate this issue  |
| `needs-info`      | open, label `needs-info`                       | Waiting on reporter for more info        |
| `ready-for-agent` | open, label `ready-for-agent`                  | Fully specified, ready for an AFK agent  |
| `ready-for-human` | open, label `ready-for-human`                  | Requires human implementation            |
| `wontfix`         | closed (reason `not_planned`), label `wontfix` | Will not be actioned                     |

Notes:
- GitHub has no workflow-state concept — every distinction rides on labels plus the open/closed bit.
- When transitioning roles, remove the prior role label as you add the new one (`gh issue edit <number> --add-label X --remove-label Y`).
- When applying `wontfix`, close the issue with `gh issue close <number> --reason not_planned` and add the `wontfix` label.