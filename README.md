# Synthera Coder

> A local-first, agentic AI coding assistant built with Laravel, Livewire, and Ollama — designed to sit alongside your projects and help you think, plan, and build.

Synthera Coder is a self-hosted web application that runs as a **sibling directory** to your other projects. Point it at any project on your machine and start a conversation: it will break down your request into a concise task list, run the relevant tools to gather context (reading files, checking package versions, etc.), work through each task using a local LLM, then synthesise a final response — all streamed back to a clean chat UI without sending a single line of your code to a third-party API.

---

## Table of Contents

- [Features](#features)
- [Roadmap](#roadmap)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Architecture](#architecture)
- [Contributing](#contributing)
- [Bug Reports & Issues](#bug-reports--issues)
- [License](#license)

---

## Features

Everything listed below is **currently implemented** and working.

### Agentic Chat

- **Three chat modes** selectable per message:
  - **Ask** — a straightforward question-and-answer mode.
  - **Plan** — the agent derives a structured task list from your prompt before responding.
  - **Agent** — the full agentic loop: task decomposition → tool execution → per-task work → final synthesis.
- **Automatic task decomposition** — the agent distils any prompt into a minimal, non-overlapping list of ≤ 5 actionable tasks using a two-pass refinement process (initial generation → conciseness pass).
- **Per-task tool execution** — before working on each task the agent selects the appropriate tools from the available tool registry and runs them to gather the context it needs.
- **Per-task work** — each task is worked on individually, with the results from earlier tasks and tool runs fed as context into subsequent ones.
- **Final synthesis** — once all tasks are complete the agent produces a single coherent response that addresses the original prompt.
- **Auto-generated session titles** — after the first message the agent generates a ≤ 5-word descriptive title for the session.
- **Graceful unclear-prompt handling** — if a prompt is ambiguous the agent returns "Reframe your question" rather than hallucinating a task list.

### Chat Sessions

- **Persistent sessions** — every conversation is stored in the database with its full message history; you can leave and return without losing context.
- **URL-addressable sessions** — each session has a unique `?sessionId=` query parameter so you can bookmark or share a specific chat.
- **Session browser** — a slide-out panel lists your 10 most recent sessions with title, message count, and relative timestamp; click any entry to navigate straight to it.
- **Editable session titles** — rename any session via the pencil-icon prompt dialog.
- **Paginated message history** — messages load in batches of 50; a "Load older messages" button fetches earlier batches on demand so the initial page load stays fast.
- **Automatic stale-session cleanup** — on ~1-in-10 page loads, sessions older than one week with zero messages are silently pruned.

### Context Window Management

- **Per-session JSON context store** — a flat JSON file is maintained on disk for each session (`app/Ai/Sessions/{id}/context.json`), persisting the original prompt, task list, tool results, and task results across the multi-step agentic loop.
- **Live context size indicator** — the sidebar displays current context size vs. the 10 KB cap in bytes and as a percentage.
- **Condense context** — a one-click action to compact the context (hook ready for custom summarisation logic).
- **Clear context** — a one-click action to wipe the context and start fresh within the same session.

### Working Directory

- **Sibling-project selector** — Synthera Coder scans the parent directory on startup and presents every sibling folder as a selectable working directory, sorted alphabetically.
- **Persistent working directory** — the selected project path is saved per session and restored when you return.
- **Working directory indicator** — the active path is always visible in the sidebar with a folder-open icon.

### One-Click Project Commands

Run common maintenance commands against the selected project directly from the UI — no terminal required:

- **`composer audit`** — check for known security vulnerabilities in PHP dependencies.
- **`composer audit fix`** — attempt to automatically resolve vulnerable packages.
- **`composer update`** — update all Composer packages.
- **`npm audit`** — check for known vulnerabilities in JavaScript dependencies.
- **`npm audit fix`** — attempt to automatically resolve vulnerable JS packages.
- **`npm update`** — update all npm packages.
- **Git status** and **Git log** — inspect the current state and recent commit history of the project.

All command output is captured and added to the session as an info message so you have a full audit trail.

### AI Tools

The agent has access to an auto-discovered tool registry — any class in `app/Ai/Tools/` that implements the `Tool` contract is automatically registered.

Currently implemented tools:

| Tool | Description |
|---|---|
| `ReadFile` | Checks whether a file exists and reads its contents, resolving paths relative to the active working directory. |
| `FindComposerVersion` | Reads `composer.lock` in the active project and returns the installed version of any named package. |

### Model Selection

- **Per-message model switching** — choose the model for each interaction from the composer toolbar without reloading.
- **Ollama-backed local models** — both configured models (`qwen3:8b-8k` and `qwen3:14b-16k`) run entirely locally via Ollama, keeping your code and prompts off third-party servers.
- **Cold-start status panel** — the sidebar lists all configured models, ready for live warm/cold status indicators.

### UI / UX

- **Animated thinking indicator** — while the agent is working, a pulsing assistant bubble cycles through time-aware status messages (e.g., "Warming up the gears…" → "Going full detective mode…" → "Still cooking, but we're close…") that update every second.
- **Dark mode support** — full light/dark theme via Flux UI.
- **Auto-scroll to latest message** — the message pane scrolls to the bottom after every agent action.
- **Keyboard shortcut** (`Ctrl+`` `) — opens the command palette from anywhere in the composer.
- **Message type differentiation** — user messages, assistant responses, and system info messages are styled and aligned distinctly.
- **New session button** — creates a fresh session and redirects in a single click.

---

## Roadmap

The following capabilities are planned or partially scaffolded but **not yet fully implemented**.

### Near-Term

- **Real-time / streaming responses** — stream tokens back to the UI as the model generates them rather than waiting for the full response.
- **Queue-backed agentic tasks** — push long-running agentic work onto Laravel queues so the HTTP request returns immediately and results are pushed via broadcasting.
- **Agent warm/cold status** — surface live warm/cold start information for each model in the sidebar panel (the UI shell is already in place).
- **Session deletion** — the trash-can button in the session header is wired to a toast placeholder; full deletion with confirmation is pending.
- **Context condensation logic** — the `condenseContext()` hook is in place but currently a no-op; a summarisation pass using the LLM is planned.
- **Command palette actions** — the `flux:command` modal UI is present but the items (Assign to…, Create new file, etc.) are not yet wired to real actions.

### Medium-Term

- **Expanded tool library** — additional tools covering file writing, directory listing, running shell commands, reading `package.json`, grep/search within project files, and more.
- **Multi-request / batch workflows** — the ability to queue multiple independent prompts and process them in parallel or in sequence, with a unified results view.
- **Conversation memory** — feed previous session messages back into the agent as conversational context so follow-up questions have full history.
- **Authentication & multi-user support** — the Fortify authentication scaffolding is already included; enabling guarded routes would allow multiple developers to share a single instance.
- **Richer markdown rendering** — syntax-highlighted code blocks, diff views, and copy-to-clipboard for assistant responses.

### Longer-Term

- **Write-back tools** — tools that can create or modify files in the target project (with confirmation dialogs) to move from advisory to hands-on agentic coding.
- **Multi-model provider support** — while the `config/ai.php` already defines OpenAI, Gemini, Anthropic, Groq, and others, the UI model picker and agent classes are currently Ollama-only; exposing cloud providers as an opt-in is planned.
- **Comprehensive agentic coding flows** — full agentic loops capable of reading, writing, running tests, interpreting output, and iterating — the core vision of the project.
- **Plugin / tool marketplace** — a convention for community-contributed tools that can be dropped into `app/Ai/Tools/` and auto-discovered.

---

## Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| PHP | ≥ 8.3 | 8.4 recommended |
| Composer | ≥ 2 | |
| Node.js | ≥ 20 | |
| npm | ≥ 10 | |
| Ollama | Latest | [ollama.com](https://ollama.com) — must be running locally |
| A supported database | SQLite / MySQL / PostgreSQL | SQLite works out of the box for local use |
| Laravel Herd (optional) | Latest | Recommended for zero-config local serving on macOS/Windows |

### Ollama Models

Pull at least one of the configured models before starting:

```bash
ollama pull qwen3:8b
ollama pull qwen3:14b
```

> The model tags used in `config/synthera-coder.php` (`qwen3:8b-8k`, `qwen3:14b-16k`) must match exactly what Ollama reports — adjust them if your local tag differs.

---

## Installation

### 1. Clone the repository as a sibling of your projects

Synthera Coder is designed to live **next to** the projects it assists. For example:

```
~/projects/
  my-laravel-app/
  my-node-api/
  synthera-coder/   ← clone here
```

```bash
git clone https://github.com/your-org/synthera-coder.git
cd synthera-coder
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Set up the environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure your database

Edit `.env` and set your preferred database connection. For a quick local start with SQLite:

```dotenv
DB_CONNECTION=sqlite
# DB_DATABASE is not required for SQLite — Laravel will create storage/database.sqlite automatically
```

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Install and build front-end assets

```bash
npm install
npm run build
```

### 7. Start the application

**With Laravel Herd** — the site is served automatically at `https://synthera-coder.test`. No further steps needed.

**Without Herd** — use the built-in dev runner which starts the HTTP server, queue worker, and Vite in one command:

```bash
composer run dev
```

Then visit [http://localhost:8000](http://localhost:8000).

---

## Configuration

All application-specific settings live in `config/synthera-coder.php`:

```php
return [
    // Models available in the UI model picker
    'chat_models' => [
        'qwen3:8b-8k',
        'qwen3:14b-16k',
    ],

    // The model selected by default when starting a new session
    'default_chat_model' => 'qwen3:8b-8k',

    // Chat modes shown in the mode picker (key => Heroicon name)
    'chat_types' => [
        'ask'   => 'question-mark-circle',
        'plan'  => 'list-bullet',
        'agent' => 'rocket-launch',
    ],

    // The mode selected by default
    'default_chat_type' => 'agent',
];
```

### Adding a new Ollama model

1. Pull the model: `ollama pull <model-name>`
2. Add the model tag to the `chat_models` array in `config/synthera-coder.php`.
3. Create a corresponding agent class in `app/Ai/Agents/` — use the existing `Qwen3_8b_8k` agent as a template, updating the `#[Model]` attribute.

### Adding a new Tool

1. Create a new class in `app/Ai/Tools/` implementing `Laravel\Ai\Contracts\Tool`.
2. The tool is auto-discovered by `ToolService` — no registration required.

```bash
# Use the built-in stub via Artisan (if a make command is configured), or copy the stub manually:
cp stubs/tool.stub app/Ai/Tools/MyNewTool.php
```

---

## Usage

1. Open the application in your browser.
2. A new chat session is created automatically and the URL updates with `?sessionId=<id>`.
3. Use the **folder icon** in the sidebar to select the project you want to work with.
4. Type your question or request in the composer at the bottom of the screen.
5. Select a **chat mode** (Ask / Plan / Agent) and a **model** from the dropdowns in the composer toolbar.
6. Press **Enter** or click the send button.

In **Agent** mode the UI will cycle through the full agentic loop automatically:

```
Send message → Generate tasks → Auto-title session
  → For each task:
      Start task → Run tools → Work on task
  → Synthesise final response
```

Progress is visible through the animated thinking indicator and the **Tasks** panel in the sidebar.

---

## Architecture

```
synthera-coder/
├── app/
│   ├── Ai/
│   │   ├── Agents/          # One class per LLM/model combination
│   │   ├── Sessions/        # Per-session context JSON files (gitignored in production)
│   │   └── Tools/           # Auto-discovered tool classes
│   ├── Models/
│   │   ├── ChatSession.php  # Persists session metadata & working directory
│   │   └── ChatMessage.php  # Persists every message in the chat
│   └── Services/
│       ├── ChatService.php    # Orchestrates the agentic loop
│       ├── ContextService.php # Reads/writes per-session context files
│       ├── FileService.php    # Resolves absolute/relative file paths
│       └── ToolService.php    # Auto-discovers & dispatches tools
└── resources/views/pages/
    └── ⚡chat.blade.php       # Full-stack Livewire SFC (PHP class + Blade in one file)
```

The entire chat interface is a single **Livewire Single File Component** (`⚡chat.blade.php`). The PHP class at the top of the file handles all server-side state and actions; Alpine.js in the Blade template drives the client-side agentic loop, calling Livewire methods in sequence and managing the thinking indicator.

---

## Contributing

Contributions are welcome. Please open an issue to discuss significant changes before submitting a pull request.

1. Fork the repository and create a feature branch.
2. Write or update tests for any behaviour you change (`php artisan make:test --pest YourTest`).
3. Run the test suite: `php artisan test --compact`
4. Run the code formatter: `vendor/bin/pint --dirty`
5. Submit a pull request with a clear description of the change and why it is needed.

---

## Bug Reports & Issues

Please use [GitHub Issues](https://github.com/your-org/synthera-coder/issues) to report bugs or request features.

When filing a bug report, include:

- **PHP version** (`php -v`)
- **Ollama version** (`ollama --version`) and the model(s) you have pulled
- **Steps to reproduce** — the exact prompt and chat mode that triggered the issue
- **Expected behaviour** vs. **actual behaviour**
- **Relevant log output** from `storage/logs/laravel.log` or `php artisan pail`

---

## License

Synthera Coder is open-source software licensed under the [MIT licence](https://opensource.org/licenses/MIT).
