<?php

namespace Develona\Translate;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Connection;

class Translate
{
    private Connection $db;
    private string $defaultLang;
    private string $appLang;
    private string $cacheKey;

    private array $texts = [];
    private array $requestedKeys = [];
    private bool $displayHints;

    private const CACHE_TTL = 172800; // 2 days in seconds
    private const CACHE_ENTRIES_LIMIT = 100;

    public function __construct(string $defaultLang, string $db)
    {
        $this->db = DB::connection($db);
        $this->defaultLang = $defaultLang;
        $this->appLang = App::getLocale();
        $this->displayHints = (bool) session('editable_texts', false);
        $this->cacheKey = 'text_keys:'.md5($this->getRequestIdentifier());
        $this->loadTextsFromCache();

        // Register cache update to run when Laravel terminates
        app()->terminating(function () {
            $this->updateCacheIfNeeded();
        });
    }

    private function getRequestIdentifier(): string
    {
        $request = request();
        $route = $request->route();
        $identifier = $route?->getName() ?? $request->path();
        return $identifier;
    }

    private function loadTextsFromCache(): void
    {
        $cachedKeys = Cache::get($this->cacheKey, []);

        if (!empty($cachedKeys)) {
            // Load all texts in a single query for current language
            $this->texts = $this->loadTextsFromDb($cachedKeys);
        }
    }

    private function updateCacheIfNeeded(): void
    {
        if (empty($this->requestedKeys)) {
            return;
        }

        $existingKeys = Cache::get($this->cacheKey, []);
        $requestedUnique = array_unique($this->requestedKeys);

        // Check if there are any new keys not in the cache
        $newKeys = array_diff($requestedUnique, $existingKeys);

        if (!empty($newKeys)) {
            // Merge existing and new keys
            $allKeys = array_unique(array_merge($existingKeys, $requestedUnique));

            // Cap the cache size?
            if (count($allKeys) > self::CACHE_ENTRIES_LIMIT) {
                \Log::warning("Translation cache for route exceeded limit", [
                    'route' => $this->getRequestIdentifier(),
                    'key_count' => count($allKeys),
                    'cached_keys' => self::CACHE_ENTRIES_LIMIT,
                ]);

                // Cache only the first 100 keys
                $allKeys = array_slice($allKeys, 0, self::CACHE_ENTRIES_LIMIT);
            }

            Cache::put($this->cacheKey, $allKeys, self::CACHE_TTL);
        }
    }

    public function trans(string $key, array $subs = []): string
    {
        $this->requestedKeys[] = $key;

        // If text not loaded yet, fetch it individually
        if (!isset($this->texts[$key])) {
            $this->texts[$key] = $this->fetchText($key);
        }

        $str = $this->texts[$key] ?? $key;

        return $subs ? strtr($str, $subs) : $str;
    }

    public function strip(string $key, array $subs = []): string
    {
        return strip_tags($this->trans($key, $subs));
    }

    public function html(string $key, array $subs = []): string
    {
        $str = $this->trans($key, $subs);

        if ($this->displayHints) {
            $escapedKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            return "<span data-text=\"{$escapedKey}\"></span>{$str}";
        }

        return $str;
    }

    public function setEditableTexts(bool $value = true): void
    {
        $this->displayHints = $value;
    }

    private function fetchText(string $key): ?string
    {
        $result = $this->queryText($key);

        if (!$result) {
            return null;
        }

        return $this->appLang === $this->defaultLang
            ? $result->content
            : ($result->translated ?? $result->content);
    }

    private function queryText(string $key): ?object
    {
        $query = $this->db->table('translations_source')
            ->where('code', $key);

        if ($this->appLang !== $this->defaultLang) {
            $query->leftJoin('translations_langs', function ($join) {
                $join->on('translations_langs.source_id', '=', 'translations_source.id')
                     ->where('translations_langs.lang', '=', $this->appLang);
            })->select('translations_source.content', 'translations_langs.translated');
        } else {
            $query->select('translations_source.content');
        }

        return $query->first();
    }

    private function loadTextsFromDb(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $query = $this->db->table('translations_source')
            ->whereIn('code', $keys);

        if ($this->appLang === $this->defaultLang) {
            return $query->pluck('content', 'code')->toArray();
        }

        $results = $query
            ->leftJoin('translations_langs', function ($join) {
                $join->on('translations_langs.source_id', '=', 'translations_source.id')
                     ->where('translations_langs.lang', '=', $this->appLang);
            })
            ->select('translations_source.code', 'translations_source.content', 'translations_langs.translated')
            ->get();

        $texts = [];
        foreach ($results as $result) {
            $texts[$result->code] = $result->translated ?? $result->content;
        }

        return $texts;
    }

    /**
     * Clear the key cache for specific path or all paths
     */
    public static function clearKeyCache(?string $path = null): void
    {
        if ($path === null) {
            // Clear all translation key caches
            Cache::flush(); // Or use a pattern if your cache driver supports it
        } else {
            $key = 'translate_keys:' . md5($path);
            Cache::forget($key);
        }
    }
}