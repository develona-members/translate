<?php

namespace Develona\Translate\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class EditableTexts
{

    public function handle($request, Closure $next)
    {
        $edit_texts = $request->input('editable_texts');
        if ($edit_texts === 'end') {
            $request->session()->forget('editable_texts');
            return redirect()->route('home');
        } elseif ($token = $request->input('token')) {
            if ($this->verifyToken($token)) {
                $request->session()->put('editable_texts', true);
                return redirect()->route('home');
            }
        }
        return $next($request);
    }


    private function verifyToken($token)
    {
        if ($token && $token == config('translate.texts_ext_access')) return true;

        $a = explode('-', $token);
        $ts = (int)($a[0] ?? 0);
        $sig = ($a[1] ?? '');
        $id = (int)($a[2] ?? 0);
        if (!$ts || !$sig || !$id) return false;

        $dt = date('YmdHis');
        if ($dt - $ts > 300) {
            return false;
        }

        $r = DB::table('translations_source')->where('id', $id)->first();
        if (!$r) return false;

        if ($sig !== md5($ts.$r->created_at)) return false;

        return true;
    }
}
