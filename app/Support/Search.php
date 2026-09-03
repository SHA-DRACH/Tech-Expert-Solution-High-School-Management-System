<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The one search behaviour every listing shares.
 *
 * Three things it does that a plain `LIKE %term%` does not.
 *
 * It matches on the start of any word, so typing "ko" finds Grace **Ko**llie
 * without also dragging in Jac**ko**n. Two letters is enough to narrow a list,
 * which is the whole point of typing into a search box.
 *
 * It splits what you typed. "gr ko" and "kollie grace" both find Grace Kollie,
 * because every word you type has to match somewhere but not all in the same
 * column. Searching a full name used to find nothing at all, since no single
 * column held both halves of it.
 *
 * It searches identifiers by fragment rather than by prefix, because nobody
 * types a student number from the beginning: they read the last few digits off
 * a form and type those.
 */
class Search
{
    /**
     * @param  array{words?: array<int, string>, identifiers?: array<int, string>, relations?: array<string, array<int, string>>}  $in
     */
    public static function apply(Builder $query, ?string $term, array $in): Builder
    {
        $tokens = self::tokens($term);

        if ($tokens === []) {
            /*
             | Nothing typed means no filter at all. But something typed that
             | left no usable token - "%", "_", punctuation alone - must match
             | nothing rather than everything. Falling through to an unfiltered
             | query would answer a search for "%" with the entire school,
             | which is precisely the behaviour stripping the wildcard was
             | meant to prevent.
             */
            return trim((string) $term) === '' ? $query : $query->whereRaw('1 = 0');
        }

        $words = $in['words'] ?? [];
        $identifiers = $in['identifiers'] ?? [];
        $relations = $in['relations'] ?? [];

        /*
         | Every token must match something (AND), but each one may match in any
         | of the columns (OR). That is what makes "kollie grace" work while
         | "kollie zzz" correctly finds nobody.
         */
        foreach ($tokens as $token) {
            $query->where(function (Builder $outer) use ($token, $words, $identifiers, $relations) {
                foreach ($words as $column) {
                    self::wordPrefix($outer, $column, $token);
                }

                foreach ($identifiers as $column) {
                    $outer->orWhere($column, 'like', '%'.$token.'%');
                }

                foreach ($relations as $relation => $columns) {
                    $outer->orWhereHas($relation, function (Builder $related) use ($columns, $token) {
                        $related->where(function (Builder $inner) use ($columns, $token) {
                            foreach ($columns as $column) {
                                self::wordPrefix($inner, $column, $token);
                            }
                        });
                    });
                }
            });
        }

        return $query;
    }

    /** Matches the column starting with the token, or any word inside it doing so. */
    protected static function wordPrefix(Builder $query, string $column, string $token): void
    {
        $query
            ->orWhere($column, 'like', $token.'%')
            ->orWhere($column, 'like', '% '.$token.'%')
            // Double-barrelled names and staff numbers: Kollie-Toe, GFI-T-006.
            ->orWhere($column, 'like', '%-'.$token.'%');
    }

    /**
     * Split what was typed into search tokens.
     *
     * `%`, `_` and `\` are stripped rather than escaped. They are LIKE
     * wildcards, and SQLite applies no default escape character, so a portable
     * escape would mean raw SQL on every clause. A single `%` typed into a
     * search box would otherwise match every row in the school, which is the
     * opposite of searching; no real name or student number contains one.
     *
     * @return array<int, string>
     */
    public static function tokens(?string $term): array
    {
        if ($term === null) {
            return [];
        }

        return collect(preg_split('/\s+/', trim($term)))
            ->map(fn (string $token) => str_replace(['%', '_', '\\'], '', $token))
            ->filter(fn (string $token) => $token !== '')
            // More than a handful of words is a paste, not a search, and each
            // one costs a set of LIKE clauses.
            ->take(6)
            ->values()
            ->all();
    }
}
