<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * ListQueryParams — standar parsing parameter list endpoint (PRD 0.5).
 *
 * Parameter yang didukung:
 *   per_page   : 25 (default), 50, 75, 100
 *   page       : nomor halaman
 *   sort       : field (opsional prefix "-" untuk descending)
 *   search     : string pencarian global (kunci: q)
 *
 * Dipakai bersama ApiResponse::paginated().
 */
class ListQueryParams
{
    public const ALLOWED_PER_PAGE = [25, 50, 75, 100];

    public const DEFAULT_PER_PAGE = 25;

    public int $perPage;

    public int $page;

    public ?string $sortField;

    public string $sortDirection;

    public ?string $search;

    public function __construct(int $perPage, int $page, ?string $sortField, string $sortDirection, ?string $search)
    {
        $this->perPage = $perPage;
        $this->page = $page;
        $this->sortField = $sortField;
        $this->sortDirection = $sortDirection;
        $this->search = $search;
    }

    public static function fromRequest(Request $request, array $allowedSorts = []): self
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $page = max(1, (int) $request->input('page', 1));

        $sort = $request->input('sort');
        $sortField = null;
        $sortDirection = 'asc';

        if ($sort && is_string($sort)) {
            if (str_starts_with($sort, '-')) {
                $sortField = substr($sort, 1);
                $sortDirection = 'desc';
            } else {
                $sortField = $sort;
                $sortDirection = 'asc';
            }

            if (! empty($allowedSorts) && ! in_array($sortField, $allowedSorts, true)) {
                $sortField = null;
                $sortDirection = 'asc';
            }
        }

        $search = $request->input('q');
        if ($search !== null && ! is_string($search)) {
            $search = null;
        }

        return new self($perPage, $page, $sortField, $sortDirection, $search);
    }

    public function apply(Builder $query, array $searchableFields = []): Builder
    {
        if ($this->search && ! empty($searchableFields)) {
            $query->where(function (Builder $q) use ($searchableFields) {
                foreach ($searchableFields as $i => $field) {
                    if ($i === 0) {
                        $q->where($field, 'like', "%{$this->search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$this->search}%");
                    }
                }
            });
        }

        if ($this->sortField) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest();
        }

        return $query;
    }
}
