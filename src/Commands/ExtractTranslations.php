<?php

namespace Develona\Translate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtractTranslations extends Command
{
    protected $signature = 'translate:extract {--dir=views} {--file=} {--dry-run}';
    protected $description = 'Extract translation keys from views and store in database';

    private array $processedKeys = [];
    private array $conflicts = [];
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
        $files = $this->getFilesToProcess();

        $totalReplacements = 0;

        foreach ($files as $file) {
            $path = $file[0];
            $replacements = $this->processFile($path, $timestamp);
            $totalReplacements += $replacements;
        }

        $this->displaySummary($totalReplacements);

        return empty($this->conflicts) ? 0 : 1;
    }

    private function getFilesToProcess(): iterable
    {
        if ($this->option('file')) {
            return [[resource_path($this->option('file'))]];
        }

        $dirName = resource_path($this->option('dir'));
        $dirIt = new \RecursiveDirectoryIterator($dirName);
        $it = new \RecursiveIteratorIterator($dirIt);

        return new \RegexIterator($it, "/.+\.blade\.php$/", \RegexIterator::GET_MATCH);
    }

    private function processFile(string $path, string $timestamp): int
    {
        $content = file_get_contents($path);
        $modified = $content;
        $totalCount = 0;

        // Pattern 1: **key: text**
        $modified = preg_replace_callback(
            "@\*{2}\s*([a-z0-9_-]+)\s*:\s*(.+?)\*{2}@s",
            function ($match) use ($timestamp, $path, &$totalCount) {
                $replacement = $this->storeTranslationKey(
                    $match[0],
                    $match[1],
                    trim($match[2]),
                    $timestamp,
                    $path
                );
                if ($replacement !== $match[0]) $totalCount++;
                return $replacement;
            },
            $modified
        );

        // Pattern 2: {{ strtr('key: text', [...]) }}
        $modified = preg_replace_callback(
            "@\{(?:!!|\{)\s*strtr\('(.+?)'(\s*,\s*.+?)?\)\s*(?:!!|\})\}@s",
            function ($match) use ($timestamp, $path, &$totalCount) {
                $codeText = $match[1];
                $subs = isset($match[2]) ? trim($match[2]) : '';

                $parts = preg_split("/\s*:\s*/", $codeText, 2);
                $key = $parts[0];
                $text = $parts[1] ?? '';

                $replacement = $this->storeTranslationKey(
                    $match[0],
                    $key,
                    $text,
                    $timestamp,
                    $path,
                    $subs
                );
                if ($replacement !== $match[0]) $totalCount++;
                return $replacement;
            },
            $modified
        );

        if ($totalCount > 0 && !$this->option('dry-run')) {
            file_put_contents($path, $modified);
        }

        if ($totalCount > 0) {
            $mode = $this->option('dry-run') ? '[DRY RUN] Would update' : 'Updated';
            $this->info("$mode $path ($totalCount replacements)");
        }

        return $totalCount;
    }

    private function storeTranslationKey(
        string $original,
        string $key,
        string $text,
        string $timestamp,
        string $path,
        string $substitutions = ''
    ): string {
        // Validate key format
        if (!preg_match("/^[a-z0-9_-]+$/", $key)) {
            $this->warn("Invalid key '$key' in $path, skipping.");
            return $original;
        }

        // Check for conflicts within this run
        if (isset($this->processedKeys[$key]) && $this->processedKeys[$key]['text'] !== $text) {
            $this->conflicts[] = [
                'key' => $key,
                'reason' => "Different content in $path vs {$this->processedKeys[$key]['path']}"
            ];
            $this->warn("Skipping '$key' in $path: different content in {$this->processedKeys[$key]['path']}");
            return $original;
        }

        // Check database
        $existing = $this->db->table('translations_source')->where('code', $key)->first();

        if ($existing && $existing->content !== $text) {
            $this->conflicts[] = [
                'key' => $key,
                'reason' => "Different content in database vs $path"
            ];
            $this->warn("Skipping '$key' in $path: different content in database");
            return $original;
        }

        // Update or insert (skip if dry-run)
        if (!$this->option('dry-run')) {
            $data = [
                'content' => $text,
                'parsed_at' => $timestamp,
                'path' => $path,
                'active' => 1,
                'updated_at' => $timestamp,
            ];

            if ($existing) {
                $this->db->table('translations_source')->where('code', $key)->update($data);
            } else {
                $this->db->table('translations_source')->insert(array_merge($data, [
                    'created_at' => $timestamp,
                    'code' => $key,
                ]));
            }
        }

        // Track result
        $this->processedKeys[$key] = [
            'text' => $text,
            'path' => $path,
        ];

        // Generate replacement
        if ($substitutions) {
            $substitutions = preg_replace("/\s+/", ' ', $substitutions);
            return "@t('$key'$substitutions)";
        }

        return "@t('$key')";
    }

    private function displaySummary(int $total): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════");
        $this->info("  Extraction Complete");
        $this->info("═══════════════════════════════════════");
        $this->info("Total replacements: $total");
        $this->info("Unique keys: " . count($this->processedKeys));

        if (!empty($this->conflicts)) {
            $this->newLine();
            $this->error("⚠ Conflicts found (" . count($this->conflicts) . "):");
            foreach ($this->conflicts as $conflict) {
                $this->error("  • {$conflict['key']}: {$conflict['reason']}");
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment("🔍 DRY RUN - No changes were made");
        }
    }
}