<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Input.php';

#[CoversClass(SENE_Input::class)]
final class SENE_InputTest extends TestCase
{
    private SENE_Input $input;

    protected function setUp(): void
    {
        $this->input = new SENE_Input();
        $_POST = $_GET = $_REQUEST = $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_POST = $_GET = $_REQUEST = $_FILES = [];
    }

    /** @return array<string, array{string}> */
    public static function methodProvider(): array
    {
        return [
            'post'    => ['post'],
            'get'     => ['get'],
            'request' => ['request'],
        ];
    }

    /** Superglobal each reader method is expected to consult. */
    private function fill(string $method, string $key, $value): void
    {
        match ($method) {
            'post'    => $_POST[$key] = $value,
            'get'     => $_GET[$key] = $value,
            'request' => $_REQUEST[$key] = $value,
        };
    }

    #[DataProvider('methodProvider')]
    public function testReturnsValueWhenKeyExists(string $method): void
    {
        $this->fill($method, 'msg', 'Welcome to Seme Framework');

        $this->assertSame('Welcome to Seme Framework', $this->input->$method('msg'));
    }

    #[DataProvider('methodProvider')]
    public function testDefaultsToNullWhenKeyMissing(string $method): void
    {
        // null, not 0: a missing parameter must not look like a real "0"
        // when it is passed on to the query builder.
        $this->assertNull($this->input->$method('missing'));
    }

    #[DataProvider('methodProvider')]
    public function testReturnsCustomDefaultWhenKeyMissing(string $method): void
    {
        $this->assertSame('fallback', $this->input->$method('missing', 'fallback'));
        $this->assertNull($this->input->$method('missing', null));
    }

    #[DataProvider('methodProvider')]
    public function testExistingValueWinsOverDefault(string $method): void
    {
        $this->fill($method, 'msg', 'real');

        $this->assertSame('real', $this->input->$method('msg', 'fallback'));
    }

    #[DataProvider('methodProvider')]
    public function testFalsyValuesAreReturnedNotDefaulted(string $method): void
    {
        // isset() is true for '', '0' and false, so they must not fall back.
        $this->fill($method, 'empty', '');
        $this->fill($method, 'zero', '0');
        $this->fill($method, 'no', false);

        $this->assertSame('', $this->input->$method('empty', 'fallback'));
        $this->assertSame('0', $this->input->$method('zero', 'fallback'));
        $this->assertFalse($this->input->$method('no', 'fallback'));
    }

    #[DataProvider('methodProvider')]
    public function testNullValueFallsBackToDefault(string $method): void
    {
        // isset() is false for null, so a stored null behaves like a missing key.
        $this->fill($method, 'nothing', null);

        $this->assertSame('fallback', $this->input->$method('nothing', 'fallback'));
    }

    #[DataProvider('methodProvider')]
    public function testArrayValuesAreReturnedUntouched(string $method): void
    {
        $this->fill($method, 'ids', ['1', '2']);

        $this->assertSame(['1', '2'], $this->input->$method('ids'));
    }

    public function testMethodsReadOnlyTheirOwnSuperglobal(): void
    {
        $_POST['k'] = 'from-post';
        $_GET['k'] = 'from-get';
        $_REQUEST['k'] = 'from-request';

        $this->assertSame('from-post', $this->input->post('k'));
        $this->assertSame('from-get', $this->input->get('k'));
        $this->assertSame('from-request', $this->input->request('k'));
    }

    public function testFileReturnsUploadEntryWhenPresent(): void
    {
        $upload = ['name' => 'a.png', 'error' => UPLOAD_ERR_OK, 'size' => 10];
        $_FILES['avatar'] = $upload;

        $this->assertSame($upload, $this->input->file('avatar'));
    }

    public function testFileReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->input->file('avatar'));
    }

    public function testDebugExposesAllFourSuperglobals(): void
    {
        $_POST['p'] = '1';
        $_GET['g'] = '2';
        $_REQUEST['r'] = '3';
        $_FILES['f'] = ['name' => 'x'];

        $this->assertSame([
            'post_param'    => ['p' => '1'],
            'get_param'     => ['g' => '2'],
            'request_param' => ['r' => '3'],
            'file_param'    => ['f' => ['name' => 'x']],
        ], $this->input->debug());
    }

    public function testDebugOnEmptyInputReturnsEmptyArrays(): void
    {
        $this->assertSame([
            'post_param'    => [],
            'get_param'     => [],
            'request_param' => [],
            'file_param'    => [],
        ], $this->input->debug());
    }
}
