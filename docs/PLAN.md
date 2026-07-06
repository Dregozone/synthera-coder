# Synthera Coder — Improvement Plan

> This is the plan of action to hand to Claude Code for implementation, after owner feedback.

---

## Context — why this change

Synthera Coder's stated vision (README) is a *local-first agentic coding assistant* that reads, writes, runs tests, interprets output, and iterates — driven by weak local models over LM Studio/Ollama, for free. Today it is a well-structured **prototype that does not yet realise that vision**:

- **It never edits files or runs commands as an agent.** The only agent tools are read-only (`ReadFile`, `FindComposerVersion`). `workOnTask()` stores the model's *text*; nothing is written to disk. Command execution exists only behind hardcoded UI buttons.
- **There is no real tool-calling.** `laravel/ai` supports native function-calling, but `Qwen3_8b_8k::tools()` returns `[]`. Tool "selection" is a separate free-text prompt parsed with `str_starts_with()` — brittle for weak models — and only **one** tool runs per task with no read→act→observe iteration.
- **No verification loop** (edit → run tests/pint → read errors → fix), which is the single biggest reliability lever for weak local models.
- **Model config is hardcoded** in `findAgent()` and the `Qwen3_8b_8k` attributes (which drift from `config/synthera-coder.php`), so swapping models means editing PHP — matching the constant model-churn in git history.
- **Orchestration lives in Alpine JS** (`⚡chat.blade.php` `sendMessage()`), is synchronous/blocking (freezes UI), has no streaming, and the model receives no real conversation history or project context (`instructions()` = "You are a helpful assistant", `messages()` = `[]`).

**Intended outcome:** a reliable, human-supervised ReAct-style agent that works Laravel projects bit by bit — proposing file diffs and commands you approve, verifying its own work by running tests, all local and free.

### Decisions locked with the owner
1. **Full re-architecture** to a native tool-calling agentic loop (not incremental patching).
2. **Human-in-the-loop:** agent *proposes* file writes and commands; user approves each before it touches disk/runs. (Auto-approve toggle can come later.)
3. **Queued + streamed:** the loop runs in a queued job; the UI updates live.
4. **LM Studio primary** (keep Ollama working), leveraging strict JSON-schema structured output where helpful.

### Guardrails
- **No new dependencies without explicit approval** (per `CLAUDE.md`). The whole plan uses existing packages (`laravel/ai`, Symfony Process, database queue, `wire:poll`). The *only* optional new dependency is **Laravel Reverb** for true token-streaming — flagged in Phase 6 as opt-in.
- Every phase ships with tests (`php artisan test --compact`) and passes `vendor/bin/pint --dirty`.
- Before writing agent/tool code, consult the **`ai-sdk-development` skill** and Boost `search-docs` for the exact `laravel/ai` v0.6 tool-calling / streaming / message API — do not assume method names.

---

## Target architecture (end state)

```
User prompt ──► RunAgentTurn (queued job) ──► AgentRunner (ReAct loop)
                                   │
                 ┌─────────────────┴─────────────────┐
                 │  loop (bounded, e.g. ≤12 steps):    │
                 │   1. call local model w/ tool schemas + context
                 │   2. model returns final answer  ──► done
                 │      OR tool calls:
                 │        • read-only tool ► execute now, feed result back
                 │        • mutating tool  ► create PendingAction, PAUSE
                 └─────────────────┬─────────────────┘
                                   ▼
        UI (Livewire + wire:poll) shows diff/command ► Approve / Reject
                                   │ approve
                                   ▼
        execute action ► record result ► re-dispatch job to continue
                                   │ (after edits)
                                   ▼
        Verification: php artisan test / pint ► failures fed back ► bounded fix loop
```

Key idea: **read-only tools auto-execute inside the loop; mutating tools (`WriteFile`, `RunCommand`) are intercepted, persisted as a pending action, and gate on user approval.** The orchestrator is an explicit step loop (so we can pause), not `laravel/ai`'s fully-automatic promptable loop.

---

## Phased implementation

