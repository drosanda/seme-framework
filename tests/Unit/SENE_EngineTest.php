<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Engine.php';

/**
 * Routing tests for SENE_Engine.
 *
 * Requests are dispatched for real against tests/Fixtures/controller, whose
 * controllers echo a short marker, so assertions are on the output. That
 * keeps the tests independent of how newRouteFolder() is structured and
 * lets them guard a refactor of it.
 *
 * Tests marked "CURRENT BEHAVIOUR" pin behaviour that is arguably a bug
 * (see bugs-notes.md); update them together with the fix.
 */
#[CoversClass(SENE_Engine::class)]
final class SENE_EngineTest extends TestCase
{
    private mixed $savedDir;
    private mixed $savedCfg;
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedDir = $GLOBALS['SEMEDIR'];
        $this->savedCfg = $GLOBALS['SEMECFG'] ?? null;
        $this->savedServer = $_SERVER;

        $dirs = clone $GLOBALS['SEMEDIR'];
        $dirs->app_controller = SEMEROOT.'tests/Fixtures/controller/';
        $GLOBALS['SEMEDIR'] = $dirs;

        $GLOBALS['SEMECFG'] = $this->makeConfig();
        $GLOBALS['__fixture_ctor_count'] = 0;

        // The engine rewrites $_SERVER[method] from argv when argv has more
        // than one entry, which is the case for `phpunit --filter ...`.
        $_SERVER['argv'] = ['phpunit'];
        unset($_SERVER['PATH_INFO'], $_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['SEMEDIR'] = $this->savedDir;
        if ($this->savedCfg === null) {
            unset($GLOBALS['SEMECFG']);
        } else {
            $GLOBALS['SEMECFG'] = $this->savedCfg;
        }
        $_SERVER = $this->savedServer;
        unset($GLOBALS['SEME_TERMINATOR'], $GLOBALS['__fixture_ctor_count']);
    }

    private function makeConfig(array $override = []): stdClass
    {
        return (object) array_merge([
            'baseurl'         => 'https://example.test/',
            'method'          => 'PATH_INFO',
            'baseurl_admin'   => 'admin',
            'controller_main' => 'home',
            'controller_404'  => 'notfound',
            'core_prefix'     => '',
            'core_controller' => '',
            'core_model'      => '',
            'routes'          => [],
            'saltkey'         => 'test',
            'environment'     => 'development',
            'database'        => new stdClass(),
            'semevar'         => new stdClass(),
        ], $override);
    }

    private function configure(array $override): void
    {
        $GLOBALS['SEMECFG'] = $this->makeConfig($override);
    }

