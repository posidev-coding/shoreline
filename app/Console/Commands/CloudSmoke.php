<?php

namespace App\Console\Commands;

use App\Jobs\CloudSmokeJob;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use Throwable;

/**
 * M0's "done" check for a Laravel Cloud environment, run from its Commands tab:
 * files land in both buckets, a queued job runs, and mail leaves through SMTP.
 * It also reports the Cloud spike items (imagick HEIC, pg_trgm, outbound SMTP).
 */
#[Signature('app:cloud-smoke
    {--mail= : Also send a test email to this address from the queue worker}
    {--wait=60 : Seconds to wait for a queue worker to run the test job}')]
#[Description('Check that this environment can store files, run queued jobs, and send mail')]
class CloudSmoke extends Command
{
    /** A 320×240 HEIC (tests/Fixtures/door.heic), to prove Imagick can decode one. */
    private const string HEIC_SAMPLE = 'AAAAHGZ0eXBoZWljAAAAAG1pZjFoZWljbWlhZgAAAVZtZXRhAAAAAAAAACFoZGxyAAAAAAAAAABwaWN0AAAAAAAAAAAAAAAAAAAAACJpbG9jAAAAAERAAAEAAQAAAAABegABAAAAAAAAAukAAAAjaWluZgAAAAAAAQAAABVpbmZlAgAAAAABAABodmMxAAAAAA5waXRtAAAAAAABAAAA1mlwcnAAAAC3aXBjbwAAAHhodmNDAQNwAAAAkAAAAAAAPPAA/P34+AAADwNgAAEAGEABDAH//wNwAAADAJAAAAMAAAMAPLoCQGEAAQArQgEBA3AAAAMAkAAAAwAAAwA8oAoIDxZbqSSmubgIaDAgAAADAyAAAAMAIWIAAQAHRAHBcrBiQAAAABNjb2xybmNseAABAA0ABoAAAAAUaXNwZQAAAAAAAAFAAAAA8AAAABBwaXhpAAAAAAMICAgAAAAXaXBtYQAAAAAAAAABAAEEgQIDBAAAAvFtZGF0AAAC5SgBrwnIJLzVkkAhOXVfX/6G9//+BOchzsEdBxhqpWjvjypY//R5rzc9e5up3S7tdf4S/cxY4txCNIhd4cu9epkqSLrvIByD4QH42CsejR/umkTetlqE3YMiGHQw6WPR4sEX8GW4QmBGoKUtCPmy4IIW+RM4uPUZ+fvGMasyCOEeE1gCyFayOZTqmyZdfTz6WCZeZK60vcxtodvBCjxsICRipBNweV4Sr+oKSucpZzAinj2BPmIc9NpZiWBmUMoIc3DO5L+4eFEYOfEouVGj7cabbH+QEt1KwNFJZS+GwL9DlSgClJbB7PFW001xSPWWMEgkSywSFL4GpZXoqdH95gL3nLbYTdGD8SRQeOo1Oh75QUj/r2iKBLS/Bhfa3zCdLt03hS2e2A6k/D5sLeF4W4X/GldRTCaYdw4c4GFMpqCEeBAHKeq2vdYtl9457sqYXszNQ7T1QiSLWYmrv/OYhWrgJCEeiQ+7tssQqWtFGcwwdQaw/NP/R5r38cQY8ltrK9398ReKqgUDHNNcCcAZ4KlpQvf/cBH6kSkZMovw0ZWBrYC1SrO1loFz2TGUulOdF3ORcAeEstSEkahPwIpyikAZ606lqPeF7AP5q2I8Pe2fz/4PUOQAAAMAQUhrfst/eIghCRZMYABbjIAm/loiH/+PZc8kbYEXJ+Dy4GIZ7isZCwJLuIWdTMdW9B2r7sP9cL4jfb+G+m9rTTsEn9KtVWjttlwvVLDCiHjVM3zxc2NW1cVC4aZlQt/P51DTRBTX+7F1qAke7wZ6xHtTBLME5WXYkPRwSVyQ1eEiv3cyfEskBVuRTrv+2KhefH/1l4vjUZox1IfD/jV9H6XL2A7F2QSk3fv+xfmgzQvnWpy3rsvwEUNk9TFc6Mbhav4hynjUQAwcM5qOI3SZFatq5i2I9VPSuuvO2z51uAPZSVgKJLgpFnDjMCKXnGjn/b1thXDwtoSgxarjPxDUwqVcHzoeFyLhiI/XYA==';

    /** @var list<array{string, string, string}> */
    private array $rows = [];

    private bool $failed = false;

