<?php

namespace App\Services;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobSearch
{
    public function query(array $f, ?User $user)
    {
        $q = JobListing::active()->with('source');
        foreach (['company' => 'company_name', 'country' => 'country', 'city' => 'city', 'location' => 'location'] as $key => $column) {
            if (! empty($f[$key])) {
                if ($key === 'country') {
                    $q->where($column, $f[$key]);
                } else {
                    $q->where($column, 'like', '%'.$this->escape($f[$key]).'%');
                }
            }
        }
        if (! empty($f['q'])) {
            // Natural language stop words keep "Laravel developer in Dubai" useful.
            $words = preg_split('/\s+/u', trim($f['q']));
            foreach (array_slice(array_filter($words, fn ($w) => ! in_array(Str::lower($w), ['in', 'with', 'of', 'and', 'the', 'a', 'years', 'year', 'experience'])), 0, 12) as $word) {
                if (ctype_digit($word)) {
                    continue;
                }
                $word = ['uae' => 'AE', 'pakistan' => 'PK', 'uk' => 'GB'][Str::lower($word)] ?? $word;
                $q->where(function ($b) use ($word) {
                    $parts = preg_split('/[^\p{L}\p{N}]+/u', $word, -1, PREG_SPLIT_NO_EMPTY);
                    $token = collect($parts)->first(fn ($part) => mb_strlen($part) >= 3) ?? $word;
                    if (DB::getDriverName() === 'mysql' && mb_strlen($token) >= 3) {
                        $b->whereRaw('MATCH(title,description,company_name,location) AGAINST (? IN BOOLEAN MODE)', [$token.'*']);
                    } else {
                        $b->where(fn ($t) => $t->where('title', 'like', '%'.$this->escape($word).'%')->orWhere('description', 'like', '%'.$this->escape($word).'%')->orWhere('location', 'like', '%'.$this->escape($word).'%')->orWhere('company_name', 'like', '%'.$this->escape($word).'%'));
                    }
                    $b->orWhere('country', $word)->orWhere('workplace_type', $word);
                });
            }
        }
        foreach (['workplace' => 'workplace_type', 'type' => 'job_type', 'experience' => 'experience_level', 'source' => 'source_id', 'currency' => 'salary_currency', 'period' => 'salary_period'] as $key => $column) {
            if (! empty($f[$key])) {
                $q->where($column, $f[$key]);
            }
        }
        if (isset($f['salary_min'])) {
            $q->where(fn ($q) => $q->where('salary_max', '>=', $f['salary_min'])->orWhere(fn ($q) => $q->whereNull('salary_max')->where('salary_min', '>=', $f['salary_min'])));
        }
        if (isset($f['salary_max'])) {
            $q->where('salary_min', '<=', $f['salary_max']);
        }
        if (! empty($f['days'])) {
            $q->where('published_at', '>=', now()->subDays((int) $f['days']));
        }
        if (! empty($f['saved']) && $user) {
            $method = $f['saved'] === 'yes' ? 'whereHas' : 'whereDoesntHave';
            $q->$method('saves', fn ($b) => $b->where('user_id', $user->id));
        }

        return $q;
    }

    public function results(array $f, ?User $user): LengthAwarePaginator
    {
        $q = $this->query($f, $user);
        if (($f['sort'] ?? '') === 'relevance') {
            // Exact personalized order, bounded memory; SQL filters are applied first.
            $scorer = app(RelevanceScorer::class);
            $p = $user?->preference;
            $ranked = $q->reorder()->select('jobs.*')->cursor()->map(fn ($j) => ['id' => $j->id, 'score' => $scorer->score($j, $p)])->collect()->sortByDesc('score')->values();
            $ids = $ranked->forPage((int) ($f['page'] ?? 1), 15)->pluck('id');
            $models = JobListing::with('source')->whereIn('id', $ids)->get()->keyBy('id');

            return new LengthAwarePaginator($ids->map(fn ($id) => $models[$id])->values(), $ranked->count(), 15, (int) ($f['page'] ?? 1), ['path' => request()->url(), 'query' => request()->query()]);
        }
        $dir = ($f['sort'] ?? '') === 'oldest' ? 'asc' : 'desc';

        return $q->orderByRaw('COALESCE(published_at, first_seen_at) '.$dir)->orderBy('id', $dir)->paginate(15)->withQueryString();
    }

    private function escape(string $s): string
    {
        return addcslashes($s, '%_\\');
    }
}
