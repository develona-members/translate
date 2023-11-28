<?php

namespace Develona\Translate;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class Translate
{

    private $cached_keys = [];
    private $missing_keys = [];
    private $texts = [];
    private $app_lang;
    private $default_lang;

    private $cache_id;
    private $display_hints = null;

    private $db;

    function __construct($default_lang, $db)
    {
        $this->db = DB::connection($db);
        $this->default_lang = $default_lang;
        $this->app_lang = App::getLocale();
        $request = request();
        $this->cache_id = 'text_keys_'.$request->path();
        $this->cached_keys = Cache::get($this->cache_id, []);
        if ($this->cached_keys) {
            $this->loadTexts($this->cached_keys);
        }
    }

    function __destruct()
    {
        if ($this->needsCacheRebuild()) {
            $keys = array_merge($this->cached_keys, array_keys($this->missing_keys));
            $keys = array_values(array_unique($keys));
            Cache::put($this->cache_id, $keys, 3600*24*7);
        }
    }

    private function needsCacheRebuild()
    {
        if (!$this->cached_keys || $this->missing_keys) {
            return true;
        }
        return false;
    }

    public function trans($key, $subs = []) {
        $str = null;
        if (isset($this->texts[$key])) {
            $str = $this->texts[$key];
        } else {
            $this->missing_keys[$key] = true;
            $str = $this->getText($key);
            $this->texts[$key] = $str;
        }
        if (!$str) $str = $key;
        if ($subs) $str = strtr($str, $subs);
        return $str;
    }

    public function strip($key, $subs = []) {
        return strip_tags($this->trans($key, $subs));
    }

    public function html($key, $subs = [])
    {
        if ($this->display_hints === null) {
            $this->display_hints = (bool)session('editable_texts');
        }
        $str = $this->trans($key, $subs);
        if ($this->display_hints) {
            $str = "<span data-text=\"$key\"></span>".$str;
        }
        return $str;
    }

    public function setEditableTexts($v = true)
    {
        $this->display_hints = (bool)$v;
    }

    private function getText($key)
    {
        if ($this->app_lang === $this->default_lang) {
            $r = $this->db->table('translations_source')->where('code', $key)->first();
            if ($r) return $r->content;
            return null;
        } else {
            $r = $this->db->table('translations_source')
                ->leftJoin('translations_langs', function ($join) {
                    $join->on('translations_langs.source_id', '=', 'translations_source.id');
                    $join->where('translations_langs.lang', '=', $this->app_lang);
                })->where('translations_source.code', $key)
                ->select('translations_source.code', 'translations_source.content', 'translations_langs.translated')->first();

            if ($r) return $r->translated ?: $r->content;
            return null;
        }
    }

    private function loadTexts($keys)
    {
        if ($this->app_lang === $this->default_lang) {
            $rs = $this->db->table('translations_source')->whereIn('code', $keys)->pluck('content', 'code');
            foreach ($rs as $k => $v) {
                $this->texts[$k] = $v;
            }
        } else {
            $rs = $this->db->table('translations_source')
                ->leftJoin('translations_langs', function ($join) {
                    $join->on('translations_langs.source_id', '=', 'translations_source.id');
                    $join->where('translations_langs.lang', '=', $this->app_lang);
                })->whereIn('translations_source.code', $keys)
                ->select('translations_source.code', 'translations_source.content', 'translations_langs.translated')->get();
            foreach ($rs as $r) {
                $this->texts[$r->code] = $r->translated ?: $r->content;
            }
        }
    }

}
