# Docker auto initialization for exam deployment

This development branch supports one-command startup for temporary exam use.

Run:

```shell
docker compose up -d
```

When the database has no SCNUOJ tables, the PHP container entrypoint runs migrations automatically and creates the default administrator:

```text
username: admin
password: admin
email: admin@localhost
```

The default administrator can be overridden through the PHP service environment variables:

```text
SCNUOJ_ADMIN_USERNAME
SCNUOJ_ADMIN_PASSWORD
SCNUOJ_ADMIN_EMAIL
```

The judge container starts `dispatcher` and `polygon` with `-o` by default.

New contests created from the admin contest page default to OI. After a contest is created successfully, the system automatically enables the exam login-guard mode and points `examContestId` to that new contest.
