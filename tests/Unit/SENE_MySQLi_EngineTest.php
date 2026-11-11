<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_MySQLi_Engine.php';

/** Result set stand-in for mysqli_result. */
final class StubMysqliResult
{
    /** @param list<array<string,mixed>> $rows */
    public function __construct(private array $rows)
    {
    }

    public function fetch_object()
    {
        $row = array_shift($this->rows);
        return $row === null ? null : (object) $row;
    }

    public function fetch_assoc()
    {
        return array_shift($this->rows);
    }

    public function fetch_array()
    {
        $row = array_shift($this->rows);
        return $row === null ? null : array_merge($row, array_values($row));
    }

    public function free(): void
    {
    }
}

/** mysqli stand-in: records SQL, never touches a server. */
final class StubMysqli
{
    /** @var list<string> */
    public array $queries = [];
    /** @var list<string> */
    public array $calls = [];
    /** @var list<array<string,mixed>> */
    public array $rows = [];
    public bool $fail = false;
    public int $insert_id = 0;
    public int $errno = 0;
    public string $error = '';

    public function query(string $sql)
    {
        $this->queries[] = $sql;
        if ($this->fail) {
            $this->errno = 1064;
            $this->error = 'You have an error in your SQL syntax';
            return false;
        }
        return stripos(ltrim($sql), 'SELECT') === 0 ? new StubMysqliResult($this->rows) : true;
    }

    public function real_escape_string($s): string
    {
        return addslashes((string) $s);
    }

    public function character_set_name(): string
    {
        return 'utf8mb4';
    }

    public function set_charset(string $cs): bool
    {
        $this->calls[] = 'set_charset:'.$cs;
        return true;
    }

    public function begin_transaction(): bool
    {
        $this->calls[] = 'begin_transaction';
        return true;
    }

    public function commit(): bool
    {
        $this->calls[] = 'commit';
        return true;
    }

    public function rollback(): bool
    {
        $this->calls[] = 'rollback';
        return true;
    }

    public function autocommit($v): bool
    {
        $this->calls[] = 'autocommit:'.(int) $v;
        return true;
    }

    public function savepoint($name): bool
    {
        $this->calls[] = 'savepoint:'.$name;
        return true;
    }
}

/**
 * Query builder tests for SENE_MySQLi_Engine. No database is involved:
 * the engine is built without its constructor and handed a StubMysqli, and
 * assertions are made on the SQL the builder produced.
 *
 * SQL is compared with whitespace collapsed because the builder emits
 * irregular spacing that carries no meaning.
 *
 * Tests marked "CURRENT BEHAVIOUR" pin behaviour that is arguably a bug;
 * update them together with the fix.
 */
#[CoversClass(SENE_MySQLi_Engine::class)]
final class SENE_MySQLi_EngineTest extends TestCase
{
    private SENE_MySQLi_Engine $db;
    private StubMysqli $mysqli;

