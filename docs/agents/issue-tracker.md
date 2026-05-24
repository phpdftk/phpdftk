# Issue tracker: Linear

Issues and PRDs for this repo live in Linear at https://linear.app/phpdftk. Access them through the Linear MCP server connected to Claude Code.

## Defaults

- **Workspace**: `phpdftk`
- **Default team**: `phpdftk` (the main team). Override only when an issue is clearly scoped to a different team you've configured.

## Conventions

Use the Linear MCP server's tools rather than shelling out to a CLI. Exact tool names depend on which Linear MCP server is installed; the canonical ones are:

- **Create an issue**: `mcp__linear__create_issue` with `team`, `title`, `description`, and optional `state` / `labels` / `assignee`.
- **Read an issue**: `mcp__linear__get_issue` by identifier (e.g. `PDF-123`) — include comments.
- **List issues**: `mcp__linear__list_issues` filtered by team, state, label, or assignee as the task requires.
- **Comment**: `mcp__linear__create_comment` on the issue identifier.
- **Update state / labels**: `mcp__linear__update_issue` with the new `state` or `labels`.

If those exact tool names don't match your installed MCP server, run the closest equivalent — the shape of the operation is what matters, not the name.

## When a skill says "publish to the issue tracker"

Create a Linear issue in the default team via the MCP server. Return the issue identifier (e.g. `PDF-123`) and URL.

## When a skill says "fetch the relevant ticket"

Call `mcp__linear__get_issue` with the identifier. Include comments and the current workflow state.

## Fallback

If the Linear MCP server isn't connected in the current session, do not silently skip the publish step — surface it to the user and offer to render the issue body as markdown they can paste manually.
