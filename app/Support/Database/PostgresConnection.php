<?php

namespace App\Support\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * The PostgreSQL connection, keeping one promise SQLite made: LIKE ignores case.
 *
 * Every search on the platform — members, clubs, people, products — was
 * written as `where(col, 'like', "%{$q}%")` against SQLite, where LIKE is
 * case-insensitive. PostgreSQL's LIKE is case-sensitive, so "ali" would stop
 * finding "Ali". The grammar below turns LIKE into ILIKE for every query-
 * builder where, so behaviour is unchanged rather than fixed in 126 places.
 *
 * Raw SQL (`whereRaw("… like ?")`) is not touched by a grammar and is written
 * with ILIKE by hand where it runs on PostgreSQL.
 *
 * Bound in AppServiceProvider::register() via Connection::resolverFor('pgsql').
 */
class PostgresConnection extends BasePostgresConnection
{
    protected function getDefaultQueryGrammar()
    {
        return new class($this) extends PostgresGrammar
        {
            protected function whereBasic(Builder $query, $where)
            {
                $op = strtolower(trim($where['operator']));

                if ($op === 'like' || $op === 'not like') {
                    $where['operator'] = $op === 'like' ? 'ilike' : 'not ilike';
                }

                return parent::whereBasic($query, $where);
            }
        };
    }
}
