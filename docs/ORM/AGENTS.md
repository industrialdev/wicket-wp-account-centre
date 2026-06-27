# Documentation Rules — WicketORM Org Roster

Always read the code currently checked out alongside these files before writing or updating any doc.

---

## Audiences

Three distinct audiences. Every doc targets one primary audience. Know which before writing.

| Audience | Who | What they need |
|---|---|---|
| `implementer` | Implementation team (also called: operator, implementor) — configures the library for a client | What settings do, when to use them, defaults, gotchas |
| `support` | Support team — answers client questions, troubleshoots issues | Same as implementer; also needs troubleshooting tips and warnings |
| `developer` | Engineers and AI agents writing or reading code | Hooks, filters, class architecture, source file references |
| `end-user` | Client staff using the WP admin UI | Plain-language task guides, no technical detail |

> **Alias note for LLMs:** When a user says "implementation team", "implementer", "implementor", or "operator" — they mean the `implementer` audience. When they say "support team" or "support" — they mean the `support` audience. Both read `docs/product/` primarily.

---

## Directory Structure

```
docs/
  product/      ← implementer + support: one file per settings section/configuration area
  engineering/  ← developer + agent: hooks, filters, architecture, source reference
  guides/       ← end-user: task-oriented how-tos in plain language
  index.md      ← entry point — list all docs by directory
  AGENTS.md     ← this file
```

### Decision rules for agents

- Does the doc explain a settings section, configuration key, or runtime behavior? → `product/`
- Does the doc explain hooks, filters, PHP classes, source files, or non-UI developer contracts? → `engineering/`
- Does the doc walk a non-technical person through completing a task? → `guides/`
- When in doubt between `product/` and `engineering/`: if a support team member needs it to configure the library, it's `product/`. If a developer needs it to write code, it's `engineering/`.

---

## Frontmatter Schema

Every doc **must** have frontmatter. Fields marked ✱ are required on all docs.

```yaml
---
title: "Human-readable title"           # ✱ used in index and HTML builds
audience: [implementer, support]        # ✱ one or more of: implementer, support, developer, agent, end-user
php_class: OrgMan                       # engineering/ and product/ — primary PHP class
config_path: access.roles               # product/ docs — dot-notation config key path
source_files: ["src/OrgMan.php"]       # engineering/ docs — relevant source files relative to library root
---
```

`config_path` bridges the gap between "what does this setting do" (prose) and "where is it in the config" (code). Use the dot-notation path matching `OrgManConfig`.

`php_class` and `source_files` let agents and developers locate code without guessing.

---

## File Naming

- kebab-case, no spaces
- `product/`: descriptive slug matching the config area or feature, e.g. `additional-seats.md`, `configuration.md`
- `engineering/`: descriptive slug matching the feature, e.g. `architecture.md`, `strategies.md`
- `guides/`: verb-first, e.g. `install-org-roster.md`, `configure-strategy.md`

---

## Content Rules

**Be concise.** Every word earns its place. Short sentences. No filler.

### product/ docs

One heading per config area or setting group. Include:
- What it does (one sentence)
- When to use it / when not to
- Default value
- Warnings or gotchas if any

Technical metadata goes in a table at the end — not in prose, not in inline sub-sections:

```markdown
## access.roles

Role slugs used to grant org-scoped permissions...

| | |
|---|---|
| Config path | `access.roles` |
| PHP access | `ConfigService::get('access.roles')` |
| Default | `owner: membership_owner, manager: membership_manager, editor: org_editor` |
```

### engineering/ docs

Include: class and method references, hook/filter signatures with priority, source file paths, decision flow diagrams (plain text or tables), troubleshooting. No settings configuration explanations — link to the relevant `product/` doc instead.

### guides/ docs

Plain language only. No config keys, no class names, no code blocks unless showing exact UI input. Task-oriented: "How to configure X", "How to set up Y". Written for someone who has never seen the codebase.

---

## Index Maintenance

`docs/index.md` is the entry point for all audiences. Update it whenever a doc is added, moved, or removed. Organize by directory:

```markdown
## Product Docs (Operators & Support)
- [Title](product/filename.md) — one-line description

## Engineering Docs (Developers & Agents)
- [Title](engineering/filename.md) — one-line description

## Guides (End Users)
- [Title](guides/filename.md) — one-line description
```

---

## HTML Generation

Build pipelines can target directories:

- `docs/guides/**` → client-facing HTML (public support portal)
- `docs/product/**` → internal implementer/support manual
- `docs/engineering/**` → developer reference site

---

## LLM and Agent Guidelines

When an agent is asked to answer a question about configuring the library, read `docs/product/` first. When asked about code, hooks, or implementation, read `docs/engineering/` first. When asked to write end-user documentation, write to `docs/guides/`.

Before writing any frontmatter field that references code (`php_class`, `config_path`, `source_files`):
1. Verify the class exists — grep the codebase
2. Verify the config path exists — check `src/Config/OrgManConfig.php`
3. Verify the source file path is correct relative to the library root

Never invent config paths or class names. If uncertain, omit the field and note that it needs verification.

---

## Clarification

If the purpose or audience of a doc is unclear, ask before writing. Do not guess and produce a doc that will mislead an LLM or a support agent.