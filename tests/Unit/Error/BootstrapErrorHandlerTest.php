<?php

declare(strict_types=1);

use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Error\BootstrapErrorHandler;

function bootstrapHandlerRender(
    BootstrapErrorHandler $handler,
    Throwable $throwable,
): string {
    $originalLog = ini_get('error_log');
    $logFile = sys_get_temp_dir() . '/marko-bootstrap-log-' . bin2hex(random_bytes(8));
    ini_set('error_log', $logFile);

    ob_start();

    try {
        $handler->handle($throwable);
    } finally {
        $output = (string) ob_get_clean();
        ini_set('error_log', (string) $originalLog);

        if (is_file($logFile)) {
            unlink($logFile);
        }
    }

    return $output;
}

describe('BootstrapErrorHandler', function (): void {
    beforeEach(function (): void {
        $this->originalAccept = $_SERVER['HTTP_ACCEPT'] ?? null;
        $this->originalDisplayErrors = ini_get('display_errors');
    });

    afterEach(function (): void {
        if ($this->originalAccept === null) {
            unset($_SERVER['HTTP_ACCEPT']);
        } else {
            $_SERVER['HTTP_ACCEPT'] = $this->originalAccept;
        }

        ini_set('display_errors', (string) $this->originalDisplayErrors);
    });

    it('renders a generic message without details outside development', function (): void {
        $handler = new BootstrapErrorHandler(new AppEnvironment([]));

        $output = bootstrapHandlerRender($handler, new RuntimeException('secret /var/www/app detail'));

        expect($output)->toBe("Server Error\n");
    });

    it('renders a generic JSON body when the request accepts JSON outside development', function (): void {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $handler = new BootstrapErrorHandler(new AppEnvironment(['APP_ENV' => 'production']));

        $output = bootstrapHandlerRender($handler, new RuntimeException('secret detail'));

        expect($output)->toBe('{"message":"Server Error"}');
    });

    it('shows the escaped exception message in development', function (): void {
        $handler = new BootstrapErrorHandler(new AppEnvironment(['APP_ENV' => 'development']));

        $output = bootstrapHandlerRender($handler, new RuntimeException('<script>x</script>'));

        expect($output)->toContain('RuntimeException: &lt;script&gt;x&lt;/script&gt;')
            ->and($output)->not->toContain('<script>');
    });

    it('writes the full exception to error_log', function (): void {
        $handler = new BootstrapErrorHandler(new AppEnvironment([]));
        $originalLog = ini_get('error_log');
        $logFile = sys_get_temp_dir() . '/marko-bootstrap-log-' . bin2hex(random_bytes(8));
        ini_set('error_log', $logFile);

        ob_start();
        $handler->handle(new RuntimeException('logged detail'));
        ob_end_clean();
        ini_set('error_log', (string) $originalLog);

        $log = (string) file_get_contents($logFile);
        unlink($logFile);

        expect($log)->toContain('RuntimeException: logged detail')
            ->and($log)->toContain('Stack trace');
    });

    it('turns display_errors off outside development when registered', function (): void {
        ini_set('display_errors', '1');
        $handler = new BootstrapErrorHandler(new AppEnvironment([]));

        $handler->register();
        $displayErrors = ini_get('display_errors');
        $handler->unregister();

        expect($displayErrors)->toBe('0');
    });

    it('leaves display_errors alone in development', function (): void {
        ini_set('display_errors', '1');
        $handler = new BootstrapErrorHandler(new AppEnvironment(['APP_ENV' => 'local']));

        $handler->register();
        $displayErrors = ini_get('display_errors');
        $handler->unregister();

        expect($displayErrors)->toBe('1');
    });

    it('installs itself as the active exception handler and removes itself on unregister', function (): void {
        $handler = new BootstrapErrorHandler(new AppEnvironment([]));

        $handler->register();
        $activeAfterRegister = $handler->isActive();
        $handler->unregister();

        expect($activeAfterRegister)->toBeTrue()
            ->and($handler->isActive())->toBeFalse();
    });

    it('does not remove a handler installed on top of it', function (): void {
        $handler = new BootstrapErrorHandler(new AppEnvironment([]));
        $other = static function (Throwable $throwable): void {};

        $handler->register();
        set_exception_handler($other);
        $handler->unregister();

        $current = set_exception_handler(null);
        restore_exception_handler();

        // Clean up: the other handler, then the bootstrap one underneath it
        restore_exception_handler();
        restore_exception_handler();

        expect($current)->toBe($other);
    });
});
