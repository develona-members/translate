<?php

namespace Develona\Translate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InsertTranslation extends Command
{
    protected $signature = 'translate:insert {key} {--editor : Use external editor for input}';
    protected $description = 'Insert new translation in database';

    private $db;
    private $stdin;

    public function __construct()
    {
        parent::__construct();
        $db = config('translate.texts_db', 'mysql');
        $this->db = DB::connection($db);
        $this->stdin = STDIN;
    }

    public function handle(): int
    {
        $key = $this->argument('key');

        // Validate key format
        if (!preg_match("/^[a-z0-9_-]+$/", $key)) {
            $this->error("Invalid key format. Use only lowercase letters, numbers, hyphens, and underscores.");
            return 1;
        }

        $skipMainContent = false;
        $existingRecord = null;

        // Check if key already exists
        $existingRecord = $this->db->table('translations_source')->where('code', $key)->first();

        if ($existingRecord) {
            $this->warn("Key '$key' already exists with content:");
            $this->line($existingRecord->content);
            $this->newLine();

            if ($this->confirm('Skip main content and add translations only?', false)) {
                $skipMainContent = true;
            } else {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $defaultContent = null;
        if (!$skipMainContent) {
            $defaultContent = $this->getContentInput('Enter the default content');

            if (empty(trim($defaultContent))) {
                $this->error('Content cannot be empty.');
                return 1;
            }
        }

        // Get translations for each language
        $translations = [];
        $languages = config('translate.translated_languages', []);

        if (!empty($languages)) {
            $this->newLine();

            foreach ($languages as $locale) {
                // In editor mode, ask before opening editor for each language
                if ($this->option('editor')) {
                    if (!$this->confirm("Add translation for [$locale]?", true)) {
                        continue;
                    }
                }

                $translatedContent = $this->getContentInput("Translation for [$locale]", true);

                if (!empty(trim($translatedContent))) {
                    $translations[$locale] = $translatedContent;
                }
            }
        }

        // Insert or update
        $this->storeTranslation($key, $defaultContent, $existingRecord, $translations);

        $this->newLine();
        $this->info("✓ Translation '$key' inserted successfully.");

        return 0;
    }

    private function getContentInput(string $prompt, bool $allowEmpty = false): string
    {
        if ($this->option('editor')) {
            return $this->readFromEditor($prompt, $allowEmpty);
        }

        return $this->readFromStdin($prompt, $allowEmpty);
    }

    private function readFromStdin(string $prompt, bool $allowEmpty): string
    {
        $this->info($prompt . ' (press Ctrl+D or Ctrl+Z when finished):');

        // Reopen stdin if it's at EOF
        if (feof($this->stdin)) {
            $this->reopenStdin();
        }

        $lines = [];

        while (true) {
            $line = fgets($this->stdin);

            // Check for EOF (Ctrl+D on Unix, Ctrl+Z on Windows)
            if ($line === false) {
                break;
            }

            $lines[] = rtrim($line, "\n");
        }

        // Reopen stdin for next input
        $this->reopenStdin();

        return implode("\n", $lines);
    }

    private function reopenStdin(): void
    {
        // Close current stdin handle
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }

        // Reopen stdin from terminal
        if (PHP_OS_FAMILY === 'Windows') {
            $this->stdin = fopen('CON', 'r');
        } else {
            $this->stdin = fopen('/dev/tty', 'r');
        }

        if (!$this->stdin) {
            throw new \RuntimeException('Failed to reopen stdin');
        }
    }

    private function readFromEditor(string $prompt, bool $allowEmpty): string
    {
        $this->comment($prompt);

        // Create an empty temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'translation_');
        file_put_contents($tmpFile, '');

        // Determine which editor to use
        $editor = getenv('EDITOR') ?: (PHP_OS_FAMILY === 'Windows' ? 'notepad' : 'vim');

        // Open in editor
        $descriptorspec = [
            ['file', '/dev/tty', 'r'],
            ['file', '/dev/tty', 'w'],
            ['file', '/dev/tty', 'w']
        ];

        $process = proc_open("$editor " . escapeshellarg($tmpFile), $descriptorspec, $pipes);

        if (is_resource($process)) {
            proc_close($process);
        }

        // Read content
        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        return trim($content);
    }

    private function storeTranslation(
        string $key,
        ?string $defaultContent,
        ?object $existingRecord,
        array $translations
    ): void {
        $timestamp = now()->toDateTimeString();

        // Insert or get ID for main translation
        if ($defaultContent !== null) {
            $data = [
                'content' => $defaultContent,
                'parsed_at' => null,
                'path' => null,
                'active' => 1,
                'updated_at' => $timestamp,
            ];

            $id = $this->db->table('translations_source')->insertGetId(array_merge($data, [
                'created_at' => $timestamp,
                'code' => $key,
            ]));
        } else {
            $id = $existingRecord->id;
        }

        // Insert translations for each language
        foreach ($translations as $locale => $content) {
            $this->db->table('translations_langs')->insert([
                'source_id' => $id,
                'lang' => $locale,
                'translated' => $content,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }
}