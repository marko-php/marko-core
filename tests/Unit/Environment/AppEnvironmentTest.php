<?php

declare(strict_types=1);

use Marko\Core\Environment\AppEnvironment;

describe('AppEnvironment', function (): void {
    beforeEach(function (): void {
        $this->originalEnv = $_ENV;
        $this->originalMarkoEnv = getenv('MARKO_ENV');
        $this->originalAppEnv = getenv('APP_ENV');
        unset($_ENV['MARKO_ENV'], $_ENV['APP_ENV']);
        putenv('MARKO_ENV');
        putenv('APP_ENV');
    });

    afterEach(function (): void {
        $_ENV = $this->originalEnv;
        putenv($this->originalMarkoEnv === false ? 'MARKO_ENV' : 'MARKO_ENV=' . $this->originalMarkoEnv);
        putenv($this->originalAppEnv === false ? 'APP_ENV' : 'APP_ENV=' . $this->originalAppEnv);
    });

    it('defaults to production when no environment variable is set', function (): void {
        $environment = new AppEnvironment();

        expect($environment->name())->toBe('production')
            ->and($environment->isProduction())->toBeTrue()
            ->and($environment->isDevelopment())->toBeFalse();
    });

    it('treats an empty environment value as unset', function (): void {
        $environment = new AppEnvironment(['APP_ENV' => '']);

        expect($environment->name())->toBe('production')
            ->and($environment->isProduction())->toBeTrue();
    });

    it('treats production and prod as production case-insensitively', function (string $value): void {
        $environment = new AppEnvironment(['APP_ENV' => $value]);

        expect($environment->isProduction())->toBeTrue()
            ->and($environment->isDevelopment())->toBeFalse()
            ->and($environment->name())->toBe(strtolower($value));
    })->with(['production', 'prod', 'PRODUCTION', 'Prod']);

    it('returns the trimmed, lowercased name', function (): void {
        expect((new AppEnvironment(['APP_ENV' => ' Staging ']))->name())->toBe('staging');
    });

    it('reads the environment lazily on each call', function (): void {
        $environment = new AppEnvironment();

        $_ENV['APP_ENV'] = 'local';
        expect($environment->isDevelopment())->toBeTrue();

        $_ENV['APP_ENV'] = 'production';
        expect($environment->isProduction())->toBeTrue();
    });

    it('treats development, dev and local as development case-insensitively', function (string $value): void {
        $environment = new AppEnvironment(['APP_ENV' => $value]);

        expect($environment->isDevelopment())->toBeTrue()
            ->and($environment->isProduction())->toBeFalse();
    })->with(['development', 'dev', 'local', 'DEVELOPMENT', 'Local']);

    it('is neither production nor development for other names such as staging', function (): void {
        $environment = new AppEnvironment(['APP_ENV' => 'staging']);

        expect($environment->name())->toBe('staging')
            ->and($environment->isProduction())->toBeFalse()
            ->and($environment->isDevelopment())->toBeFalse();
    });

    it('treats testing and test as testing case-insensitively', function (string $value): void {
        $environment = new AppEnvironment(['APP_ENV' => $value]);

        expect($environment->isTesting())->toBeTrue()
            ->and($environment->isProduction())->toBeFalse()
            ->and($environment->isDevelopment())->toBeFalse();
    })->with(['testing', 'test', 'TESTING', 'Test']);

    it('does not treat production, development or staging names as testing', function (string $value): void {
        expect((new AppEnvironment(['APP_ENV' => $value]))->isTesting())->toBeFalse();
    })->with(['production', 'prod', 'development', 'dev', 'local', 'staging', 'tests']);

    it('does not treat an unset environment as testing', function (): void {
        expect((new AppEnvironment([]))->isTesting())->toBeFalse();
    });

    it('prefers MARKO_ENV over APP_ENV', function (): void {
        $environment = new AppEnvironment(['MARKO_ENV' => 'local', 'APP_ENV' => 'production']);

        expect($environment->name())->toBe('local')
            ->and($environment->isDevelopment())->toBeTrue();
    });

    it('prefers MARKO_ENV over APP_ENV when reading the real environment', function (): void {
        $_ENV['APP_ENV'] = 'production';
        putenv('MARKO_ENV=dev');

        expect((new AppEnvironment())->name())->toBe('dev');
    });

    it('reads APP_ENV from $_ENV', function (): void {
        $_ENV['APP_ENV'] = 'local';

        expect((new AppEnvironment())->isDevelopment())->toBeTrue();
    });

    it('reads APP_ENV from getenv when $_ENV lacks it', function (): void {
        putenv('APP_ENV=local');

        $environment = new AppEnvironment();

        expect($environment->name())->toBe('local')
            ->and($environment->isDevelopment())->toBeTrue();
    });

    it('prefers $_ENV over getenv for the same key', function (): void {
        $_ENV['APP_ENV'] = 'staging';
        putenv('APP_ENV=local');

        expect((new AppEnvironment())->name())->toBe('staging');
    });

    it('ignores the real environment when variables are injected', function (): void {
        $_ENV['APP_ENV'] = 'local';

        expect((new AppEnvironment([]))->name())->toBe('production');
    });
});
