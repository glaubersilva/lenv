# Architecture

How the `lenv` CLI and templates are organized.

## Repository layout

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
  docs/
    architecture.md <- this file
  README.md
  .gitignore
```

## How templates work

All files in `templates/` use `__PROJECT_NAME__` as a placeholder. Commands replace it with the actual project name when scaffolding a new environment.

| Placeholder | Replaced with |
|---|---|
| `__PROJECT_NAME__` | project name |
| `__PROJECT_NAME__.lndo.site` | `{name}.lndo.site` |
| `serverName=__PROJECT_NAME__` | `serverName={name}` |
| `__PROJECT_IDE_PATH__` | IDE-friendly path to the project folder |

`templates/docs/` is copied to each new project's `docs/` folder with the same substitutions applied.

## Updating templates

Changes to `templates/` only affect **future** projects. For existing projects, use:

```bash
lenv rebuild [folder]
```

Then inside the project: `lando rebuild -y`

> `lenv rebuild` updates `.lando.yml` and `.lando/php.ini` only. It does not overwrite project `README.md` or `docs/` — those may contain project-specific content.

## Commands (implementation)

| Command | What it does |
|---|---|
| `lenv new` | Interactive wizard — creates project folder from templates |
| `lenv rebuild` | Re-applies `.lando.yml` and `.lando/php.ini` templates, preserving current settings |
| `lenv update` | Interactive edit of PHP version, database, webserver, and Xdebug in `.lando.yml` |

Validation helpers (`validate_project_name`, `validate_folder_name`) live in `helpers.php`. Project names allow dots (e.g. for `local.*` domains required by some plugin licenses).
