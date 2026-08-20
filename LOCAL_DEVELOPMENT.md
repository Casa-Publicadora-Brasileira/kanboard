# Local development

The normal local environment is isolated from the company DEV database:

```text
Kanboard local
    |-- Web:   http://localhost:18080
    `-- MySQL: 127.0.0.1:3313 (from the host)
               kanboard-local-db:3306 (from Kanboard)
```

MySQL data is persisted in the exclusive `kanboard-local-mysql-data` Docker
volume. It remains available after containers are stopped or normally recreated
while preserving volumes.

## Safety guarantees

- Automatic Kanboard migrations are disabled with `DB_RUN_MIGRATIONS=false`.
- The image's scheduled Kanboard cron job is disabled locally.
- MySQL is pinned to version 8.0.42.
- Application and database ports bind only to `127.0.0.1`.
- Normal local operation does not use DEV credentials or connect to DEV.
- No schema or data is imported from DEV by this setup.
- Local persistent volumes use names specific to this project.

The database starts empty. Until a controlled import is performed in a separate
task, a missing-schema error from Kanboard is expected. Do not enable migrations
merely to make the application page open.

## Configure

If `.env.local` does not exist, create the ignored local environment file:

```bash
cp .env.local.example .env.local
```

Replace every `CHANGE_ME` placeholder with a unique local password. This ignored
file is the only place where local database passwords are stored.

## Validate configuration without starting the application

```bash
docker compose --env-file .env.local -f compose.local.yml config --quiet
```

## Start

Start and validate MySQL first:

```bash
docker compose --env-file .env.local -f compose.local.yml up -d kanboard-local-db
docker compose --env-file .env.local -f compose.local.yml ps
docker compose --env-file .env.local -f compose.local.yml logs kanboard-local-db
```

Then start Kanboard. Its missing-schema error is expected until the later import:

```bash
docker compose --env-file .env.local -f compose.local.yml up -d --build app
```

Open <http://localhost:18080>. Code is copied into the image at build time, so use
`--build` again after changing application code.

## Logs

```bash
docker compose --env-file .env.local -f compose.local.yml logs app
docker compose --env-file .env.local -f compose.local.yml logs kanboard-local-db
```

## MySQL Workbench

```text
Connection Name: Kanboard LOCAL
Hostname: 127.0.0.1
Port: 3313
Username: kanboard
Default Schema: kanboard
```

Read the password from `KANBOARD_LOCAL_DB_PASSWORD` in the ignored `.env.local`.

## Stop without deleting data

```bash
docker compose --env-file .env.local -f compose.local.yml stop
```

Never add `-v` to a Compose command. Do not remove the
`kanboard-local-mysql-data` volume; it contains the local database.
