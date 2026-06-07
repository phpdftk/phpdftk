# Issue tracker: GitHub Issues

Issues and PRDs for this repo live in GitHub Issues at https://github.com/phpdftk/phpdftk/issues. Access them through the `gh` CLI.

## Defaults

- **Repo**: `phpdftk/phpdftk`
- **Default issue type**: regular issue. Use a draft (`--draft` on `gh issue create` is not supported — see below) or the `needs-triage` label when an issue isn't fully specified.

## Conventions

Use the `gh` CLI for all issue operations. Pass markdown bodies via HEREDOC to preserve formatting.

- **Create an issue**: `gh issue create --title "..." --body "$(cat <<'EOF' ... EOF)" --label needs-triage` (add `--assignee`, `--milestone` as needed).
- **Read an issue**: `gh issue view <number> --comments` to include the conversation.
- **List issues**: `gh issue list --state open --label <label> --assignee <user> --search "..."` filtered as the task requires.
- **Comment**: `gh issue comment <number> --body "..."`.
- **Update labels / state**: `gh issue edit <number> --add-label ... --remove-label ...`; close with `gh issue close <number>` (`--reason completed|not_planned`), reopen with `gh issue reopen <number>`.

## When a skill says "publish to the issue tracker"

Create a GitHub issue in `phpdftk/phpdftk` via `gh issue create`. Return the issue number (e.g. `#123`) and URL.

## When a skill says "fetch the relevant ticket"

Run `gh issue view <number> --comments` and read the body plus the conversation.

## Fallback

If `gh` isn't authenticated in the current session, do not silently skip the publish step — surface it to the user (`gh auth status` to confirm) and offer to render the issue body as markdown they can paste manually.