    /** Dispatch a request path and return what the controller echoed. */
    private function dispatch(string $path): string
    {
        $key = $GLOBALS['SEMECFG']->method;
        $_SERVER[$key] = $path;

        ob_start();
        try {
            (new SENE_Engine())->run();
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    private function callOvrRoutes(array $paths): array
    {
        $engine = new SENE_Engine();
        $m = new ReflectionMethod($engine, 'ovrRoutes');

        return $m->invoke($engine, $paths);
    }

    // ---- default and 404 -------------------------------------------------

    public function testMissingPathInfoRunsDefaultController(): void
    {
        ob_start();
        (new SENE_Engine())->run();

        $this->assertSame('home-index', ob_get_clean());
    }

    #[DataProvider('defaultPathProvider')]
    public function testEmptyPathRunsDefaultController(string $path): void
    {
        $this->assertSame('home-index', $this->dispatch($path));
    }

    /** @return array<string, array{string}> */
    public static function defaultPathProvider(): array
    {
        return ['empty' => [''], 'root' => ['/']];
    }

    public function testUnknownControllerRunsNotFound(): void
    {
        $this->assertSame('notfound', $this->dispatch('/does-not-exist'));
    }

    public function testConfiguredDefaultControllerIsHonoured(): void
    {
        $this->configure(['controller_main' => 'my_page']);

        $this->assertSame('my-page-index', $this->dispatch('/'));
    }

    // ---- controller and method resolution -------------------------------

    public function testControllerWithoutMethodCallsIndex(): void
    {
        $this->assertSame('blog-index', $this->dispatch('/blog'));
    }

    public function testExplicitMethodIsCalled(): void
    {
        $this->assertSame('blog-index', $this->dispatch('/blog/index'));
    }

    public function testDashesInControllerAndMethodBecomeUnderscores(): void
    {
        $this->assertSame('my-page-index', $this->dispatch('/my-page'));
        $this->assertSame('dash-ok', $this->dispatch('/blog/dash-method'));
    }

    public function testUnknownMethodRunsNotFound(): void
    {
        $this->assertSame('notfound', $this->dispatch('/blog/nope'));
    }

    // ---- arguments -------------------------------------------------------

    public function testSegmentsAfterMethodBecomeArguments(): void
    {
        $this->assertSame("show:'42'", $this->dispatch('/blog/show/42'));
    }

    public function testMissingArgumentUsesParameterDefault(): void
    {
        $this->assertSame('show:5', $this->dispatch('/blog/show'));
    }

    public function testMissingRequiredArgumentIsNull(): void
    {
        $this->assertSame("pair:'x',NULL", $this->dispatch('/blog/pair/x'));
    }

    public function testSurplusSegmentsAreIgnored(): void
    {
        $this->assertSame("show:'1'", $this->dispatch('/blog/show/1/2/3'));
    }

    // ---- nested controller directories ----------------------------------

    public function testControllerInsideDirectory(): void
    {
        $this->assertSame('summary-index', $this->dispatch('/reports/summary'));
        $this->assertSame("summary-year:2024", $this->dispatch('/reports/summary/year/2024'));
    }

    public function testControllerTwoDirectoriesDeep(): void
    {
        $this->assertSame('monthly-index', $this->dispatch('/reports/sales/monthly'));
        $this->assertSame('monthly-show:mar', $this->dispatch('/reports/sales/monthly/show/mar'));
    }

    public function testDirectoryWithMissingControllerRunsNotFound(): void
    {
        $this->assertSame('notfound', $this->dispatch('/reports/missing'));
        $this->assertSame('notfound', $this->dispatch('/reports/sales/missing'));
    }

    // ---- methods that must not be reachable -----------------------------

    #[DataProvider('unroutableMethodProvider')]
    public function testNonRoutableMethodsRunNotFound(string $method): void
    {
        $this->assertSame('notfound', $this->dispatch('/blog/'.$method));
    }

    /** @return array<string, array{string}> */
    public static function unroutableMethodProvider(): array
    {
        return [
            'magic method'               => ['__construct'],
            'public but __ prefixed'     => ['__hidden'],
            'protected'                  => ['guarded'],
            'inherited from base class'  => ['getInstance'],
        ];
    }

    public function testNonRoutableMethodDoesNotInstantiateController(): void
    {
        $this->dispatch('/blog/__hidden');

        // Only NotFound is built; Blog's constructor bumps the counter.
        $this->assertSame(0, $GLOBALS['__fixture_ctor_count']);
    }

    public function testRoutableMethodInstantiatesControllerOnce(): void
    {
        $this->dispatch('/blog/index');

        $this->assertSame(1, $GLOBALS['__fixture_ctor_count']);
    }

    // ---- path traversal --------------------------------------------------

    #[DataProvider('traversalProvider')]
    public function testTraversalSegmentsRunNotFound(string $path): void
    {
        $out = $this->dispatch($path);

        $this->assertSame('notfound', $out);
        $this->assertStringNotContainsString('pwned', $out);
    }

    /** @return array<string, array{string}> */
    public static function traversalProvider(): array
    {
        return [
            'parent file'          => ['/../outside/index'],
            'parent via dir'       => ['/reports/../../outside/index'],
            'dot segment'          => ['/./blog'],
            'trailing parent'      => ['/blog/..'],
        ];
    }

    public function testSymlinkEscapingControllerDirRunsNotFound(): void
    {
        // No ".." in the URL, so only the realpath prefix check
        // (insideControllerDir) stands between this and a foreign file.
        $link = SEMEROOT.'tests/Fixtures/controller/escape';
        if (!@symlink(SEMEROOT.'tests/Fixtures/outside_dir', $link)) {
            $this->markTestSkipped('Cannot create symlinks on this filesystem.');
        }

        try {
            $out = $this->dispatch('/escape/leaked');
        } finally {
            unlink($link);
        }

        $this->assertSame('notfound', $out);
    }

    // ---- admin re-routing -----------------------------------------------

    public function testAdminAliasWorksWithDefaultSecret(): void
    {
        $this->assertSame('dashboard-index', $this->dispatch('/admin/dashboard'));
        $this->assertSame('stats:day', $this->dispatch('/admin/dashboard/stats'));
        $this->assertSame('stats:week', $this->dispatch('/admin/dashboard/stats/week'));
    }

    public function testSecretAdminUrlMapsToAdminDirectory(): void
    {
        $this->configure(['baseurl_admin' => 'mastermind']);

        $this->assertSame('dashboard-index', $this->dispatch('/mastermind/dashboard'));
    }

    public function testLiteralAdminIsHiddenWhenSecretUrlIsConfigured(): void
    {
        $this->configure(['baseurl_admin' => 'mastermind']);

        $this->assertSame('notfound', $this->dispatch('/admin/dashboard'));
    }

    public function testBrokenControllerClassInDirectoryIsFatal(): void
    {
        // admin/broken.php does not declare class "broken".
        $GLOBALS['SEME_TERMINATOR'] = function () {
            throw new RuntimeException('terminated');
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terminated');

        ob_start();
        try {
            $this->dispatch('/admin/broken');
        } finally {
            ob_end_clean();
        }
    }

    // ---- request URI parsing --------------------------------------------

    public function testRequestUriMethodIsSupported(): void
    {
        $this->configure(['method' => 'REQUEST_URI']);

        $this->assertSame('blog-index', $this->dispatch('/blog'));
    }

    public function testQueryStringDropsTheSegmentAndFallsToDefault(): void
    {
        // CURRENT BEHAVIOUR (bugs-notes.md #4): the segment containing '?'
        // is removed entirely, so "/blog?x=1" loses "blog" and runs the
        // default controller instead of Blog.
        $this->configure(['method' => 'REQUEST_URI']);

        $this->assertSame('home-index', $this->dispatch('/blog?x=1'));
    }

    public function testQueryStringOnLaterSegmentKeepsEarlierOnes(): void
    {
        $this->configure(['method' => 'REQUEST_URI']);

        // "show?x=1" is dropped, so Blog is called with its default method.
        $this->assertSame('blog-index', $this->dispatch('/blog/show?x=1'));
    }

    public function testDoubleSlashesAreNotCollapsed(): void
    {
        // CURRENT BEHAVIOUR (bugs-notes.md #3): strtr($p, '//', '/') is a
        // no-op, so "//blog" yields an empty first segment -> default.
        $this->assertSame('home-index', $this->dispatch('//blog'));
    }

    public function testArgvOverridesRequestPath(): void
    {
        // CLI support: extra argv entries become the path.
        $_SERVER['argv'] = ['index.php', 'blog', 'show', '9'];

        ob_start();
        (new SENE_Engine())->run();

        $this->assertSame("show:'9'", ob_get_clean());
    }

    // ---- route overrides (ovrRoutes) ------------------------------------

    public function testOverrideWithAnyPlaceholderRewritesPath(): void
    {
        $this->configure(['routes' => ['shop/(:any)' => 'blog/show/$1']]);

        $this->assertSame("show:'42'", $this->dispatch('/shop/42'));
        $this->assertSame("show:'abc'", $this->dispatch('/shop/abc'));
    }

    public function testOverrideWithNumPlaceholderOnlyMatchesDigits(): void
    {
        $this->configure(['routes' => ['item/(:num)' => 'blog/show/$1']]);

        $this->assertSame("show:'7'", $this->dispatch('/item/7'));
        $this->assertSame('notfound', $this->dispatch('/item/abc'));
    }

    public function testOverrideDoesNotMatchPartialPaths(): void
    {
        $this->configure(['routes' => ['shop/(:any)' => 'blog/show/$1']]);

        // anchored regex: no match for extra depth, so no controller "shop".
        $this->assertSame('notfound', $this->dispatch('/shop/a/b'));
    }

    public function testOverrideSlashesInRouteConfigAreTrimmed(): void
    {
        $this->configure(['routes' => ['/shop/(:any)/' => '/blog/show/$1/']]);

        $this->assertSame("show:'5'", $this->dispatch('/shop/5'));
    }

    public function testOverrideCanTargetNestedController(): void
    {
        $this->configure(['routes' => ['annual/(:num)' => 'reports/summary/year/$1']]);

        $this->assertSame('summary-year:2023', $this->dispatch('/annual/2023'));
    }

    public function testOverrideMatchingIsCaseInsensitive(): void
    {
        $this->configure(['routes' => ['shop/(:any)' => 'blog/show/$1']]);

        $this->assertSame("show:'x'", $this->dispatch('/SHOP/x'));
    }

    public function testLastMatchingOverrideWins(): void
    {
        // CURRENT BEHAVIOUR (bugs-notes.md #9): there is no break, so a
        // later route silently shadows an earlier one.
        $this->configure(['routes' => [
            'go/(:any)' => 'blog/show/$1',
            'go/(:num)' => 'reports/summary/year/$1',
        ]]);

        $this->assertSame('summary-year:3', $this->dispatch('/go/3'));
        $this->assertSame("show:'abc'", $this->dispatch('/go/abc'));
    }

    // ---- ovrRoutes directly ---------------------------------------------

    public function testOvrRoutesReturnsInputWhenNoRouteMatches(): void
    {
        $this->configure(['routes' => ['shop/(:any)' => 'blog/show/$1']]);
        $input = ['', 'blog', 'index'];

        $this->assertSame($input, $this->callOvrRoutes($input));
    }

    public function testOvrRoutesReturnsInputWhenNoRoutesConfigured(): void
    {
        $input = ['', 'blog'];

        $this->assertSame($input, $this->callOvrRoutes($input));
    }

    public function testOvrRoutesReturnsLeadingEmptySegmentOnMatch(): void
    {
        $this->configure(['routes' => ['shop/(:any)' => 'blog/show/$1']]);

        $this->assertSame(['', 'blog', 'show', '42'], $this->callOvrRoutes(['', 'shop', '42']));
    }

    public function testOvrRoutesLowercasesTheTarget(): void
    {
        // CURRENT BEHAVIOUR (bugs-notes.md #9): both key and target are
        // lowercased, and so is the captured value, which breaks
        // case-sensitive ids and controller file names.
        $this->configure(['routes' => ['shop/(:any)' => 'Blog/Show/$1']]);

        $this->assertSame(['', 'blog', 'show', 'abc'], $this->callOvrRoutes(['', 'shop', 'ABC']));
    }
}