    protected function setUp(): void
    {
        $this->mysqli = new StubMysqli();

        $db = (new ReflectionClass(SENE_MySQLi_Engine::class))->newInstanceWithoutConstructor();
        $db->__mysqli = $this->mysqli;
        $db->stderr = false;   // keep SQL logging off stderr
        $db->is_debug = 0;     // no E_USER_NOTICE on failed exec()
        $db->flushQuery();
        $db->union_flush();
        $this->db = $db;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['SEME_TERMINATOR']);
    }

    /**
     * Assert that $fn is rejected by the identifier checks and that no SQL
     * was built. The terminator is a no-op so we also prove the engine does
     * not carry on after seme_fatal() returns.
     */
    private function assertRejected(callable $fn, string $messagePart): void
    {
        $GLOBALS['SEME_TERMINATOR'] = function () {
        };

        ob_start();
        try {
            $fn();
            $this->fail('Expected the identifier to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        } finally {
            ob_end_clean();
        }
        $this->assertSame([], $this->mysqli->queries, 'No SQL may be sent for a rejected identifier.');
    }

    /** Last SQL sent to the stub, whitespace collapsed. */
    private function sql(): string
    {
        $last = end($this->mysqli->queries);
        $this->assertIsString($last, 'No SQL was executed.');

        return trim((string) preg_replace('/\s+/', ' ', $last));
    }

    /** SELECT produced by get() for the current builder state. */
    private function selectSql(string $table = 'users'): string
    {
        $this->db->from($table)->get();

        return $this->sql();
    }

    /** Run get() and return only the WHERE part of the SQL. */
    private function whereOf(callable $build): string
    {
        $this->db->from('t');
        $build($this->db);
        $this->db->get();
        $sql = $this->sql();
        $pos = strpos($sql, ' WHERE ');
        $this->assertNotFalse($pos, 'No WHERE clause in: '.$sql);

        return substr($sql, $pos + 7);
    }

    // ---- esc ------------------------------------------------------------

    public function testEscQuotesAndEscapesStrings(): void
    {
        $this->assertSame('"abc"', $this->db->esc('abc'));
        $this->assertSame('"say \\"hi\\""', $this->db->esc('say "hi"'));
        $this->assertSame('"5"', $this->db->esc(5));
    }

    public function testEscMapsNullToSqlNull(): void
    {
        $this->assertSame('NULL', $this->db->esc(null));
    }

    #[DataProvider('nullLookalikeProvider')]
    public function testEscTurnsTheStringNullIntoSqlNull(string $value): void
    {
        // CURRENT BEHAVIOUR: a user literally named "null" is stored as NULL.
        $this->assertSame('NULL', $this->db->esc($value));
    }

    /** @return array<string, array{string}> */
    public static function nullLookalikeProvider(): array
    {
        return ['lower' => ['null'], 'upper' => ['NULL'], 'mixed' => ['NuLl']];
    }

    public function testEscReturnsNothingForArrays(): void
    {
        // CURRENT BEHAVIOUR: arrays fall through the empty branch.
        $this->assertNull($this->db->esc(['a']));
    }

    // ---- select ---------------------------------------------------------

    public function testNoSelectDefaultsToStar(): void
    {
        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
    }

    public function testSelectSingleColumnIsBackticked(): void
    {
        $this->db->select('id');

        $this->assertSame('SELECT `id` FROM `users`', $this->selectSql());
    }

    public function testSelectArrayAndRepeatedCallsAccumulate(): void
    {
        $this->db->select(['id', 'name'])->select('email');

        $this->assertSame('SELECT `id`, `name`, `email` FROM `users`', $this->selectSql());
    }

    public function testSelectStarIsNotBackticked(): void
    {
        $this->db->select('*');

        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
    }

    public function testSelectAsBuildsAliases(): void
    {
        $this->db->select_as('id', 'user_id')->select_as(['COUNT(*)' => 'total']);

        $this->assertSame("SELECT id AS 'user_id', COUNT(*) 'total' FROM `users`", $this->selectSql());
    }

    public function testSelectWithFullSqlStringRunsImmediately(): void
    {
        $this->mysqli->rows = [['n' => 1]];

        $rows = $this->db->select('SELECT 1 AS n FROM dual');

        $this->assertSame('SELECT 1 AS n FROM dual', $this->sql());
        $this->assertEquals([(object) ['n' => 1]], $rows);
    }

    // ---- from / alias ---------------------------------------------------

    public function testFromWithAliasEmitsAliasAfterTable(): void
    {
        $this->db->from('users', 'u')->get();

        $this->assertSame('SELECT * FROM `users` u', $this->sql());
    }

    public function testFromLowercasesTheAlias(): void
    {
        $this->db->from('users', 'U')->get();

        $this->assertSame('SELECT * FROM `users` u', $this->sql());
    }

    public function testSetTableAliasRegistersAliasForCurrentTable(): void
    {
        $this->db->from('users')->setTableAlias('usr')->get();

        $this->assertSame('SELECT * FROM `users` usr', $this->sql());
    }

    public function testFromWithEmptyTableIsFatal(): void
    {
        $GLOBALS['SEME_TERMINATOR'] = function () {
            throw new RuntimeException('terminated');
        };

        $this->expectException(RuntimeException::class);
        ob_start();
        try {
            $this->db->from('');
        } finally {
            ob_end_clean();
        }
    }

    // ---- where: string form ---------------------------------------------

    public function testWhereEqualsEscapesTheValue(): void
    {
        $this->assertSame('`id` = "5"', $this->whereOf(fn ($db) => $db->where('id', 5)));
    }

    public function testWhereEscapesQuotesInValues(): void
    {
        $this->assertSame(
            '`n` = "x\\" OR \\"1\\"=\\"1"',
            $this->whereOf(fn ($db) => $db->where('n', 'x" OR "1"="1'))
        );
    }

    public function testWhereBackticksEachPartOfADottedColumn(): void
    {
        $this->assertSame('`u`.`id` = "1"', $this->whereOf(fn ($db) => $db->where('u.id', 1)));
    }

    public function testChainedWhereJoinsWithAnd(): void
    {
        $this->assertSame(
            '`a` = "1" AND `b` = "2"',
            $this->whereOf(fn ($db) => $db->where('a', 1)->where('b', 2))
        );
    }

    public function testOperandControlsHowTheNextConditionIsJoined(): void
    {
        $this->assertSame(
            '`a` = "1" OR `b` = "2"',
            $this->whereOf(fn ($db) => $db->where('a', 1, 'or')->where('b', 2))
        );
    }

    /** @return array<string, array{string, string}> */
    public static function comparisonProvider(): array
    {
        return [
            'greater or equal' => ['>=',        '`c` >= "x"'],
            'less or equal'    => ['<=',        '`c` <= "x"'],
            'greater'          => ['>',         '`c` > "x"'],
            'less'             => ['<',         '`c` < "x"'],
            'not equal (!=)'   => ['!=',        '`c` <> "x"'],
            'not equal (<>)'   => ['<>',        '`c` <> "x"'],
            'like'             => ['like',      '`c` LIKE "x"'],
            'like prefix'      => ['like%',     '`c` LIKE "x%"'],
            'like suffix'      => ['%like',     '`c` LIKE "%x"'],
            'like contains'    => ['%like%',    '`c` LIKE "%x%"'],
            'like contains 2'  => ['like%%',    '`c` LIKE "%x%"'],
            'not like'         => ['notlike',   '`c` NOT LIKE "x"'],
            'not like prefix'  => ['notlike%',  '`c` NOT LIKE "x%"'],
            'not like suffix'  => ['%notlike',  '`c` NOT LIKE "%x"'],
            'not like contains' => ['%notlike%', '`c` NOT LIKE "%x%"'],
            'unknown falls back to =' => ['bogus', '`c` = "x"'],
        ];
    }

    #[DataProvider('comparisonProvider')]
    public function testWhereComparisonOperators(string $comp, string $expected): void
    {
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->where('c', 'x', 'AND', $comp)));
    }

    public function testWhereComparisonIsCaseInsensitive(): void
    {
        $this->assertSame('`c` LIKE "x%"', $this->whereOf(fn ($db) => $db->where('c', 'x', 'AND', 'LIKE%')));
    }

    #[DataProvider('isNullProvider')]
    public function testWhereRecognisesIsNullKeywords(string $value, string $expected): void
    {
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->where('c', $value)));
    }

    /** @return array<string, array{string, string}> */
    public static function isNullProvider(): array
    {
        return [
            'is null'         => ['is null', '`c` IS NULL'],
            'is not null'     => ['IS NOT NULL', '`c` IS NOT NULL'],
            'padded'          => ['  is null ', '`c` IS NULL'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function mixedCaseNullProvider(): array
    {
        return [
            'mixed null'     => ['Is nUlL', 'IS NULL'],
            'mixed not null' => ['Is not nUlL', 'IS NOT NULL'],
            'lower null'     => ['is null', 'IS NULL'],
            'lower not null' => ['is not null', 'IS NOT NULL'],
        ];
    }

    #[DataProvider('mixedCaseNullProvider')]
    public function testWhereIsNullKeywordsAreCaseInsensitive(string $value, string $keyword): void
    {
        $this->assertSame('`name` '.$keyword, $this->whereOf(fn ($db) => $db->where('name', $value)));
    }

    #[DataProvider('mixedCaseNullProvider')]
    public function testWhereAsIsNullKeywordsAreCaseInsensitive(string $value, string $keyword): void
    {
        $this->assertSame('name '.$keyword, $this->whereOf(fn ($db) => $db->where_as('name', $value)));
    }

    /** @return array<string, array{string, string}> */
    public static function whereAsComparisonProvider(): array
    {
        return [
            'default'          => ['',          'name = b.x'],
            'equal'            => ['=',         'name = b.x'],
            'greater'          => ['>',         'name > b.x'],
            'greater or equal' => ['>=',        'name >= b.x'],
            'less'             => ['<',         'name < b.x'],
            'less or equal'    => ['<=',        'name <= b.x'],
            'not equal (!=)'   => ['!=',        'name <> b.x'],
            'not equal (<>)'   => ['<>',        'name <> b.x'],
            'like'             => ['like',      'name LIKE b.x'],
            'not like'         => ['notlike',   'name NOT LIKE b.x'],
        ];
    }

    #[DataProvider('whereAsComparisonProvider')]
    public function testWhereAsComparisonOperators(string $comp, string $expected): void
    {
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->where_as('name', 'b.x', 'AND', $comp)));
    }

    /** @return array<string, array{string, string}> */
    public static function whereAsLikeWildcardProvider(): array
    {
        return [
            'like prefix'       => ['like%',      "name LIKE 'Alice%'"],
            'like suffix'       => ['%like',      "name LIKE '%Alice'"],
            'like contains'     => ['%like%',     "name LIKE '%Alice%'"],
            'like contains 2'   => ['like%%',     "name LIKE '%Alice%'"],
            'not like prefix'   => ['notlike%',   "name NOT LIKE 'Alice%'"],
            'not like suffix'   => ['%notlike',   "name NOT LIKE '%Alice'"],
            'not like contains' => ['%notlike%',  "name NOT LIKE '%Alice%'"],
            'not like contains 2' => ['notlike%%', "name NOT LIKE '%Alice%'"],
        ];
    }

    #[DataProvider('whereAsLikeWildcardProvider')]
    public function testWhereAsLikeWildcards(string $comp, string $expected): void
    {
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->where_as('name', 'Alice', 'AND', $comp)));
    }

    public function testWhereAsJoinsWithGivenOperand(): void
    {
        $this->assertSame(
            'name = b.x OR z = b.z',
            $this->whereOf(fn ($db) => $db->where_as('name', 'b.x', 'OR')->where_as('z', 'b.z'))
        );
    }

    public function testWhereBracketsGroupSeveralConditions(): void
    {
        $this->assertSame(
            '( `name` = "Alice" AND `age` = "32" )',
            $this->whereOf(fn ($db) => $db->where('name', 'Alice', 'AND', '=', 1, 0)->where('age', 32, 'AND', '=', 0, 1))
        );
    }

    public function testWhereAsBracketsGroupSeveralConditions(): void
    {
        $this->assertSame(
            '( name = b.x AND age = 32 )',
            $this->whereOf(fn ($db) => $db->where_as('name', 'b.x', 'AND', '=', 1, 0)->where_as('age', 32, 'AND', '=', 0, 1))
        );
    }

    public function testFlushQueryAfterBuildingClearsEverything(): void
    {
        $this->db->select('*')->from('tabel')->where('name', 'ashley');
        $this->db->flushQuery();

        $this->assertSame('', $this->db->in_select);
        $this->assertSame('', $this->db->in_where);
        $this->assertSame('', $this->db->in_order);
        $this->assertSame('', $this->db->in_group);
        $this->assertSame(0, $this->db->pagesize);
        $this->assertSame(0, $this->db->page);
        $this->assertSame(0, $this->db->is_limit);
        $this->assertSame(0, $this->db->limit_a);
        $this->assertSame(0, $this->db->limit_b);
        $this->assertSame([], $this->db->as_from);
    }

    public function testWhereBracketsWrapACondition(): void
    {
        $this->assertSame(
            '( `a` = "1" ) OR ( `b` = "2" )',
            $this->whereOf(fn ($db) => $db->where('a', 1, 'OR', '=', 1, 1)->where('b', 2, 'AND', '=', 1, 1))
        );
    }

    #[DataProvider('zeroLikeValueProvider')]
    public function testWhereKeepsZeroLikeValues(mixed $value, string $expected): void
    {
        // 0, '0', 0.0 and false are real values, not "no value".
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->where('active', $value)));
    }

    /** @return array<string, array{mixed, string}> */
    public static function zeroLikeValueProvider(): array
    {
        return [
            'int zero'    => [0, '`active` = "0"'],
            'string zero' => ['0', '`active` = "0"'],
            'float zero'  => [0.0, '`active` = "0"'],
            'false'       => [false, '`active` = "0"'],
            'true'        => [true, '`active` = "1"'],
        ];
    }

    #[DataProvider('noValueProvider')]
    public function testWhereWithoutAValueAddsNoCondition(mixed $value): void
    {
        // null and '' (the argument's default) mean "no value supplied".
        $this->db->from('users')->where('active', $value)->get();

        $this->assertSame('SELECT * FROM `users`', $this->sql());
    }

    /** @return array<string, array{mixed}> */
    public static function noValueProvider(): array
    {
        return ['empty string' => [''], 'null' => [null]];
    }

    public function testWhereWithNoSecondArgumentAddsNoCondition(): void
    {
        $this->db->from('users')->where('active')->get();

        $this->assertSame('SELECT * FROM `users`', $this->sql());
    }

    #[DataProvider('zeroLikeValueProvider')]
    public function testWhereAsKeepsZeroLikeValues(mixed $value, string $unused): void
    {
        $expected = 'a.x = '.(int) $value;

        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->where_as('a.x', $value)));
    }

    public function testDeleteWithZeroWhereValueIsStillScoped(): void
    {
        $this->db->where('id', 0)->delete('users');

        $this->assertSame('DELETE FROM `users` WHERE `id` = "0"', trim((string) end($this->mysqli->queries)));
    }

    public function testUpdateWithZeroWhereValueIsStillScoped(): void
    {
        $this->db->where('id', '0')->update('users', ['active' => 1]);

        $this->assertSame('UPDATE `users` SET `active` = "1" WHERE `id` = "0"', trim((string) end($this->mysqli->queries)));
    }

    public function testWhereEscapesBackticksInColumnNames(): void
    {
        // A column name from user input can no longer close the backtick:
        // the embedded backticks are doubled, so it stays one identifier.
        $this->assertSame(
            '`a`` = 1 OR ``b` = "1"',
            $this->whereOf(fn ($db) => $db->where('a` = 1 OR `b', 1))
        );
    }

    public function testWhereArrayFormEscapesBackticksInColumnNames(): void
    {
        $this->assertSame(
            '`a`` OR ``b` = "1"',
            $this->whereOf(fn ($db) => $db->where(['a` OR `b' => 1], 'AND'))
        );
    }

    // ---- where: array form ----------------------------------------------

    public function testWhereArrayFormJoinsWithGivenOperand(): void
    {
        $this->assertSame(
            '`a` = "1" AND `b` = "2"',
            $this->whereOf(fn ($db) => $db->where(['a' => 1, 'b' => 2], 'AND'))
        );
    }

    public function testWhereArrayFormAcceptsComparisonAsThirdArgument(): void
    {
        // In array form the 2nd argument is the joiner and the 3rd the comparison.
        $this->assertSame(
            '`a` LIKE "x%" OR `b` LIKE "y%"',
            $this->whereOf(fn ($db) => $db->where(['a' => 'x', 'b' => 'y'], 'OR', 'like%'))
        );
    }

    #[DataProvider('missingJoinerProvider')]
    public function testWhereArrayFormDefaultsTheJoinerToAnd(mixed $joiner): void
    {
        $this->assertSame(
            '`a` = "1" AND `b` = "2"',
            $this->whereOf(fn ($db) => $db->where(['a' => 1, 'b' => 2], $joiner))
        );
    }

    /** @return array<string, array{mixed}> */
    public static function missingJoinerProvider(): array
    {
        return ['empty string' => [''], 'null' => [null]];
    }

    public function testWhereArrayFormWithNoSecondArgumentUsesAnd(): void
    {
        $this->assertSame(
            '`a` = "1" AND `b` = "2"',
            $this->whereOf(fn ($db) => $db->where(['a' => 1, 'b' => 2]))
        );
    }

    public function testWhereArrayFormDefaultJoinerAlsoLinksTheNextCall(): void
    {
        $this->assertSame(
            '`a` = "1" AND `b` = "2" AND `c` = "3"',
            $this->whereOf(fn ($db) => $db->where(['a' => 1, 'b' => 2])->where('c', 3))
        );
    }

    public function testWhereArrayFormDefaultJoinerKeepsTheComparison(): void
    {
        // Joiner omitted, comparison given: where($arr, '', 'like%').
        $this->assertSame(
            '`a` LIKE "x%" AND `b` LIKE "y%"',
            $this->whereOf(fn ($db) => $db->where(['a' => 'x', 'b' => 'y'], '', 'like%'))
        );
    }

    public function testWhereAsArrayFormDefaultsTheJoinerToAnd(): void
    {
        $this->assertSame(
            'a = b.x AND c = d.y',
            $this->whereOf(fn ($db) => $db->where_as(['a' => 'b.x', 'c' => 'd.y']))
        );
    }

    public function testWhereAsArrayFormHonoursAnExplicitJoiner(): void
    {
        $this->assertSame(
            'a = b.x OR c = d.y',
            $this->whereOf(fn ($db) => $db->where_as(['a' => 'b.x', 'c' => 'd.y'], 'or'))
        );
    }

    public function testJoinerIsCaseInsensitive(): void
    {
        $this->assertSame(
            '`a` = "1" OR `b` = "2"',
            $this->whereOf(fn ($db) => $db->where(['a' => 1, 'b' => 2], 'oR'))
        );
    }

    #[DataProvider('hostileJoinerProvider')]
    public function testJoinerRejectsAnythingButAndOr(string $joiner): void
    {
        $this->assertRejected(fn () => $this->db->where('a', 1, $joiner), 'invalid condition joiner');
        $this->assertRejected(fn () => $this->db->where(['a' => 1], $joiner), 'invalid condition joiner');
        $this->assertRejected(fn () => $this->db->where_as(['a' => 'b.c'], $joiner), 'invalid condition joiner');
        $this->assertRejected(fn () => $this->db->where_null('a', $joiner), 'invalid condition joiner');
        $this->assertRejected(fn () => $this->db->where_not_null('a', $joiner), 'invalid condition joiner');
        $this->assertRejected(fn () => $this->db->where_in('a', [1], 0, $joiner), 'invalid condition joiner');
    }

    /** @return array<string, array{string}> */
    public static function hostileJoinerProvider(): array
    {
        return [
            'injection'      => ['AND 1=1 OR'],
            'xor'            => ['XOR'],
            'symbol'         => ['&&'],
            'statement'      => ['OR 1=1; DROP TABLE t; --'],
        ];
    }

    // ---- where helpers --------------------------------------------------

    public function testWhereNullAndNotNull(): void
    {
        $this->assertSame(
            'a IS NULL AND b IS NOT NULL',
            $this->whereOf(fn ($db) => $db->where_null('a')->where_not_null('b'))
        );
    }

    public function testWhereInEscapesEachValue(): void
    {
        $this->assertSame(
            'id IN ("1", "2", "x\\"y")',
            $this->whereOf(fn ($db) => $db->where_in('id', [1, 2, 'x"y']))
        );
    }

    public function testWhereInNotAndCustomJoiner(): void
    {
        $this->assertSame(
            'id NOT IN ("1") OR `b` = "2"',
            $this->whereOf(fn ($db) => $db->where_in('id', [1], 1, 'OR')->where('b', 2))
        );
    }

    public function testWhereInAcceptsRawSubqueryString(): void
    {
        $this->assertSame(
            'id IN (SELECT user_id FROM orders WHERE total > 100)',
            $this->whereOf(fn ($db) => $db->where_in('id', ' SELECT user_id FROM orders WHERE total > 100 '))
        );
        $this->assertSame(
            'id NOT IN (SELECT 1)',
            $this->whereOf(fn ($db) => $db->where_in('id', 'SELECT 1', 1))
        );
    }

    public function testWhereInAcceptsFunctionWrappedColumn(): void
    {
        $this->assertSame(
            'LOWER(a.status) IN ("dikirim", "diterima")',
            $this->whereOf(fn ($db) => $db->where_in('LOWER(a.status)', ['dikirim', 'diterima']))
        );
        $this->assertSame(
            'UPPER(LOWER(a.status)) IN ("x")',
            $this->whereOf(fn ($db) => $db->where_in('UPPER(LOWER(a.status))', ['x']))
        );
    }

    public function testWhereInRejectsUnsafeFunctionColumns(): void
    {
        foreach (['LOWER(a.status) OR 1=1', 'LOWER(a)) OR (1', 'LOWER(a.b', 'LOWER(a, b)', 'LOWER(a);--'] as $col) {
            $this->assertRejected(fn () => $this->db->where_in($col, [1]), 'where_in: invalid column');
        }
    }

    public function testWhereInWithEmptyListEmitsInvalidSql(): void
    {
        // CURRENT BEHAVIOUR: "IN ()" is a MySQL syntax error.
        $this->assertSame('id IN ()', $this->whereOf(fn ($db) => $db->where_in('id', [])));
    }

    public function testBetweenWrapsRangeInBrackets(): void
    {
        $this->assertSame(
            '( age BETWEEN 18 AND 65)',
            $this->whereOf(fn ($db) => $db->between('age', 18, 65))
        );
        $this->mysqli->queries = [];
        $this->assertSame(
            '( age NOT BETWEEN 1 AND 2)',
            $this->whereOf(fn ($db) => $db->between('age', 1, 2, 1))
        );
    }

    public function testBetweenPassesColumnAndBoundsThroughRaw(): void
    {
        $this->assertSame(
            '( (bdj.sdate) BETWEEN (\'2026-11-01 00:00:00\') AND (\'2026-11-30 23:59:59\'))',
            $this->whereOf(fn ($db) => $db->between('(bdj.sdate)', "('2026-11-01 00:00:00')", "('2026-11-30 23:59:59')"))
        );
        $this->assertSame(
            '( DATE(a.sdate) BETWEEN \'2026-01-01\' AND CURDATE())',
            $this->whereOf(fn ($db) => $db->between('DATE(a.sdate)', "'2026-01-01'", 'CURDATE()'))
        );
    }

    public function testWhereAsPassesValueThroughRaw(): void
    {
        // where_as() is for column-to-column comparisons: neither side is
        // quoted or backticked.
        $this->assertSame('a.x = b.y', $this->whereOf(fn ($db) => $db->where_as('a.x', 'b.y')));
    }

    /** @return array<string, array{string}> */
    public static function lookalikeOperandProvider(): array
    {
        // Each ends in letters that rtrim($s, "AND ") / rtrim($s, "OR ") would eat.
        return [
            'ID'      => ['b.ID'],
            'AND'     => ['b.BRAND'],
            'COND'    => ['b.COND'],
            'COLOR'   => ['b.COLOR'],
            'NOR'     => ['b.NOR'],
            'A'       => ['b.A'],
        ];
    }

    #[DataProvider('lookalikeOperandProvider')]
    public function testTrailingJoinerTrimKeepsTheLastRawOperandIntact(string $operand): void
    {
        $this->assertSame('a.x = '.$operand, $this->whereOf(fn ($db) => $db->where_as('a.x', $operand)));
    }

    #[DataProvider('lookalikeOperandProvider')]
    public function testTrailingOrJoinerTrimKeepsTheLastRawOperandIntact(string $operand): void
    {
        $this->assertSame(
            'a.x = '.$operand,
            $this->whereOf(fn ($db) => $db->where_as('a.x', $operand, 'OR'))
        );
    }

    public function testJoinersBetweenConditionsAreKeptAndOnlyTheLastIsRemoved(): void
    {
        $this->assertSame(
            'a.x = b.ID AND a.y = b.COLOR',
            $this->whereOf(fn ($db) => $db->where_as('a.x', 'b.ID')->where_as('a.y', 'b.COLOR'))
        );
    }

    public function testTrailingJoinerIsRemovedAfterABracketedCondition(): void
    {
        $this->assertSame(
            '( age BETWEEN 1 AND 2)',
            $this->whereOf(fn ($db) => $db->between('age', 1, 2))
        );
    }

    public function testLowercaseJoinerFromWhereInIsRemoved(): void
    {
        $this->assertSame(
            'id IN ("1")',
            $this->whereOf(fn ($db) => $db->where_in('id', [1], 0, 'or'))
        );
    }

    public function testGetFirstKeepsTheLastRawOperand(): void
    {
        $this->db->from('t')->where_as('a.parent', 'b.ID')->get_first();

        $this->assertSame('SELECT * FROM `t` WHERE a.parent = b.ID LIMIT 0, 1', $this->sql());
    }

    public function testGetFirstDoesNotTrimLettersOffTheEndOfTheSql(): void
    {
        // get_first() used to rtrim() the whole statement with " AND" and
        // " OR" masks. A raw operand at the very end showed the damage.
        $this->db->from('t')->where_as('a.x', 'b.REGION')->get_first();

        $this->assertStringContainsString('WHERE a.x = b.REGION LIMIT 0, 1', $this->sql());
    }

    public function testUpdateAndDeleteKeepTheLastRawOperand(): void
    {
        $this->db->where_as('a.parent', 'b.ID')->update('t', ['n' => 1]);
        $this->assertSame('UPDATE `t` SET `n` = "1" WHERE a.parent = b.ID', trim((string) end($this->mysqli->queries)));

        $this->db->where_as('a.parent', 'b.COLOR', 'OR')->delete('t');
        $this->assertSame('DELETE FROM `t` WHERE a.parent = b.COLOR', trim((string) end($this->mysqli->queries)));
    }

    public function testUnionKeepsRawOperandsThatLookLikeTheKeyword(): void
    {
        // union_get() used to chop() the trailing " UNION " as a character
        // mask, which also removed the "ION" of "b.REGION".
        $this->db->from('a')->union_create();
        $this->db->from('b')->where_as('x', 'b.REGION')->union_create();

        $this->db->union_get();

        $this->assertSame(
            'SELECT * FROM ( SELECT * FROM `a` UNION SELECT * FROM `b` WHERE x = b.REGION ) AS u1',
            $this->sql()
        );
    }

    // ---- order / group --------------------------------------------------

    public function testOrderByDefaultsToAscAndUppercasesDirection(): void
    {
        $this->db->order_by('name');
        $this->assertSame('SELECT * FROM `users` ORDER BY name ASC', $this->selectSql());

        $this->mysqli->queries = [];
        $this->db->order_by('name', 'desc');
        $this->assertSame('SELECT * FROM `users` ORDER BY name DESC', $this->selectSql());
    }

    public function testOrderByArrayAndChaining(): void
    {
        $this->db->order_by(['a' => 'asc', 'b' => 'desc'])->order_by('c');

        $this->assertSame('SELECT * FROM `users` ORDER BY a ASC, b DESC, c ASC', $this->selectSql());
    }

    public function testOrderByAcceptsQualifiedAndBackticksColumns(): void
    {
        $this->db->order_by('u.name', 'desc')->order_by('`created_at`');

        $this->assertSame('SELECT * FROM `users` ORDER BY u.name DESC, `created_at` ASC', $this->selectSql());
    }

    public function testOrderByAcceptsRawExpression(): void
    {
        $expr = "CAST(COALESCE(a.edate,CONCAT(DATE(a.sdate),' 23:59:59')) AS DATETIME)";
        $this->db->order_by($expr, 'desc')->order_by('id');

        $this->assertSame("SELECT * FROM `users` ORDER BY $expr DESC, id ASC", $this->selectSql());
    }

    #[DataProvider('hostileDirectionProvider')]
    public function testOrderByRejectsAnythingButAscOrDesc(string $direction): void
    {
        $this->assertRejected(fn () => $this->db->order_by('name', $direction), 'direction must be ASC or DESC');
    }

    /** @return array<string, array{string}> */
    public static function hostileDirectionProvider(): array
    {
        return [
            'extra column' => ['ASC, (SELECT 1)'],
            'word'         => ['sideways'],
        ];
    }

    public function testOrderByArrayFormValidatesDirections(): void
    {
        $this->assertRejected(fn () => $this->db->order_by(['a' => 'sideways']), 'direction must be ASC or DESC');
    }

    public function testGroupByString(): void
    {
        $this->db->group_by('role');

        $this->assertSame('SELECT * FROM `users` GROUP BY role', $this->selectSql());
    }

    public function testGroupByArrayEmitsOneKeyword(): void
    {
        $this->db->group_by(['a', 'u.b']);

        $this->assertSame('SELECT * FROM `users` GROUP BY a, u.b', $this->selectSql());
    }

    public function testGroupByCallsAccumulateUnderOneKeyword(): void
    {
        $this->db->group_by('a')->group_by(['b', 'c'])->group_by('d');

        $this->assertSame('SELECT * FROM `users` GROUP BY a, b, c, d', $this->selectSql());
    }

    public function testGroupByIsAppliedToGetFirstAndClearedAfterwards(): void
    {
        $this->db->from('users')->group_by(['a', 'b'])->get_first();
        $this->assertSame('SELECT * FROM `users` GROUP BY a, b LIMIT 0, 1', $this->sql());

        $this->db->get();
        $this->assertSame('SELECT * FROM `users`', $this->sql());
    }

    public function testGroupByWithEmptyArrayAddsNothing(): void
    {
        $this->db->group_by([]);

        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
    }

    public function testRealAppQueryWithRawBetweenGroupAndOrder(): void
    {
        $as = 'bdj';
        $this->db->between("($as.sdate)", "('2026-11-01 00:00:00')", "('2026-11-30 23:59:59')")
            ->group_by(["DATE($as.sdate)", "$as.id"])
            ->order_by("CAST(COALESCE($as.edate,CONCAT(DATE($as.sdate),' 23:59:59')) AS DATETIME)", "desc")
            ->limit(0, 10);

        $this->assertSame(
            "SELECT * FROM `users` WHERE ( ($as.sdate) BETWEEN ('2026-11-01 00:00:00') AND ('2026-11-30 23:59:59'))"
            ." GROUP BY DATE($as.sdate), $as.id"
            ." ORDER BY CAST(COALESCE($as.edate,CONCAT(DATE($as.sdate),' 23:59:59')) AS DATETIME) DESC LIMIT 0, 10",
            $this->selectSql()
        );
    }

    public function testGroupByAcceptsRawExpression(): void
    {
        $this->db->group_by("DATE(a.sdate)")->group_by(["CAST(a.x AS CHAR)", 'b']);

        $this->assertSame('SELECT * FROM `users` GROUP BY DATE(a.sdate), CAST(a.x AS CHAR), b', $this->selectSql());
    }

    public function testClauseOrderIsWhereGroupOrderLimit(): void
    {
        $this->db->where('a', 1)->group_by('g')->order_by('o')->limit(5);

        $this->assertSame(
            'SELECT * FROM `users` WHERE `a` = "1" GROUP BY g ORDER BY o ASC LIMIT 0, 5',
            $this->selectSql()
        );
    }

    // ---- limit / paging -------------------------------------------------

    /** @return array<string, array{callable, string}> */
    public static function limitProvider(): array
    {
        return [
            'limit(n) is a row count'  => [fn ($db) => $db->limit(10), ' LIMIT 0, 10'],
            'limit(offset, count)'     => [fn ($db) => $db->limit(5, 10), ' LIMIT 5, 10'],
            'limit(0, count)'          => [fn ($db) => $db->limit(0, 10), ' LIMIT 0, 10'],
            'page(1, size)'            => [fn ($db) => $db->page(1, 10), ' LIMIT 0, 10'],
            'page(3, size)'            => [fn ($db) => $db->page(3, 10), ' LIMIT 20, 10'],
            'page(n) alone is a size'  => [fn ($db) => $db->page(7), ' LIMIT 0, 7'],
            'pagesize(n)'              => [fn ($db) => $db->pagesize(5), ' LIMIT 5'],
            'limitpage first page'     => [fn ($db) => $db->limitpage(1, 10), ' LIMIT 10'],
            'no limit by default'      => [fn ($db) => $db, ''],
        ];
    }

    #[DataProvider('limitProvider')]
    public function testLimitVariants(callable $apply, string $suffix): void
    {
        $apply($this->db);

        $this->assertSame('SELECT * FROM `users`'.$suffix, $this->selectSql());
    }

    /** @return array<string, array{int, int, string}> */
    public static function limitPageProvider(): array
    {
        return [
            'page 1'                 => [1, 10, ' LIMIT 10'],
            'page 2'                 => [2, 10, ' LIMIT 10, 10'],
            'page 3'                 => [3, 10, ' LIMIT 20, 10'],
            'page 5 of 25'           => [5, 25, ' LIMIT 100, 25'],
            'page 0 is the first'    => [0, 10, ' LIMIT 10'],
            'negative is the first'  => [-4, 10, ' LIMIT 10'],
            'size 0 means no limit'  => [3, 0, ''],
            'negative size no limit' => [3, -5, ''],
        ];
    }

    #[DataProvider('limitPageProvider')]
    public function testLimitPageOffsetIsPreviousPagesTimesPageSize(int $page, int $size, string $suffix): void
    {
        $this->db->limitpage($page, $size);

        $this->assertSame('SELECT * FROM `users`'.$suffix, $this->selectSql());
    }

    public function testLimitPageDefaultsToTenRowsPerPage(): void
    {
        $this->db->limitpage(2);

        $this->assertSame('SELECT * FROM `users` LIMIT 10, 10', $this->selectSql());
    }

    #[DataProvider('pageAgreementProvider')]
    public function testLimitPageAgreesWithPageForTheSameArguments(int $page, int $size): void
    {
        // limitpage() used to use the page number as the offset; page() was right.
        $this->db->limitpage($page, $size);
        $viaLimitPage = $this->selectSql();

        $this->mysqli->queries = [];
        $this->db->page($page, $size);
        $viaPage = $this->selectSql();

        $this->assertSame($viaPage, $viaLimitPage);
    }

    /** @return array<string, array{int, int}> */
    public static function pageAgreementProvider(): array
    {
        return ['2/10' => [2, 10], '3/10' => [3, 10], '4/7' => [4, 7], '9/50' => [9, 50]];
    }

    public function testLimitPageCastsItsArgumentsToIntegers(): void
    {
        // Both values are written into the SQL; non-numeric tails are dropped.
        $this->db->limitpage('3; DROP TABLE users', '10 OR 1=1');

        $this->assertSame('SELECT * FROM `users` LIMIT 20, 10', $this->selectSql());
    }

    public function testLimitPageIsClearedAfterGet(): void
    {
        $this->db->limitpage(3, 10);
        $this->selectSql();

        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
    }

    public function testExplicitLimitWinsOverLimitPage(): void
    {
        $this->db->limitpage(3, 10)->limit(5);

        $this->assertSame('SELECT * FROM `users` LIMIT 0, 5', $this->selectSql());
    }

    public function testNoLimitClearsPreviousLimit(): void
    {
        $this->db->limit(10)->nolimit();

        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
    }

    public function testNoLimitAlsoClearsLimitPage(): void
    {
        $this->db->limitpage(3, 10)->nolimit();
        $this->assertSame('SELECT * FROM `users`', $this->selectSql());

        // A stale page number would turn this into "LIMIT 10, 5".
        $this->db->limitpage(3, 10)->nolimit()->pagesize(5);
        $this->assertSame('SELECT * FROM `users` LIMIT 5', $this->selectSql());
    }

    // ---- joins ----------------------------------------------------------

    public function testJoinBuildsOnClauseFromKeys(): void
    {
        $this->db->from('users', 'u')->join('orders', 'o', 'user_id', 'u', 'id')->get();

        $this->assertSame('SELECT * FROM `users` u LEFT JOIN `orders` o ON (o.`user_id` = u.`id`)', $this->sql());
    }

    public function testJoinMethodIsUppercased(): void
    {
        $this->db->from('users', 'u')->join('orders', 'o', 'user_id', 'u', 'id', ' inner ')->get();

        $this->assertStringContainsString(' INNER JOIN `orders` o ON ', $this->sql());
    }

    public function testJoinAsUsesRawOnClause(): void
    {
        $this->db->from('users', 'u')->join_as('`orders` o', 'o.uid = u.id AND o.paid = 1', 'right')->get();

        $this->assertSame('SELECT * FROM `users` u RIGHT JOIN `orders` o ON (o.uid = u.id AND o.paid = 1)', $this->sql());
    }

    public function testJoinCompositeChainsConditions(): void
    {
        $composites = [
            $this->db->composite_create('o.uid', '=', 'u.id'),
            $this->db->composite_create('o.status', '=', '"paid"', 'OR'),
        ];
        $this->db->from('users', 'u')->join_composite('orders', 'o', $composites, 'left')->get();

        $this->assertSame(
            'SELECT * FROM `users` u LEFT JOIN `orders` o ON o.uid = u.id AND o.status = "paid"',
            $this->sql()
        );
    }

    public function testJoinCompositeBrackets(): void
    {
        $composites = [
            $this->db->composite_create('a.x', '=', 'b.x', 'AND', 1, 0),
            $this->db->composite_create('a.y', '=', 'b.y', 'AND', 0, 1),
        ];
        $this->db->from('a', 'a')->join_composite('b', 'b', $composites, 'inner')->get();

        $this->assertStringContainsString('ON (a.x = b.x AND a.y = b.y)', $this->sql());
    }

    public function testJoinCompositeUnknownMethodBecomesPlainJoin(): void
    {
        $this->db->from('a', 'a')->join_composite('b', 'b', [$this->db->composite_create('a.x', '=', 'b.x')], 'weird')->get();

        $this->assertSame('SELECT * FROM `a` a JOIN `b` b ON a.x = b.x', $this->sql());
    }

    public function testJoinWithoutMainTableAliasStillRuns(): void
    {
        // CURRENT BEHAVIOUR: get() has an "alias required" guard, but
        // array_search() returns false (not 0) for a missing alias, so the
        // guard can never fire and the query is built without an alias.
        $this->db->from('users')->join('orders', 'o', 'user_id', 'users', 'id')->get();

        $this->assertSame('SELECT * FROM `users` LEFT JOIN `orders` o ON (o.`user_id` = users.`id`)', $this->sql());
    }

    // ---- get / get_first ------------------------------------------------

    public function testGetReturnsObjectsByDefault(): void
    {
        $this->mysqli->rows = [['id' => 1], ['id' => 2]];

        $rows = $this->db->from('users')->get();

        $this->assertEquals([(object) ['id' => 1], (object) ['id' => 2]], $rows);
    }

    public function testGetSupportsAssocType(): void
    {
        $this->mysqli->rows = [['id' => 1]];

        $this->assertSame([['id' => 1]], $this->db->from('users')->get('assoc'));
    }

    public function testGetClearsBuilderStateButKeepsTheTable(): void
    {
        $this->db->from('users')->where('a', 1)->order_by('a')->limit(3)->select('id')->get();
        $this->db->get();

        $this->assertSame('SELECT * FROM `users`', $this->sql());
    }

    public function testGetFirstAddsLimitOneAndReturnsFirstRow(): void
    {
        $this->mysqli->rows = [['id' => 7], ['id' => 8]];

        $row = $this->db->from('users')->where('id', 7)->get_first();

        $this->assertSame('SELECT * FROM `users` WHERE `id` = "7" LIMIT 0, 1', $this->sql());
        $this->assertEquals((object) ['id' => 7], $row);
    }

    public function testGetFirstReturnsEmptyObjectWhenNoRows(): void
    {
        $this->mysqli->rows = [];

        $this->assertEquals(new stdClass(), $this->db->from('users')->get_first());
    }

    // ---- insert ---------------------------------------------------------

    public function testInsertBuildsColumnsAndEscapedValues(): void
    {
        $this->db->insert('users', ['name' => 'Bob', 'age' => 30]);

        $this->assertSame('INSERT INTO `users`(`name`,`age`) VALUES("Bob","30");', end($this->mysqli->queries));
    }

    public function testInsertReturnsOneOnSuccessAndStoresLastId(): void
    {
        $this->mysqli->insert_id = 42;

        $this->assertSame(1, $this->db->insert('users', ['n' => 'x']));
        $this->assertSame(42, $this->db->last_id);
    }

    public function testInsertReturnsZeroOnFailure(): void
    {
        $this->mysqli->fail = true;

        $this->assertSame(0, $this->db->insert('users', ['n' => 'x']));
    }

    public function testInsertEscapesValues(): void
    {
        $this->db->insert('t', ['n' => 'O"Reilly']);

        $this->assertSame('INSERT INTO `t`(`n`) VALUES("O\\"Reilly");', end($this->mysqli->queries));
    }

    public function testInsertPassesNowThroughAndMapsNullToSqlNull(): void
    {
        $this->db->insert('t', ['a' => 'NOW()', 'b' => null, 'c' => 'null', 'd' => 'now()']);

        $this->assertSame('INSERT INTO `t`(`a`,`b`,`c`,`d`) VALUES(NOW(),NULL,NULL,now());', end($this->mysqli->queries));
    }

    public function testInsertEscapesBackticksInColumnNames(): void
    {
        $this->db->insert('t', ['a`) VALUES ("x"); -- ' => 1]);

        $this->assertSame(
            'INSERT INTO `t`(`a``) VALUES ("x"); -- `) VALUES("1");',
            end($this->mysqli->queries)
        );
    }

    public function testInsertWithMultiFlagDelegatesToInsertMulti(): void
    {
        $this->db->insert('t', [['a' => 1], ['a' => 2]], 1);

        $this->assertStringStartsWith('INSERT INTO `t` (`a`) VALUES("1"),("2")', (string) end($this->mysqli->queries));
    }

    public function testInsertMultiBuildsOneStatement(): void
    {
        $this->db->insert_multi('t', [['a' => 1, 'b' => 'x'], ['a' => 2, 'b' => 'y']]);

        $this->assertSame('INSERT INTO `t` (`a`,`b`) VALUES("1","x"),("2","y");', end($this->mysqli->queries));
    }

    public function testInsertBatchIsAnAliasOfInsertMulti(): void
    {
        $this->db->insert_batch('t', [['a' => 1]]);

        $this->assertSame('INSERT INTO `t` (`a`) VALUES("1");', end($this->mysqli->queries));
    }

    public function testInsertIgnoreMultiBackticksColumns(): void
    {
        $this->db->insert_ignore_multi('t', [['a' => 1], ['a' => 2]]);

        $this->assertSame('INSERT IGNORE INTO `t`(`a`) VALUES("1"),("2");', end($this->mysqli->queries));
    }

    public function testInsertMultiPassesNowAndNullRaw(): void
    {
        $this->db->insert_multi('t', [['a' => 'now()', 'b' => 'null']]);

        $this->assertSame('INSERT INTO `t` (`a`,`b`) VALUES(now(),null);', end($this->mysqli->queries));
    }

    // ---- update ---------------------------------------------------------

    public function testUpdateBuildsSetAndWhere(): void
    {
        $this->db->where('id', 1)->update('users', ['name' => 'Bob', 'age' => 30]);

        $this->assertSame('UPDATE `users` SET `name` = "Bob",`age` = "30" WHERE `id` = "1"', trim((string) end($this->mysqli->queries)));
    }

    public function testUpdatePassesNowAndNullRaw(): void
    {
        $this->db->where('id', 1)->update('t', ['a' => 'NOW()', 'b' => 'null', 'c' => 'NULL']);

        $this->assertSame('UPDATE `t` SET `a` = NOW(),`b` = null,`c` = NULL WHERE `id` = "1"', trim((string) end($this->mysqli->queries)));
    }

    public function testUpdateWithoutWhereTouchesEveryRow(): void
    {
        $this->db->update('users', ['active' => 0]);

        $this->assertSame('UPDATE `users` SET `active` = "0"', end($this->mysqli->queries));
    }

    public function testUpdateAppliesPagesizeAsLimit(): void
    {
        $this->db->where('a', 1)->pagesize(5)->update('t', ['b' => 2]);

        $this->assertStringEndsWith(' LIMIT 5', (string) end($this->mysqli->queries));
    }

    public function testUpdateRecordsLastQueryAndClearsWhere(): void
    {
        $this->db->where('id', 1)->update('t', ['a' => 1]);
        $first = $this->db->getLastQuery();
        $this->db->update('t', ['a' => 2]);

        $this->assertStringContainsString('WHERE `id` = "1"', $first);
        $this->assertSame('UPDATE `t` SET `a` = "2"', end($this->mysqli->queries));
    }

    public function testUpdateReturnsZeroOnFailure(): void
    {
        $this->mysqli->fail = true;

        $this->assertSame(0, $this->db->update('t', ['a' => 1]));
    }

    // ---- delete ---------------------------------------------------------

    public function testDeleteWithWhere(): void
    {
        $this->db->where('id', 3)->delete('users');

        $this->assertSame('DELETE FROM `users` WHERE `id` = "3"', trim((string) end($this->mysqli->queries)));
    }

    public function testDeleteAppliesPagesizeAsLimit(): void
    {
        $this->db->where('a', 1)->pagesize(1)->delete('users');

        $this->assertStringEndsWith(' LIMIT 1', (string) end($this->mysqli->queries));
    }

    public function testDeleteWithoutWhereDeletesEverything(): void
    {
        $this->db->delete('users');

        $this->assertSame('DELETE FROM `users`', end($this->mysqli->queries));
    }

    // ---- replace --------------------------------------------------------

    public function testReplaceBuildsStatementAndStoresLastId(): void
    {
        $this->mysqli->insert_id = 9;

        $this->assertSame(1, $this->db->replace('t', ['a' => 1, 'b' => null]));
        $this->assertSame('REPLACE INTO `t` (`a`,`b`) VALUES("1",NULL);', end($this->mysqli->queries));
        $this->assertSame(9, $this->db->last_id);
    }

    public function testReplaceMultiUsesKeysAsColumnNames(): void
    {
        $this->db->replace_multi('t', [['a' => 1, 'b' => 'x'], ['a' => 2, 'b' => 'y']]);

        $this->assertSame('REPLACE INTO `t` (`a`,`b`) VALUES("1","x"),("2","y");', end($this->mysqli->queries));
    }

    public function testReplaceMultiViaReplaceFlag(): void
    {
        $this->db->replace('t', [['a' => 1]], 1);

        $this->assertSame('REPLACE INTO `t` (`a`) VALUES("1");', end($this->mysqli->queries));
    }

    // ---- exec / query / errors ------------------------------------------

    public function testExecReturnsOneOrZero(): void
    {
        $this->assertSame(1, $this->db->exec('DELETE FROM t'));

        $this->mysqli->fail = true;
        $this->assertSame(0, $this->db->exec('DELETE FROM t'));
    }

    public function testExecRaisesNoticeOnFailureWhenDebugIsOn(): void
    {
        $this->db->setDebug(1);
        $this->mysqli->fail = true;

        $seen = $this->captureErrors(fn () => $this->db->exec('BAD SQL'));

        $this->assertCount(1, $seen);
        $this->assertSame(E_USER_NOTICE, $seen[0][0]);
        $this->assertStringContainsString('You have an error in your SQL syntax', $seen[0][1]);
        $this->assertStringContainsString('BAD SQL', $seen[0][1]);
    }

    public function testQueryReturnsRowsByType(): void
    {
        $this->mysqli->rows = [['id' => 1]];
        $this->assertEquals([(object) ['id' => 1]], $this->db->query('SELECT 1'));

        $this->assertSame([['id' => 1]], $this->db->query('SELECT 1', 0, 0, 'assoc'));

        $this->assertSame([['id' => 1, 0 => 1]], $this->db->query('SELECT 1', 0, 0, 'array'));
    }

    public function testFailedQueryReturnsErrorTripleAndRaisesNotice(): void
    {
        $this->mysqli->fail = true;

        $result = null;
        $seen = $this->captureErrors(function () use (&$result) {
            $result = $this->db->query('SELECT broken');
        });

        $this->assertSame([1064, 'You have an error in your SQL syntax', 'SELECT broken'], $result);
        $this->assertCount(1, $seen);
        $this->assertSame(E_USER_NOTICE, $seen[0][0]);
    }

    public function testErrorStateAccumulatesAcrossFailedQueries(): void
    {
        // CURRENT BEHAVIOUR: debug() appends to fieldvalue and nothing
        // clears it, so the second failure also returns the first one's data.
        $this->mysqli->fail = true;
        $second = null;
        $this->captureErrors(function () use (&$second) {
            $this->db->query('first');
            $second = $this->db->query('second');
        });

        $this->assertCount(6, $second);
        $this->assertSame('first', $second[2]);
        $this->assertSame('second', $second[5]);
    }

    /**
     * Run $fn while collecting PHP errors instead of letting PHPUnit report them.
     *
     * @return list<array{int, string}>
     */
    private function captureErrors(callable $fn): array
    {
        $seen = [];
        set_error_handler(function (int $no, string $str) use (&$seen) {
            $seen[] = [$no, $str];
            return true;
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        return $seen;
    }

    // ---- full generated SQL ---------------------------------------------
    //
    // Each case builds a complete statement and compares the whole SQL string,
    // so clause order, quoting and spacing between clauses are all pinned.

    /** @return array<string, array{callable, string}> */
    public static function fullSelectProvider(): array
    {
        return [
            'blog by slug (select_as)' => [
                fn ($db) => $db->select_as('id', 'blog_id')->from('d_blog')->where('slug', 'what-is-seme-framework'),
                'SELECT id AS \'blog_id\' FROM `d_blog` WHERE `slug` = "what-is-seme-framework"',
            ],
            'select star, no where' => [
                fn ($db) => $db->from('d_blog'),
                'SELECT * FROM `d_blog`',
            ],
            'columns and two conditions' => [
                fn ($db) => $db->select('id')->select('title')->from('d_blog')
                    ->where('is_active', 1)->where('author_id', 7),
                'SELECT `id`, `title` FROM `d_blog` WHERE `is_active` = "1" AND `author_id` = "7"',
            ],
            'or condition' => [
                fn ($db) => $db->from('d_blog')->where('a', 1, 'OR')->where('b', 2),
                'SELECT * FROM `d_blog` WHERE `a` = "1" OR `b` = "2"',
            ],
            'where in' => [
                fn ($db) => $db->from('d_blog')->where_in('id', [1, 2, 3]),
                'SELECT * FROM `d_blog` WHERE id IN ("1", "2", "3")',
            ],
            'order by' => [
                fn ($db) => $db->from('d_blog')->order_by('cdate', 'desc'),
                'SELECT * FROM `d_blog` ORDER BY cdate DESC',
            ],
            'group by' => [
                fn ($db) => $db->select_as('COUNT(*)', 'total')->from('d_blog')->group_by('author_id'),
                'SELECT COUNT(*) AS \'total\' FROM `d_blog` GROUP BY author_id',
            ],
            'limit' => [
                fn ($db) => $db->from('d_blog')->limit(5, 10),
                'SELECT * FROM `d_blog` LIMIT 5, 10',
            ],
            'where + group + order + limit' => [
                fn ($db) => $db->from('d_blog')->where('is_active', 1)
                    ->group_by('author_id')->order_by('author_id')->limit(0, 20),
                'SELECT * FROM `d_blog` WHERE `is_active` = "1" GROUP BY author_id ORDER BY author_id ASC LIMIT 0, 20',
            ],
            'alias join' => [
                fn ($db) => $db->from('d_blog', 'a')->join('b_user', 'u', 'id', 'a', 'author_id'),
                'SELECT * FROM `d_blog` a LEFT JOIN `b_user` u ON (u.`id` = a.`author_id`)',
            ],
        ];
    }

    #[DataProvider('fullSelectProvider')]
    public function testFullSelectStatement(callable $build, string $expected): void
    {
        $build($this->db);
        $this->db->get();

        $this->assertSame($expected, $this->sql());
    }

    public function testFullSelectFromTheBlogExample(): void
    {
        $this->mysqli->rows = [['blog_id' => 42]];

        $this->db->select_as('id', 'blog_id');
        $this->db->from('d_blog');
        $this->db->where('slug', 'what-is-seme-framework');
        $rows = $this->db->get();

        $this->assertSame(
            'SELECT id AS \'blog_id\' FROM `d_blog` WHERE `slug` = "what-is-seme-framework"',
            $this->sql()
        );
        $this->assertEquals([(object) ['blog_id' => 42]], $rows);
    }

    public function testFullInsertStatement(): void
    {
        $this->db->insert('d_blog', ['title' => 'Hi', 'slug' => 'hi']);

        $this->assertSame('INSERT INTO `d_blog`(`title`,`slug`) VALUES("Hi","hi");', $this->sql());
    }

    public function testFullUpdateStatement(): void
    {
        $this->db->where('id', 5)->update('d_blog', ['title' => 'New']);

        $this->assertSame('UPDATE `d_blog` SET `title` = "New" WHERE `id` = "5"', $this->sql());
    }

    public function testFullDeleteStatement(): void
    {
        $this->db->where('id', 5)->delete('d_blog');

        $this->assertSame('DELETE FROM `d_blog` WHERE `id` = "5"', $this->sql());
    }

    // ---- state / misc ---------------------------------------------------

    public function testFlushQueryResetsClauses(): void
    {
        $this->db->from('users')->where('a', 1)->order_by('a')->limit(3)->group_by('g')->select('id');
        $this->db->flushQuery();

        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
    }

    public function testSetDebugNormalisesToOneOrZero(): void
    {
        $this->assertSame($this->db, $this->db->setDebug('yes'));
        $this->assertSame(1, $this->db->is_debug);

        $this->db->setDebug(0);
        $this->assertSame(0, $this->db->is_debug);
    }

    public function testTransactionHelpersDelegateToMysqli(): void
    {
        $this->db->begin();
        $this->db->savepoint('sp1');
        $this->db->rollback();
        $this->db->commit();
        $this->db->autocommit(0);

        $this->assertSame(
            ['begin_transaction', 'savepoint:sp1', 'rollback', 'commit', 'autocommit:0'],
            $this->mysqli->calls
        );
    }

    public function testCharsetHelpers(): void
    {
        $this->assertSame('utf8mb4', $this->db->getCharSet());
        $this->assertSame(1, $this->db->setCharSet('latin1'));
        $this->assertContains('set_charset:latin1', $this->mysqli->calls);
    }

    public function testEngineEncryptHelperReliesOnAMissingDbProperty(): void
    {
        // CURRENT BEHAVIOUR (bug): __encrypt() reads $this->db and
        // $this->config, neither of which exists on the engine itself
        // (they exist on SENE_Model). Calling it fails.
        $this->expectException(Error::class);

        $this->captureErrors(fn () => $this->db->__encrypt('x'));
    }

    // ---- identifier escaping ----------------------------------------------

    public function testTableNamesAreBacktickQuoted(): void
    {
        $this->assertSame('SELECT * FROM `us``ers`', $this->selectSql('us`ers'));
    }

    public function testQualifiedTableNameIsQuotedPerPart(): void
    {
        $this->assertSame('SELECT * FROM `shop`.`users`', $this->selectSql('shop.users'));
    }

    public function testWriteStatementsQuoteTableNames(): void
    {
        $this->db->insert('a`b', ['x' => 1]);
        $this->assertSame('INSERT INTO `a``b`(`x`) VALUES("1");', end($this->mysqli->queries));

        $this->db->update('a`b', ['x' => 1]);
        $this->assertSame('UPDATE `a``b` SET `x` = "1"', end($this->mysqli->queries));

        $this->db->delete('a`b');
        $this->assertSame('DELETE FROM `a``b`', end($this->mysqli->queries));

        $this->db->replace('a`b', ['x' => 1]);
        $this->assertSame('REPLACE INTO `a``b` (`x`) VALUES("1");', end($this->mysqli->queries));

        $this->db->insert_ignore_multi('a`b', [['x' => 1]]);
        $this->assertSame('INSERT IGNORE INTO `a``b`(`x`) VALUES("1");', end($this->mysqli->queries));
    }

    public function testUpdateQuotesColumnNames(): void
    {
        $this->db->update('t', ['a` = 1, `b' => 2]);

        $this->assertSame('UPDATE `t` SET `a`` = 1, ``b` = "2"', end($this->mysqli->queries));
    }

    public function testSelectQuotesColumnsAndKeepsTableStar(): void
    {
        $this->db->select(['u.id', 'u.*', 'we`ird']);

        $this->assertSame('SELECT `u`.`id`, `u`.*, `we``ird` FROM `users`', $this->selectSql());
    }

    public function testJoinQuotesTableAndKeys(): void
    {
        $this->db->from('users', 'u')->join('or`ders', 'o', 'user`id', 'u', 'id')->get();

        $this->assertSame(
            'SELECT * FROM `users` u LEFT JOIN `or``ders` o ON (o.`user``id` = u.`id`)',
            $this->sql()
        );
    }

    #[DataProvider('hostileAliasProvider')]
    public function testAliasesAreValidated(string $alias): void
    {
        $this->assertRejected(fn () => $this->db->from('users', $alias), 'from: invalid table alias');
        $this->assertRejected(fn () => $this->db->join('o', $alias, 'k', 'u', 'id'), 'join: invalid table alias');
        $this->assertRejected(fn () => $this->db->join('o', 'o', 'k', $alias, 'id'), 'join: invalid table alias');
        $this->assertRejected(fn () => $this->db->join_composite('o', $alias, []), 'join_composite: invalid table alias');
    }

    /** @return array<string, array{string}> */
    public static function hostileAliasProvider(): array
    {
        return [
            'statement' => ['u; DROP TABLE users'],
            'space'     => ['a b'],
            'dot'       => ['a.b'],
            'backtick'  => ['a`'],
            'leading digit' => ['1a'],
        ];
    }

    public function testWhereInAndBetweenAndNullChecksValidateTheColumn(): void
    {
        $this->assertRejected(fn () => $this->db->where_in('id) OR (1', [1]), 'where_in: invalid column');
        $this->assertRejected(fn () => $this->db->where_null('a OR 1=1'), 'where_null: invalid column');
        $this->assertRejected(fn () => $this->db->where_not_null('a OR 1=1'), 'where_not_null: invalid column');
    }

    public function testValidatorsRejectTrailingNewline(): void
    {
        // PCRE's $ would accept "name\n" without the D modifier.
        $this->assertRejected(fn () => $this->db->from('users', "u\n"), 'from: invalid table alias');
    }

    public function testWhereInAcceptsQualifiedColumns(): void
    {
        $this->assertSame('u.id IN ("1")', $this->whereOf(fn ($db) => $db->where_in('u.id', [1])));
    }

    public function testEmptyTableNameIsRejected(): void
    {
        $this->assertRejected(fn () => $this->db->delete('x.'), 'empty identifier');
    }

    public function testRawByDesignApisStillAcceptExpressions(): void
    {
        // Documented escape hatches for expressions; callers own the safety.
        $this->db->select_as('COUNT(DISTINCT u.id)', 'n')->where_as('a.x', 'b.y');

        $this->assertSame("SELECT COUNT(DISTINCT u.id) AS 'n' FROM `users` WHERE a.x = b.y", $this->selectSql());
    }

    // ---- union ----------------------------------------------------------

    private function buildTwoTableUnion(): void
    {
        $this->db->from('a')->where('x', 1)->union_create();
        $this->db->from('b')->union_create();
    }

    public function testUnionCombinesSavedQueries(): void
    {
        $this->buildTwoTableUnion();
        $this->db->union_select('id');

        $this->db->union_get();

        $this->assertSame(
            'SELECT id FROM ( SELECT * FROM `a` WHERE `x` = "1" UNION SELECT * FROM `b` ) AS u1',
            $this->sql()
        );
    }

    public function testUnionSelectAliasGroupOrderAndLimit(): void
    {
        $this->buildTwoTableUnion();
        $this->db->union_select('COUNT(*)', 'total')
            ->union_alias('t')
            ->union_group_by('x')
            ->union_order_by('total', 'desc')
            ->union_limit(5, 10);

        $this->db->union_get();

        $this->assertSame(
            'SELECT COUNT(*) AS total FROM ( SELECT * FROM `a` WHERE `x` = "1" UNION SELECT * FROM `b` ) AS t GROUP BY x ORDER BY total DESC LIMIT 5, 10',
            $this->sql()
        );
    }

    public function testUnionWithoutSelectUsesStar(): void
    {
        $this->buildTwoTableUnion();

        $this->db->union_get();

        $this->assertStringStartsWith('SELECT * FROM (', $this->sql());
    }

    public function testUnionCreateFlushesBuilderStateByDefault(): void
    {
        $this->db->from('a')->where('x', 1)->union_create();

        $this->assertSame('SELECT * FROM `a`', $this->selectSql('a'));
    }

    public function testUnionCreateCanKeepBuilderState(): void
    {
        $this->db->from('a')->where('x', 1)->union_create(0);

        $this->assertSame('SELECT * FROM `a` WHERE `x` = "1"', $this->selectSql('a'));
    }

    public function testUnionLimitVariants(): void
    {
        // A single bound is stored as int, two bounds as a "a, b" string.
        $this->db->union_limit(0, 7);
        $this->assertSame(7, $this->db->union->limit);

        $this->db->union_limit(3);
        $this->assertSame(3, $this->db->union->limit);

        $this->db->union_limit(5, 10);
        $this->assertSame('5, 10', $this->db->union->limit);

        $this->db->union_limit(0, 0);
        $this->assertSame('', $this->db->union->limit);
    }

    public function testUnionFlushRestoresDefaults(): void
    {
        $this->buildTwoTableUnion();
        $this->db->union_select('id')->union_alias('t')->union_flush();

        $this->assertSame([], $this->db->union->table);
        $this->assertSame([], $this->db->union->select);
        $this->assertSame('u1', $this->db->union->from_as);
    }
}
