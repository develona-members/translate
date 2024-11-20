Generate a url with a valid access token:

```php
    public function get_editable_texts_url()
    {
        $r = \DB::table('translations_source')->inRandomOrder()->limit(1)->first();
        $id = $r->id;
        $c = $r->created_at;

        $dt = date('YmdHis');
        $sig = md5($dt.$c);

        $token = $dt.'-'.$sig.'-'.$id;
        return response()->json([
            'url' => config('settings.front_url').'?editable_texts=1&token='.$token,
        ]);
    }
```
