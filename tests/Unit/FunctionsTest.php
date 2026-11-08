<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!defined('SEME_VERSION')) {
    define('SEME_VERSION', 'test');
}

require_once $GLOBALS['SEMEDIR']->kero.'Functions.php';
// Functions.php registers seme_error_handling() globally on include.
// Drop it so it can't hijack PHPUnit's own error handling.
restore_error_handler();

#[CoversFunction('base_url')]
#[CoversFunction('base_url_admin')]
#[CoversFunction('base_url_front')]
#[CoversFunction('redir')]
#[CoversFunction('register_namespace')]
#[CoversFunction('seme_error_handling')]
#[CoversFunction('seme_fatal')]
#[CoversFunction('seme_terminate')]
#[CoversFunction('dd')]
#[CoversFunction('dd_format')]
#[CoversFunction('seme_fatal')]
#[CoversFunction('dd')]
final class FunctionsTest extends TestCase
{
    private mixed $originalConfig;
    private mixed $originalSession;

    protected function setUp(): void
    {
        $this->originalConfig = $GLOBALS['SEMECFG'] ?? null;
        $this->originalSession = $_SESSION ?? null;

        $GLOBALS['SEMECFG'] = (object) [
            'baseurl'       => 'https://example.test/',
            'baseurl_admin' => 'admin',
            'baseurl_front' => 'front',
        ];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['SEME_TERMINATOR'], $GLOBALS['SEME_DD_TERMINATOR']);

