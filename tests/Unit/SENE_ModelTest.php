<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Model.php';

/** Concrete model: SENE_Model is abstract. */
class SENE_ModelStub extends SENE_Model
{
}

#[CoversClass(SENE_Model::class)]
final class SENE_ModelTest extends TestCase
{
    /**
     * Build a model without running its constructor, which would open a
     * real MySQL connection via SENE_MySQLi_Engine. $db and $config are
     * injected by hand instead.
     */
    private function makeModel(string $enckey = 'secret-key'): SENE_ModelStub
    {
        $model = (new ReflectionClass(SENE_ModelStub::class))->newInstanceWithoutConstructor();

        $config = (object) ['database' => (object) ['enckey' => $enckey]];
        (new ReflectionProperty(SENE_Model::class, 'config'))->setValue($model, $config);

        // Stand-in for SENE_MySQLi_Engine::esc(): wraps in double quotes,
        // maps null to NULL, and records what it was asked to escape.
        $model->db = new class {
            /** @var list<mixed> */
            public array $escaped = [];

            public function esc($var)
            {
                $this->escaped[] = $var;
                return $var === null ? 'NULL' : '"'.$var.'"';
            }
        };

        return $model;
    }

    public function testEncryptWrapsEscapedValueAndKeyInAesEncrypt(): void
    {
        $model = $this->makeModel('secret-key');

        $this->assertSame('AES_ENCRYPT("john@example.test","secret-key")', $model->__encrypt('john@example.test'));
    }

    public function testEncryptDelegatesEscapingToDb(): void
    {
        $model = $this->makeModel();

        $model->__encrypt('abc');

        $this->assertSame(['abc'], $model->db->escaped);
    }

    public function testEncryptPassesNullThroughDbEscaping(): void
    {
        $model = $this->makeModel('k');

        $this->assertSame('AES_ENCRYPT(NULL,"k")', $model->__encrypt(null));
    }

    public function testEncryptWithEmptyKeyStillProducesCommand(): void
    {
        // The shipped config has enckey = '' so this is the out-of-the-box state.
        $model = $this->makeModel('');

        $this->assertSame('AES_ENCRYPT("x","")', $model->__encrypt('x'));
    }

    public function testDecryptWrapsColumnAndKeyInAesDecrypt(): void
    {
        $model = $this->makeModel('secret-key');

        $this->assertSame('AES_DECRYPT(u.email,"secret-key")', $model->__decrypt('u.email'));
    }

    public function testDecryptDoesNotEscapeOrQuoteItsArgument(): void
    {
        // The argument is meant to be a column/expression, so it goes into
        // the SQL verbatim and the db is never consulted.
        $model = $this->makeModel();

        $this->assertSame('AES_DECRYPT(1 OR 1=1,"secret-key")', $model->__decrypt('1 OR 1=1'));
        $this->assertSame([], $model->db->escaped);
    }

    public function testEncryptedOutputRoundTripsIntoDecryptCommand(): void
    {
        $model = $this->makeModel('k');

        $this->assertSame(
            'AES_DECRYPT(AES_ENCRYPT("v","k"),"k")',
            $model->__decrypt($model->__encrypt('v'))
        );
    }

    /** @return array<string, array{string}> */
    public static function hostileKeyProvider(): array
    {
        return [
            'double quote' => ['a"b'],
            'quote and sql' => ['"); DROP TABLE users; --'],
        ];
    }

    #[DataProvider('hostileKeyProvider')]
    public function testEnckeyIsInterpolatedWithoutEscaping(string $key): void
    {
        // Documents current behaviour: the key is trusted config and is
        // pasted into the SQL as-is, so a quote inside it breaks the statement.
        $model = $this->makeModel($key);

        $this->assertSame('AES_ENCRYPT("x","'.$key.'")', $model->__encrypt('x'));
        $this->assertSame('AES_DECRYPT(c,"'.$key.'")', $model->__decrypt('c'));
    }

    public function testFieldDefaultsToEmptyArray(): void
    {
        $this->assertSame([], $this->makeModel()->field);
    }
}
