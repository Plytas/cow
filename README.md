# cow

A CLI for managing APFS copy-on-write git clones with [Laravel Valet](https://laravel.com/docs/valet) integration. Spin up isolated project clones instantly, switch between them, and let Valet serve the right one — without duplicating gigabytes of vendor files.

## Installation

Download the latest `cow` binary from [GitHub Releases](https://github.com/Plytas/cow/releases/latest) and place it in your `PATH`:

```bash
curl -L https://github.com/Plytas/cow/releases/latest/download/cow -o ~/bin/cow
chmod +x ~/bin/cow
```

## Self-update

```bash
cow self-update
```

## Usage

### Interactive TUI

Running `cow` with no arguments opens an interactive menu. On first run it walks you through initial setup (clones directory, IDE, projects).

```bash
cow
```

From the menu you can:
- Activate a clone (relinks Valet and restarts PHP-FPM)
- Open a clone in your IDE
- Create a new clone from a branch name, a remote branch, or an open PR
- Delete a clone
- Switch between configured projects

### Non-interactive commands

All commands accept `--json` to emit machine-readable output.

#### List projects

```bash
cow:projects [--json]
```

#### Add a project

```bash
cow:project-add <name> <path> [--domain=<domain>] [--json]
```

#### List clones for a project

```bash
cow:list <project> [--json]
```

#### Create a clone

```bash
cow:create <project> [--branch=<branch>] [--pr=<number>] [--json]
```

#### Activate a clone (relink Valet)

```bash
cow:activate <project> <clone> [--json]
```

Use `main` as the clone name to activate the source repo itself.

#### Open a clone in your IDE

```bash
cow:open <project> [<clone>=main] [--json]
```

#### Delete a clone

```bash
cow:delete <project> <clone> [--force] [--json]
```

## Releasing a new version

Push a version tag — GitHub Actions builds the PHAR and publishes the release automatically:

```bash
git tag v1.2.3
git push origin v1.2.3
```

## License

MIT
