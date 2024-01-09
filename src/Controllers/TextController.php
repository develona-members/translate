<?php

namespace Develona\Translate\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class TextController extends Controller
{

    private $db;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $db = config('translate.texts_db');
        $this->db = DB::connection($db);
    }

    public function index(Request $request)
    {
        if (!$request->session()->get('editable_texts')) abort(401);

        $lang = $request->input('lang');
        if ($lang !== config('translate.default_language')) {
            if (!in_array($lang, config('translate.translated_languages'))) {
                $lang = config('translate.default_language');
            }
        }
        App::setLocale($lang);

        $query = $this->db->table('translations_source')->where('translations_source.active', 1);

        $search = [
            'code' => $request->input('code'),
            'text' => $request->input('text'),
        ];

        $this->applySearch($query, $search, $lang);

        $rs = $query->pluck('translations_source.code');

        return view('translate::index', ['lang' => $lang, 'codes' => $rs, 'search' => $search]);
    }

    private function applySearch($query, $search, $lang)
    {
        $code = $search['code'] ? '%'.addcslashes(mb_strtolower($search['code']), '%_').'%' : null;
        $text = $search['text'] ? '%'.addcslashes(mb_strtolower($search['text']), '%_').'%' : null;
        if (!$code && !$text) return;

        if ($code) $query->where('translations_source.code', 'like', $code);
        if ($text) {
            if ($lang === config('translate.default_language')) {
                $query->where('translations_source.content', 'like', $text);
            } else {
                $query->leftJoin('translations_langs', function ($join) use ($lang) {
                    $join->on('translations_langs.source_id', '=', 'translations_source.id');
                    $join->where('translations_langs.lang', '=', $lang);
                })->where(function ($q) use ($text) {
                    $q->orWhere('translations_source.content', 'like', $text);
                    $q->orWhere('translations_langs.translated', 'like', $text);
                });
            }
        }
    }

    public function single(Request $request, $id, $lang)
    {
        if ($request->isMethod('POST')) {
            return $this->saveText($request, $id, $lang);
        }

        $r = $this->db->table('translations_source')
        ->leftJoin('translations_langs', function ($join) use ($lang) {
            $join->on('translations_langs.source_id', '=', 'translations_source.id');
            $join->where('translations_langs.lang', '=', $lang);
        })->where('translations_source.code', $id)
        ->select('translations_source.code', 'translations_source.content', 'translations_langs.translated')->first();

        if ($r) {
            return response()->json([
                'success' => true,
                'content' => (string)($r->translated ?: $r->content),
            ]);
        }
        return response()->json([
            'success' => false,
            'msg' => trans('translate::messages.text_not_found'),
        ]);
    }


    public function saveText(Request $request, $id, $lang)
    {
        if (!$request->session()->get('editable_texts')) {
            return response()->json([
                'success' => false,
                'msg' => trans('translate::messages.invalid_token'),
            ]);
        }

        $content = (string)$request->input('content');

        $default_lang = config('translate.default_language');
        $translated_languages = config('translate.translated_languages');

        if ($lang !== $default_lang && !in_array($lang, $translated_languages)) {
            return response()->json([
                'success' => false,
                'msg' => trans('translate::messages.invalid_parameters'),
            ]);
        }

        $r = $this->db->table('translations_source')->where('code', $id)->first();
        if (!$r) {
            return response()->json([
                'success' => false,
                'msg' => trans('translate::messages.text_not_found'),
            ]);
        }
        $dt = date('Y-m-d H:i:s');

        if ($lang === $default_lang) {
            $this->db->table('translations_source')->where('id', $r->id)->update([
                'content' => $content,
                'updated_at' => $dt,
            ]);
            return response()->json([
                'success' => true,
            ]);
        }

        $trans = $this->db->table('translations_langs')->where('source_id', $r->id)->where('lang', $lang)->first();
        if ($trans) {
            $this->db->table('translations_langs')->where('id', $trans->id)->update([
                'translated' => $content,
                'updated_at' => $dt,
                'revised_at' => $dt,
            ]);
        } else {
            $this->db->table('translations_langs')->insert([
                'source_id' => $r->id,
                'lang' => $lang,
                'translated' => $content,
                'created_at' => $dt,
                'updated_at' => $dt,
                'revised_at' => $dt,
            ]);
        }
        return response()->json([
            'success' => true,
        ]);
    }

}

