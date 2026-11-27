<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache key prefix for expenses.
     */
    public const EXPENSES_TAG = 'expenses';

    /**
     * Cache key prefix for users.
     */
    public const USERS_TAG = 'users';

    /**
     * Default cache duration in hours.
     */
    public const DEFAULT_TTL = 24;

    /**
     * Remember a value in cache using tags.
     */
    public function remember(string $tag, string $key, \Closure $callback, int $hours = self::DEFAULT_TTL): mixed
    {
        return Cache::tags($tag)->remember($key, now()->addHours($hours), $callback);
    }

    /**
     * Get expenses list from cache or compute it.
     */
    public function getExpensesList(int $companyId, int $page, string $search, \Closure $callback): LengthAwarePaginator
    {
        $key = "expenses.company.{$companyId}.page.{$page}.search.{$search}";

        return $this->remember(self::EXPENSES_TAG, $key, $callback);
    }

    /**
     * Get users list from cache or compute it.
     */
    public function getUsersList(int $companyId, int $page, \Closure $callback): LengthAwarePaginator
    {
        $key = "users.company.{$companyId}.page.{$page}";

        return $this->remember(self::USERS_TAG, $key, $callback);
    }

    /**
     * Flush all expense cache entries for a company.
     */
    public function flushExpenseCache(int $companyId): void
    {
        Cache::tags(self::EXPENSES_TAG)->flush();
    }

    /**
     * Flush all user cache entries for a company.
     */
    public function flushUserCache(int $companyId): void
    {
        Cache::tags(self::USERS_TAG)->flush();
    }

    /**
     * Flush all application cache.
     */
    public function flushAll(): void
    {
        Cache::flush();
    }
}