### Phase 0 — Foundations & cleanup (low risk, do first)
- Create `docs/` and save this plan to `docs/PLAN.md`.
- **Prune dead routes** in `routes/web.php`: remove `/hello`, `/test`, `/test-tasks` (they hardcode the old agent and pollute the router).
- **Fix config drift:** align `config/synthera-coder.php` `chat_models` / `default_chat_model` with the actual LM Studio model in use; add `LM_STUDIO_URL`, `LM_STUDIO_API_KEY`, model name to `.env.example`. Update README to reflect **LM Studio primary** (it currently says Ollama-only).
- **Harden `app/Services/FileService.php`:** resolve every path against the session working directory, then verify the real path stays *inside* the project root (reject `../` traversal and absolute paths that escape). This is a prerequisite for safe write tools. Add unit tests (extend `tests/Feature/ToolServiceTest.php` patterns).
- **Stop creating empty sessions on every page load:** `mount()` → `nextSessionId()` currently inserts a row then redirects. Defer session-row creation until the first message, or clean up in the existing `tidyOldSessions()` path.

### Phase 1 — Dynamic model/provider config (kills model churn)
- Replace the single `app/Ai/Agents/Qwen3_8b_8k.php` + hardcoded `ChatService::findAgent()` with **one configurable agent** whose provider, model, timeout, and context window are resolved from config/session at runtime (use `laravel/ai`'s runtime model/provider setters — confirm API via docs).
- Drive it from a `models` map in `config/synthera-coder.php` (label → provider + model id + context window). Per-session `current_model` already exists and is wired.
- Update `tests/Feature/LmStudioConfigurationTest.php` to assert config-driven resolution instead of the pinned class.
- Delete `Qwen3_8b_8k` once nothing references it.

### Phase 2 — Real tool-calling toolset (the heart)
- Convert the agent to expose **real tools via `tools()`** using `laravel/ai` native function-calling with proper JSON schemas (not the free-text `tool_name: input` scheme). Remove the string-parsing path in `ToolService::runToolsForTask()`.
- **Tools** (all path-sandboxed via hardened `FileService`; keep auto-discovery in `ToolService::listTools()`):
  - Keep `ReadFile`; add `ListDirectory`, `SearchFiles` (grep-style), `ReadPackageJson`. *(read-only, auto-execute)*
  - `WriteFile` / `ApplyEdit` — produces a unified diff; **does not write** — records a `PendingAction`. *(mutating)*
  - `RunCommand` — **does not run** — records a `PendingAction` with the command + cwd. *(mutating)*
- Mutating tools return a placeholder to the model ("Proposed edit/command #N, awaiting approval") so the loop can pause deterministically.
- Add per-tool tests.

### Phase 3 — Agent loop orchestrator + approval gate (server-side; replaces Alpine choreography)
- **`app/Services/AgentRunner.php`** + **`app/Jobs/RunAgentTurn.php`** (queued): implement the bounded ReAct loop above. Read-only tool results feed straight back; hitting a mutating tool creates a `PendingAction` and ends the job leg.
- **New table `agent_actions`** (migration + model): `chat_session_id`, `type` (`write`|`command`), `payload` (path/diff or command+cwd), `status` (`pending`|`approved`|`rejected`|`executed`|`failed`), `result`, timestamps.
- **Livewire (`⚡chat.blade.php`):** delete the multi-step Alpine `sendMessage()` orchestration. On send, dispatch `RunAgentTurn` and return immediately. Add `approveAction($id)` / `rejectAction($id)` methods: approve → execute (write file / run command via Symfony Process) → append result → re-dispatch `RunAgentTurn` to continue.
- **Feed the model real context:** a Laravel-aware system prompt, prior conversation history (`ChatMessage`), the working directory + a shallow file-tree summary, and accumulated tool results. Reuse `ContextService` as the store that actually primes prompts (today it's written but never sent to the model).
- Render pending actions in the UI: **diff view** for writes, **command + cwd** for run, each with Approve/Reject.

### Phase 4 — Verification & self-correction loop (reliability for weak models)
- After an approved edit batch, `AgentRunner` runs project checks through the same command path: `php artisan test --compact`, `vendor/bin/pint --dirty`, optionally `npm run build`.
- Failures are fed back to the model for a **bounded** fix loop (e.g. ≤2 auto-fix rounds) before surfacing to the user.
- Wire the sidebar **Tests** panel (currently `x/y passing` placeholder) to real last-run results.

### Phase 5 — Context management that's actually used
- Use the per-session context store to build prompts (Phase 3 dependency).
- Implement the real `condenseContext()` (currently a no-op) as an LLM summarisation pass over `chat_history` / prior task results.
- Fix the sidebar indicator: it labels **bytes** as "k" and compares against `maxContextSizeBytes = 32` (a bug — the constant is `32 * 1024`). Switch to approximate **token** sizing against the selected model's context window.

### Phase 6 — UI/UX polish & live updates
- **Live updates (baseline, no new deps):** while a run is active, use `wire:poll` (~1s) to pull new messages, task/step status, and pending actions. This delivers the "streamed" feel with the queued job and zero new dependencies.
- **Optional true token-streaming (needs approval):** add **Laravel Reverb** + broadcasting so the job streams tokens live. Flag this to the owner before installing — it is the only dependency change in the plan.
- Wire up the placeholder sidebar cards to real data: **agent warm/cold status** (ping LM Studio `/v1/models`), Laravel/Livewire/Flux versions, git branch list + **session deletion** (trash button is a toast stub), and the command-palette items.
- Richer assistant rendering: syntax-highlighted code blocks and diff view for proposed edits.

### Phase 7 — Tests & quality gate
- Feature tests: orchestrator loop (mocked model), read-only tool execution, mutating-tool → pending-action → approve → execute flow, rejection path, sandbox enforcement, verification fix loop.
- Run the full quality suite: `vendor/bin/pint --parallel`, `vendor/bin/rector`, `vendor/bin/phpstan analyse`, `php artisan test --compact` (the `composer quality` script already chains these).

---

## Critical files

| Area | Path |
|---|---|
| Orchestration (replace Alpine loop) | `resources/views/pages/⚡chat.blade.php` |
| Agent loop (new) | `app/Services/AgentRunner.php`, `app/Jobs/RunAgentTurn.php` |
| Chat orchestration service | `app/Services/ChatService.php` |
| Tool discovery/dispatch | `app/Services/ToolService.php` |
| Tools | `app/Ai/Tools/*` (add `WriteFile`, `RunCommand`, `ListDirectory`, `SearchFiles`, `ReadPackageJson`) |
| Configurable agent (replaces `Qwen3_8b_8k`) | `app/Ai/Agents/*` |
| Path sandbox | `app/Services/FileService.php` |
| Context store | `app/Services/ContextService.php` |
| Pending actions (new) | migration + `app/Models/AgentAction.php` |
| Config | `config/synthera-coder.php`, `config/ai.php`, `.env.example` |
| Routes cleanup | `routes/web.php` |
| Tests | `tests/Feature/*` |

## Reuse (don't rebuild)
- `ToolService::listTools()` auto-discovery — keep; new tools drop in automatically.
- `ContextService` push/get/set + JSON persistence — repurpose as the prompt-priming store.
- `FileService::resolveFilePath()` / `isAbsolutePath()` — extend with sandboxing, don't replace.
- Existing `runCommand()` (Symfony Process) logic — move into the approved-action executor.
- Database queue (`QUEUE_CONNECTION=database`) and `composer run dev` (already starts a queue worker) — no queue setup needed.

---

## Verification (end-to-end)
1. Start LM Studio with a local model loaded; `composer run dev` (serves app + queue worker + Vite). App at `https://synthera-coder.test` (Herd).
2. Select a sibling Laravel project as the working directory.
3. Paste a multi-part request (e.g. "add a Livewire SFC page at `/new-page` that renders 'Hello, world!' and a Pest test for it").
4. Confirm: the agent reads relevant files, then **proposes a diff** — approve it and see the file written to disk; it proposes `php artisan test` — approve and see real output.
5. Confirm the verification loop runs tests after edits and feeds any failure back for a bounded fix.
6. Reject a proposed action and confirm nothing is written/run and the agent adapts.
7. Confirm path sandboxing: a request to touch a file outside the project root is refused.
8. `php artisan test --compact` green; `vendor/bin/pint --dirty` clean.

## Out of scope / deferred
- Multi-user auth (Fortify is scaffolded but routes are intentionally public for local single-user use).
- Cloud provider model picker (LM Studio/Ollama only for now).
- Reverb token-streaming unless the owner approves the dependency (Phase 6 baseline uses `wire:poll`).
- Plugin/tool marketplace.
