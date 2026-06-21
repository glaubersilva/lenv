# lenv

Shared scaffolding and CLI for local WordPress development environments using Lando.

## What's in here

```
lenv/
  lenv              <- CLI executable (add to PATH)
  helpers.php       <- shared utilities
  commands/
    new.php         <- lenv new
    rebuild.php     <- lenv rebuild [folder]
    update.php      <- lenv update [folder]
  templates/
    .lando.yml      <- Lando config template (placeholder: __PROJECT_NAME__)
    .lando/
      php.ini       <- Xdebug config
    README.md       <- project README template
    docs/
      xdebug.md     <- Xdebug setup guide (copied to new projects)
      environment.md <- Environment config guide (copied to new projects)
  README.md         <- this file
  .gitignore
```

## Setup

**1. Clone the repository:**

```bash
git clone https://github.com/glaubersilva/lenv.git ~/Dev/tools/lenv
```

**2. Add `lenv` to your PATH** by editing your shell config file **directly in the WSL terminal** (not via Git Bash or any Windows tool):

```bash
# zsh (default on macOS, common on Ubuntu)
echo 'export PATH="$HOME/Dev/tools/lenv:$PATH"' >> ~/.zshrc
source ~/.zshrc

# bash
echo 'export PATH="$HOME/Dev/tools/lenv:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

Not sure which shell you use? Run `echo $SHELL` — it will say `/bin/zsh` or `/bin/bash`.

> **Warning:** Do not add this line using Git Bash (`echo` via `wsl -e sh -c "..."` or similar). Git Bash expands `$PATH` at write time and injects the entire Windows PATH into the file, corrupting the variable and breaking tools like `lando` that depend on it. Always edit the shell config from inside WSL.

## Commands

### `lenv new`

Create a new Lando environment interactively:

```bash
lenv new
```

Asks for project name, folder name, PHP version and database, then creates the project folder with `.lando.yml`, `README.md` and `docs/`.

### `lenv rebuild [folder]`

Rebuild a project's `.lando.yml` and `.lando/php.ini` from the latest templates, preserving the project name, PHP version and database.

```bash
lenv rebuild mysite.lndo.site

# or from inside the project folder:
cd mysite.lndo.site
lenv rebuild
```

After running: `lando rebuild -y`

### `lenv update [folder]`

Change environment settings interactively (PHP version, database, webserver, Xdebug):

```bash
lenv update mysite.lndo.site

# or from inside the project folder:
cd mysite.lndo.site
lenv update
```

After running: `lando rebuild -y`

## How templates work

All files in `templates/` use `__PROJECT_NAME__` as a placeholder. Commands replace it with the actual project name.

| Placeholder | Replaced with |
|---|---|
| `__PROJECT_NAME__` | project name |
| `__PROJECT_NAME__.lndo.site` | `{name}.lndo.site` |
| `serverName=__PROJECT_NAME__` | `serverName={name}` |
| `__PROJECT_IDE_PATH__` | IDE-friendly path to the project folder |

`templates/docs/` is copied to each new project's `docs/` folder with the same substitutions applied.

## Updating templates

Changes to `templates/` only affect **future** projects. For existing projects, use `lenv rebuild`.