    public function handle(): int
    {
        $runId = Str::lower((string) Str::ulid());
        $mailTo = $this->option('mail');
        $mailTo = is_string($mailTo) && $mailTo !== '' ? $mailTo : null;
        $wait = max(0, (int) $this->option('wait'));

        foreach (['private', 'public_media'] as $disk) {
            $this->check("Storage: {$disk}", fn (): array => $this->checkDisk($disk, $runId));
        }

        CloudSmokeJob::dispatch($runId, $mailTo);
        $result = $this->waitForJob($runId, $wait);
        $connection = (string) config('queue.default');

        $this->check('Queue', fn (): array => $result === null
            ? [false, "No worker ran the test job within {$wait}s (connection: {$connection})"]
            : [true, "Ran on {$result['host']} (connection: {$connection})"]);

        if ($mailTo !== null) {
            $mailer = (string) config('mail.default');

            $this->check('Mail', fn (): array => match (true) {
                $result === null => [false, 'Not attempted, because the test job did not run'],
                $result['mail_error'] !== null => [false, $result['mail_error']],
                default => [true, "Sent to {$mailTo} with the {$mailer} mailer; check the inbox"],
            });
        }

        $this->finding('Imagick', $this->imagickSupport(...));
        $this->finding('Postgres pg_trgm', $this->pgTrgmStatus(...));
        $this->finding('Outbound SMTP', $this->smtpReachability(...));

        $this->table(['Check', 'Result', 'Detail'], $this->rows);

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Writes a file, reads it back, fetches it over HTTP the way visitors will, then deletes it.
     *
     * @return array{bool, string}
     */
    private function checkDisk(string $disk, string $runId): array
    {
        $storage = Storage::disk($disk);
        $path = "cloud-smoke/{$runId}.txt";
        $contents = "Shoreline cloud smoke test {$runId}";
        $driver = (string) config("filesystems.disks.{$disk}.driver");

        try {
            if (! $storage->put($path, $contents)) {
                return [false, "Could not write to the {$driver} disk"];
            }

            if ($storage->get($path) !== $contents) {
                return [false, "Wrote to the {$driver} disk but could not read the file back"];
            }

            $url = $disk === 'private'
                ? $storage->temporaryUrl($path, now()->addMinutes(5))
                : $storage->url($path);
            $response = Http::timeout(10)->get($url);

            if (! $response->successful() || $response->body() !== $contents) {
                return [false, "Wrote and read the file, but {$url} answered HTTP {$response->status()}"];
            }

            return [true, "{$driver}: wrote, read, and fetched it by its ".($disk === 'private' ? 'temporary URL' : 'public URL')];
        } finally {
            $storage->delete($path);
        }
    }

    /**
     * @return array{host: string, mail_error: string|null}|null
     */
    private function waitForJob(string $runId, int $seconds): ?array
    {
        $deadline = now()->addSeconds($seconds);

        do {
            $result = Cache::get(CloudSmokeJob::cacheKey($runId));

            if (is_array($result)) {
                /** @var array{host: string, mail_error: string|null} $result */
                return $result;
            }

            Sleep::for(1)->second();
        } while (now()->lessThan($deadline));

        return null;
    }

    /**
     * Decodes a real HEIC rather than trusting queryFormats(): Imagick lists HEIC even when
     * libheif's HEVC decoder plugin is missing, and then can't open an iPhone photo.
     */
    private function imagickSupport(): string
    {
        if (! extension_loaded('imagick')) {
            return 'Extension not loaded';
        }

        $formats = array_values(array_intersect(['HEIC', 'HEIF', 'AVIF', 'WEBP'], Imagick::queryFormats()));
        $listed = 'Lists '.($formats === [] ? 'none of HEIC, HEIF, AVIF, WEBP' : implode(', ', $formats));
        $sample = (string) tempnam(sys_get_temp_dir(), 'heic-');
        file_put_contents($sample, base64_decode(self::HEIC_SAMPLE));

        try {
            $image = new Imagick($sample);

            return "{$listed}; decoded a HEIC photo ({$image->getImageWidth()}×{$image->getImageHeight()})";
        } catch (ImagickException $exception) {
            return "{$listed}; can’t decode HEIC, so iPhone photos stay HEIC ({$exception->getMessage()})";
        } finally {
            unlink($sample);
        }
    }

    private function pgTrgmStatus(): string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return 'Not a Postgres connection';
        }

        $extension = DB::selectOne("select default_version, installed_version from pg_available_extensions where name = 'pg_trgm'");

        return match (true) {
            $extension === null => 'Not available',
            $extension->installed_version !== null => "Installed ({$extension->installed_version})",
            default => "Available ({$extension->default_version}), not installed yet",
        };
    }

    private function smtpReachability(): string
    {
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $tls = config('mail.mailers.smtp.scheme') === 'smtps';
        $started = microtime(true);

        $socket = @stream_socket_client(($tls ? 'ssl' : 'tcp')."://{$host}:{$port}", $errorCode, $errorMessage, 5);

        if ($socket === false) {
            return "Cannot connect to {$host}:{$port}: {$errorMessage}";
        }

        fclose($socket);

        return sprintf('%s:%d reachable%s in %d ms', $host, $port, $tls ? ' over TLS' : '', (microtime(true) - $started) * 1000);
    }

    /**
     * @param  Closure(): array{bool, string}  $check
     */
    private function check(string $name, Closure $check): void
    {
        try {
            [$passed, $detail] = $check();
        } catch (Throwable $e) {
            [$passed, $detail] = [false, $e::class.': '.$e->getMessage()];
        }

        $this->failed = $this->failed || ! $passed;
        $this->rows[] = [$name, $passed ? 'PASS' : 'FAIL', $detail];
    }

    /**
     * Records a finding that doesn't decide whether the environment passes.
     *
     * @param  Closure(): string  $finding
     */
    private function finding(string $name, Closure $finding): void
    {
        try {
            $detail = $finding();
        } catch (Throwable $e) {
            $detail = $e::class.': '.$e->getMessage();
        }

        $this->rows[] = [$name, 'INFO', $detail];
    }
}
