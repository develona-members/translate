<?php

namespace Develona\Translate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeTranslations extends Command
{
    protected $signature = 'translate:purge {--dir=views} {--dry-run}';
    protected $description = 'Mark unused translation keys as inactive in the database';

    private array $activeKeys = [];
    private $db;

    public function __construct()
    {
        parent::__construct();
        $db = config('translate.texts_db', 'mysql');
        $this->db = DB::connection($db);
    }

    public function handle(): int
    {
        $timestamp = now()->toDateTimeString();

        // Scan all blade files for translation keys
        $this->scanViewFiles();

        if (empty($this->activeKeys)) {
            $this->warn('No translation keys found in views.');
            return 0;
        }

        $activeKeysArray = array_keys($this->activeKeys);

        // Get counts before update
        $totalKeys = $this->db->table('translations_source')->count();
        $keysToDeactivate = $this->db->table('translations_source')
            ->whereNotIn('code', $activeKeysArray)
            ->where('active', 1)
            ->count();

        $this->info("Found " . count($activeKeysArray) . " active keys in views");
        $this->info("Total keys in database: $totalKeys");
        $this->info("Keys to deactivate: $keysToDeactivate");

        if ($keysToDeactivate === 0) {
            $this->info('✓ All database keys are in use. Nothing to purge.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('🔍 DRY RUN - Would deactivate the following keys:');
            $keysToShow = $this->db->table('translations_source')
                ->whereNotIn('code', $activeKeysArray)
                ->where('active', 1)
                ->pluck('code');

            foreach ($keysToShow as $key) {
                $this->line("  • $key");
            }

            return 0;
        }

        // Confirm before deactivating
        if (!$this->confirm("Deactivate $keysToDeactivate unused keys?", false)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Mark found keys as active and update parsed_at
        $this->db->table('translations_source')
            ->whereIn('code', $activeKeysArray)
            ->update([
                'parsed_at' => $timestamp,
                'active' => 1,
            ]);

        // Mark unfound keys as inactive
        $this->db->table('translations_source')
            ->whereNotIn('code', $activeKeysArray)
            ->where('active', 1)
            ->update([
                'active' => 0,
            ]);

        $this->newLine();
        $this->info("✓ Deactivated $keysToDeactivate unused keys");

        return 0;
    }

    private function scanViewFiles(): void
    {
        $dirName = resource_path($this->option('dir'));

        if (!is_dir($dirName)) {
            $this->error("Directory not found: $dirName");
            return;
        }

        $dirIt = new \RecursiveDirectoryIterator($dirName);
        $it = new \RecursiveIteratorIterator($dirIt);
        $files = new \RegexIterator($it, "/.+\.blade\.php$/", \RegexIterator::GET_MATCH);

        $fileCount = 0;

        foreach ($files as $file) {
            $path = $file[0];
            $content = file_get_contents($path);
            $fileCount++;

            // Pattern 1: @t('key') or @t('key', [...])
            $this->extractKeys($content, "/@t\('([a-z0-9_-]+)'.*?\)/s");

            // Pattern 2: T::trans('key') or T::trans('key', [...])
            $this->extractKeys($content, "/T::trans\('([a-z0-9_-]+)'.*?\)/s");

            // Pattern 3: T::html('key') or T::html('key', [...])
            $this->extractKeys($content, "/T::html\('([a-z0-9_-]+)'.*?\)/s");

            // Pattern 4: T::strip('key') or T::strip('key', [...])
            $this->extractKeys($content, "/T::strip\('([a-z0-9_-]+)'.*?\)/s");
        }

        $this->info("Scanned $fileCount blade files");
    }

    private function extractKeys(string $content, string $pattern): void
    {
        $matches = [];
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $this->activeKeys[$key] = true;
        }
    }
}