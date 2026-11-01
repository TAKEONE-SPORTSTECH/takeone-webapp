# Performance — what is slow and why

Since 30 Oct 2026, staging and production share one PostgreSQL database on
192.168.0.54. SQLite answered a query in-process in microseconds. PostgreSQL
answers over the network in **about 0.4–0.7 ms per query**, and opening a
connection with TLS costs **about 18 ms**. Code that was cheap on SQLite (a
query per row, the "N+1" pattern) is now the main cost.

## Rules

- **No query inside a loop over rows.** Eager-load relations (`with([...])`).
  Load a set once (`whereIn`), then look things up in memory.
- **A predicate asked about many members takes a pre-loaded set.** Example:
  `User::canViewPublicProfile($viewer, $blockedIds)`. The rule stays in one
  place.
- **Remember schema checks once they are true.** `Schema::hasTable` is a
  catalogue query on PostgreSQL.
- **Measure before and after.** Count queries with `DB::listen`, and compare
  the rendered HTML byte for byte.

## Persistent connections — `DB_PERSISTENT`

`config/database.php` → `pgsql.options[PDO::ATTR_PERSISTENT]`. It is off unless
`.env` says `DB_PERSISTENT=true`. It is ON on staging. A `DB_*` key is a server
setting, so `ship.sh` does **not** mirror it: set it in production's `.env` by
hand.

- **What it does:** each Apache worker keeps its database connection between
  requests, instead of doing a new TLS handshake every time.
- **Measured** against the local Apache, 40 requests each (median):

  | Page | Before | After |
  |---|---|---|
  | `/screen` | 55 ms | 36 ms |
  | `/login` | 37 ms | 35 ms |

- **Watch:** connections ≈ live Apache children on each box. Prefork allows up
  to 150 per box, and PostgreSQL `max_connections` is 200. Idle children above
  `MaxSpareServers` (10) exit and close their connections.
- **Safety:** if a request dies mid-transaction, PDO rolls it back when the
  connection is released. Check that `pg_stat_activity` shows no
  `idle in transaction` rows.
- **Undo:** set `DB_PERSISTENT=false` in `.env`, then run `config:cache`.

## Measured page costs (31 Oct 2026)

| Page | Before | After |
|---|---|---|
| Federation event Results | 4,465 queries / 4.8 s | 936 queries / 2.0 s |
| `/me/events/{uuid}` | 804 queries / 1.54 s | 410 queries / 1.23 s |
| Draw page | 437 queries / 0.80 s | 235 queries / 0.76 s |

Most of what remains is PHP building large payloads. The federation dashboard
builds every tab's data on every load.

## Redis — the cache, not the sessions (31 Oct 2026)

Each box runs its own Redis: `redis-server` and `php8.2-redis`.

- **Setup:** listens on `127.0.0.1` only, with a password (`REDIS_PASSWORD` in
  each `.env`). AOF persistence is on, `maxmemory 1gb`, `volatile-lru`. The
  original config is saved as `/etc/redis/redis.conf.orig`.
- **Cache:** `CACHE_STORE=redis` on both boxes. On production, the live entries
  in the database `cache` table were copied across first. Staging's file cache
  was only derived data.
- ⚠️ **Never `cache:clear` / `optimize:clear` on a live box.** The cache holds
  live state, not just derived data: mat scoreboards (`MatState`, 4 h),
  sign-in and invitation codes, login lockouts and throttles.
- **Sessions stay in the database (`SESSION_DRIVER=database`).** Signing a user
  out everywhere deletes their rows from the `sessions` table: password reset,
  role changes and account deletion (`User`, `NewPasswordController`). Redis
  sessions have no per-user index, so those deletes would silently do nothing,
  and a stolen session would survive a password reset. Moving sessions needs a
  sign-out that works with either driver first. The gain would be about 1 ms
  per request.
- **Not a shared lock store:** each box has its own Redis. Making sure
  scheduled jobs run on only one box (`onOneServer`) needs one Redis both
  boxes reach.
