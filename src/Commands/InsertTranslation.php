<?php

namespace Develona\Translate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InsertTranslation extends Command
{
    protected $signature = 'translate:insert {code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert new translation in database';

    private $db;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $db = config('settings.texts_db');
        $this->db = \DB::connection($db);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $code = $this->argument('code');

        $skip_main = false;

        $r = $this->db->table('translations_source')->where('code', $code)->first();
        if ($r) {
            $this->warn('Text exists with content: '.$r->content);
            if ($this->confirm('Insert translations?')) {
                $skip_main = true;
            } else {
                return;
            }
        }

        $content = null;
        if (!$skip_main) {
            $content = $this->ask('Enter the source content');
        }

        $langs = [];
        foreach (config('settings.translated_languages') as $k) {
            $t = $this->ask("Enter the translated content [$k]");
            if ($t) $langs[$k] = $t;
        }

        $this->insertText($code, $content ?: $r, $langs);
        $this->info('Translation inserted.');
    }

    private function insertText($code, $content, $langs)
    {
        $dt = date('Y-m-d H:i:s');

        if (is_string($content)) {
            $data = [
                'content' => $content,
                'parsed_at' => null,
                'path' => null,
                'active' => 1,
                'updated_at' => $dt,
            ];

            $id = $this->db->table('translations_source')->insertGetId(array_merge($data, [
                'created_at' => $dt,
                'code' => $code,
            ]));
        } else {
            $id = $content->id;
        }

        foreach($langs as $k => $content) {
            $this->db->table('translations_langs')->insert([
                'source_id' => $id,
                'lang' => $k,
                'translated' => $content,
                'created_at' => $dt,
                'updated_at' => $dt,
            ]);
        }
    }



}
