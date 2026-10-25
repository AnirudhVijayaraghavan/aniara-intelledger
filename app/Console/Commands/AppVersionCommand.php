<?php

namespace App\Console\Commands;

use Brick\Math\BigInteger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

#[Signature('app:version {bump? : major, minor, or patch; omit to display the current version}')]
#[Description('Display or increment the application semantic version')]
class AppVersionCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $bump = $this->argument('bump');

        if ($bump !== null && ! in_array($bump, ['major', 'minor', 'patch'], true)) {
            $this->error('Choose major, minor, or patch.');

            return self::INVALID;
        }

        $path = Config::string('app.version_file', base_path('VERSION'));

        if (! File::isFile($path)) {
            $this->error('Version file is missing.');

            return self::FAILURE;
        }

        $current = trim(File::get($path));

        if (! preg_match('/\A(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\z/', $current)) {
            $this->error('Version must use MAJOR.MINOR.PATCH without leading zeros.');

            return self::FAILURE;
        }

        if ($bump === null) {
            $this->line($current);

            return self::SUCCESS;
        }

        [$major, $minor, $patch] = explode('.', $current);
        $next = match ($bump) {
            'major' => BigInteger::of($major)->plus(1).'.0.0',
            'minor' => $major.'.'.BigInteger::of($minor)->plus(1).'.0',
            'patch' => $major.'.'.$minor.'.'.BigInteger::of($patch)->plus(1),
        };

        File::replace($path, $next.PHP_EOL);
        $this->info("Version updated: {$current} -> {$next}");

        return self::SUCCESS;
    }
}