        if ($this->originalConfig === null) {
            unset($GLOBALS['SEMECFG']);
        } else {
            $GLOBALS['SEMECFG'] = $this->originalConfig;
        }
        if ($this->originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->originalSession;
        }
        unset($GLOBALS['SEME_TERMINATOR']);
        unset($GLOBALS['SEME_DD_TERMINATOR']);
    }

    // ---- base_url* -------------------------------------------------------

    public function testBaseUrlWithoutArgumentReturnsConfiguredBase(): void
    {
        $this->assertSame('https://example.test/', base_url());
    }

    public function testBaseUrlAppendsPathVerbatim(): void
    {
        $this->assertSame('https://example.test/blog/post-1', base_url('blog/post-1'));
    }

    public function testBaseUrlDoesNotNormaliseSlashes(): void
    {
        // Documents current behaviour: no slash trimming or de-duplication.
        $this->assertSame('https://example.test//x', base_url('/x'));
    }

    public function testBaseUrlAdminUsesAdminSegment(): void
    {
        $this->assertSame('https://example.test/admin/', base_url_admin());
        $this->assertSame('https://example.test/admin/users/1', base_url_admin('users/1'));
    }

    public function testBaseUrlAdminFollowsConfiguredSecretUrl(): void
    {
        $GLOBALS['SEMECFG']->baseurl_admin = 'mastermind';

        $this->assertSame('https://example.test/mastermind/dash', base_url_admin('dash'));
    }

    public function testBaseUrlFrontUsesFrontSegment(): void
    {
        $this->assertSame('https://example.test/front/', base_url_front());
        $this->assertSame('https://example.test/front/about', base_url_front('about'));
    }

    public function testBaseUrlFrontWarnsWhenBaseurlFrontIsNotConfigured(): void
    {
        // index.php never sets baseurl_front, so out of the box this
        // function reads an undefined property. Pinned so a fix is visible.
        unset($GLOBALS['SEMECFG']->baseurl_front);

        $warning = null;
        set_error_handler(function (int $no, string $str) use (&$warning) {
            $warning = [$no, $str];
            return true;
        });
        try {
            $result = base_url_front('x');
        } finally {
            restore_error_handler();
        }

        $this->assertSame('https://example.test//x', $result);
        $this->assertSame(E_WARNING, $warning[0]);
        $this->assertStringContainsString('baseurl_front', $warning[1]);
    }

    // ---- redir -----------------------------------------------------------

    /** @return array<string, array{int|string, int|string, string}> */
    public static function htmlRedirectProvider(): array
    {
        return [
            'no delay defaults to 1s'      => [0, 1, '<meta http-equiv="refresh" content="1;URL=\'https://x.test/a\'" />'],
            'explicit delay'               => [5, 1, '<meta http-equiv="refresh" content="5;URL=\'https://x.test/a\'" />'],
            'type given as string "1"'     => [3, '1', '<meta http-equiv="refresh" content="3;URL=\'https://x.test/a\'" />'],
        ];
    }

    #[DataProvider('htmlRedirectProvider')]
    public function testRedirHtmlTypeEmitsMetaRefresh(int|string $time, int|string $type, string $expected): void
    {
        $this->expectOutputString($expected);

        redir('https://x.test/a', $time, $type);
    }

    public function testRedirHtmlTypeDoesNotEscapeUrl(): void
    {
        // Documents current behaviour: the URL is echoed raw into HTML,
        // so callers must never pass user-controlled input.
        $this->expectOutputString('<meta http-equiv="refresh" content="1;URL=\'\'><script>\'" />');

        redir("'><script>", 0, 1);
    }

    #[DataProvider('headerRedirectProvider')]
    public function testRedirHeaderTypeEmitsNoBody(int $time): void
    {
        // header() is a no-op under the CLI SAPI, so we can only assert
        // that nothing is written to the body.
        $this->expectOutputString('');

        redir('https://x.test/a', $time, 0);
    }

    /** @return array<string, array{int}> */
    public static function headerRedirectProvider(): array
    {
        return ['immediate' => [0], 'delay of 1' => [1], 'delayed' => [5]];
    }

    // ---- register_namespace ---------------------------------------------

    public function testRegisterNamespaceStoresValueInSession(): void
    {
        register_namespace('App\\Controllers');

        $this->assertSame('App\\Controllers', $_SESSION['namespace']);
    }

    public function testRegisterNamespaceOverwritesPreviousValue(): void
    {
        register_namespace('First');
        register_namespace('Second');

        $this->assertSame('Second', $_SESSION['namespace']);
    }

    public function testRegisterNamespaceDefaultsToGlobalNamespace(): void
    {
        register_namespace();

        $this->assertSame('', $_SESSION['namespace']);
    }

    // ---- seme_error_handling --------------------------------------------

    /** @return array<string, array{int}> */
    public static function ignoredLevelProvider(): array
    {
        return [
            'E_DEPRECATED'      => [E_DEPRECATED],
            'E_USER_DEPRECATED' => [E_USER_DEPRECATED],
            'E_NOTICE'          => [E_NOTICE],
            'E_USER_NOTICE'     => [E_USER_NOTICE],
        ];
    }

    #[DataProvider('ignoredLevelProvider')]
    public function testErrorHandlerDefersNoticesAndDeprecations(int $level): void
    {
        // Returning false hands the error back to PHP's standard handler
        // and must happen before any output or die().
        $this->expectOutputString('');

        $this->assertFalse(seme_error_handling($level, 'msg', __FILE__, 1));
    }
    
    // ---- seme_fatal / seme_terminate ------------------------------------

    public function testSemeTerminateCallsInjectedTerminatorWithMessage(): void
    {
        $received = null;
        $GLOBALS['SEME_TERMINATOR'] = function ($msg) use (&$received) {
            $received = $msg;
        };

        seme_terminate('bye');

        $this->assertSame('bye', $received);
    }

    public function testSemeFatalTerminatesInsteadOfDying(): void
    {
        // seme_error_handling() prints its report and then terminates.
        // The injected terminator throws, so the test survives and proves
        // the handler goes through seme_terminate() and not a bare die().
        $GLOBALS['SEME_TERMINATOR'] = function ($msg) {
            throw new RuntimeException('terminated');
        };

        ob_start(); // the handler writes an error report
        try {
            seme_fatal('boom');
            $this->fail('seme_fatal should terminate');
        } catch (RuntimeException $e) {
            $this->assertSame('terminated', $e->getMessage());
            $report = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertStringContainsString('Error: ['.E_USER_ERROR.'] boom', $report);
        $this->assertStringContainsString('Seme Framework v'.SEME_VERSION, $report);
    }

    // ---- dd / dd_format -------------------------------------------------

    public function testDdFormatInCliReturnsPrintROutputWithTrailingNewline(): void
    {
        // PHP_SAPI is 'cli' under PHPUnit, so only the CLI branch is
        // reachable here. print_r() ends in "\n" and dd_format() adds one more.
        $this->assertSame("Array\n(\n    [a] => 1\n)\n\n", dd_format(['a' => 1]));
    }

    public function testDdFormatHandlesScalars(): void
    {
        $this->assertSame('hello'.PHP_EOL, dd_format('hello'));
        $this->assertSame(PHP_EOL, dd_format(null));
    }

    public function testDdPrintsThenTerminates(): void
    {
        $GLOBALS['SEME_DD_TERMINATOR'] = function () {
            throw new RuntimeException('dd-exit');
        };

        $this->expectOutputString("Array\n(\n    [a] => 1\n)\n\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dd-exit');

        dd(['a' => 1]);
    }
}
