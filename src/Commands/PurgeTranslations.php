<?php

namespace Develona\Translate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PurgeTranslations extends Command
{
    protected $signature = 'translate:purge {--dir=views}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set unused texts as inactive in the database';

    private $results = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dt = date('Y-m-d H:i:s');

        $dir_name = resource_path($this->option('dir'));

        $dir_it = new \RecursiveDirectoryIterator($dir_name);
        $it = new \RecursiveIteratorIterator($dir_it);
        $files = new \RegexIterator($it, "/.+\.blade\.php$/", \RegexIterator::GET_MATCH);
        foreach($files as $file) {
            $path = $file[0];
            $content = file_get_contents($path);

            $regex = "/@e\('([a-z0-9_-]+)'.*?\)/s";

            $matches = [];
            preg_match_all($regex, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $code = $match[1];
                $this->results[$code] = true;
            }
        }

        $codes = array_keys($this->results);
        \DB::table('translations_source')->whereIn('code', $codes)->update([
            'parsed_at' => $dt,
            'active' => 1,
        ]);
        \DB::table('translations_source')->where('parsed_at', '!=', $dt)->update([
            'active' => 0,
        ]);

    }


}