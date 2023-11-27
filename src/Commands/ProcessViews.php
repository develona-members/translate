<?php

namespace Develona\Translate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProcessViews extends Command
{
    protected $signature = 'translate:views {--dir=views} {--file=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replaces static texts in views with the translate function';

    private $results = [];
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
        $dt = date('Y-m-d H:i:s');

        if ($this->option('file')) {
            $filepath = resource_path($this->option('file'));
            $files = [[$filepath]];
        } else {
            $dir_name = resource_path($this->option('dir'));

            $dir_it = new \RecursiveDirectoryIterator($dir_name);
            $it = new \RecursiveIteratorIterator($dir_it);
            $files = new \RegexIterator($it, "/.+\.blade\.php$/", \RegexIterator::GET_MATCH);
        }

        foreach($files as $file) {
            $path = $file[0];
            $content = file_get_contents($path);

            //
            // <p>**btn_back: Back**</p>
            //
            $regex = "@\*{2}\s*([a-z0-9_-]+)\s*\:\s*(.+?)\*{2}@s";
            $count1 = 0;
            $str = preg_replace_callback(
                $regex,
                function ($match) use ($dt, $path) {
                    $original = $match[0];
                    $code = $match[1];
                    $text = trim($match[2]);
                    return $this->handleText($original, $code, $text, $dt, $path);
                },
                $content,
                -1,
                $count1
            );

            //
            // <p>{{ strtr('num_results: :num results.', [':num' => count($rs)]) }} </p>
            //
            $regex = "@\{(?:!!|\{)\s*strtr\('(.+?)'(\s*,\s*.+?)?\)\s*(?:!!|\})\}@s";
            $count2 = 0;
            $str = preg_replace_callback(
                $regex,
                function ($match) use ($dt, $path) {
                    $original = $match[0];
                    $code_text = $match[1];
                    $subs = trim($match[2]);

                    $a = preg_split("/\s*:\s*/", $code_text, 2);
                    $code = $a[0];
                    $text = $a[1] ?? null;
                    if (!preg_match("/^[a-z0-9_-]+$/", $code)) {
                        $this->warn("Invalid code $code in $path, skipping.");
                        return $original;
                    }
                    return $this->handleText($original, $code, $text, $dt, $path, $subs);
                },
                $str,
                -1,
                $count2
            );

            if ($count1 + $count2) {
                // $this->info($str);
                file_put_contents($path, $str);
            }
        }

    }

    private function handleText($original, $code, $text, $dt, $path, $subs = '')
    {
        if (isset($this->results[$code])) {
            if ($this->results[$code]['text'] !== $text) {
                $this->warn("Skipping $code in $path: different content found in ".$this->results[$code]['path']);
                return $original;
            }
        }
        $data = [
            'content' => $text,
            'parsed_at' => $dt,
            'path' => $path,
            'active' => 1,
            'updated_at' => $dt,
        ];
        $r = $this->db->table('translations_source')->where('code', $code)->first();
        if ($r) {
            if ($r->content !== $text) {
                $this->warn("Skipping $code in $path: different content found in database");
                return $original;
            }
            $this->db->table('translations_source')->where('code', $code)->update($data);
        } else {
            $this->db->table('translations_source')->insert(array_merge($data, [
                'created_at' => $dt,
                'code' => $code,
            ]));
        }
        $this->results[$code] = [
            'text' => $text,
            'path' => $path,
        ];
        if ($subs) {
            $subs = preg_replace("/\s+/", ' ', $subs);
            $str = "@t('$code'$subs)";
            $this->info($str);
            return $str;
        }
        $str = "@t('$code')";
        $this->info($str);
        return $str;
    }



}
