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

    public function testWhereArrayFormWithoutJoinerGluesConditionsTogether(): void
    {
        // CURRENT BEHAVIOUR: the joiner defaults to '' in array form, so
        // two conditions are emitted with no AND between them (invalid SQL).
        $this->assertSame(
            '`a` = "1" `b` = "2"',
            $this->whereOf(fn ($db) => $db->where(['a' => 1, 'b' => 2]))
        );
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

    /** @return array<string, array{mixed, mixed, string}> */
    public static function betweenBoundProvider(): array
    {
        return [
            'ints'                 => [18, 65, '( age BETWEEN 18 AND 65)'],
            'negative'             => [-5, 5, '( age BETWEEN -5 AND 5)'],
            'numeric strings'      => ['18', '65.5', '( age BETWEEN 18 AND 65.5)'],
            'floats'               => [1.5, 2.25, '( age BETWEEN 1.5 AND 2.25)'],
            'dates are quoted'     => ['2024-01-01', '2024-12-31', '( age BETWEEN "2024-01-01" AND "2024-12-31")'],
            'null bound'           => [null, 10, '( age BETWEEN NULL AND 10)'],
            'bool becomes int'     => [false, true, '( age BETWEEN 0 AND 1)'],
            'quotes are escaped'   => ['a"b', 'c', '( age BETWEEN "a\\"b" AND "c")'],
        ];
    }

    #[DataProvider('betweenBoundProvider')]
    public function testBetweenRendersBoundsAsLiterals(mixed $low, mixed $high, string $expected): void
    {
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->between('age', $low, $high)));
    }

    #[DataProvider('hostileBoundProvider')]
    public function testBetweenCannotBeInjectedThroughBounds(string $hostile, string $expected): void
    {
        // These used to be pasted into the SQL verbatim.
        $this->assertSame($expected, $this->whereOf(fn ($db) => $db->between('age', $hostile, 2)));
    }

    /** @return array<string, array{string, string}> */
    public static function hostileBoundProvider(): array
    {
        return [
            'boolean injection' => ['1 OR 1=1', '( age BETWEEN "1 OR 1=1" AND 2)'],
            'quote breakout'    => ['1" OR "1"="1', '( age BETWEEN "1\\" OR \\"1\\"=\\"1" AND 2)'],
            'statement'         => ['1; DROP TABLE t', '( age BETWEEN "1; DROP TABLE t" AND 2)'],
            'leading number'    => ['1abc', '( age BETWEEN "1abc" AND 2)'],
            // sql() collapses whitespace, so the quoted "1\n" reads as "1 ".
            'trailing newline'  => ["1\n", '( age BETWEEN "1 " AND 2)'],
        ];
    }

    public function testBetweenUpperBoundIsEscapedToo(): void
    {
        $this->assertSame(
            '( age BETWEEN 1 AND "2 OR 1=1")',
            $this->whereOf(fn ($db) => $db->between('age', 1, '2 OR 1=1'))
        );
    }

    public function testBetweenNumericStringsWithExponentOrSignAreQuoted(): void
    {
        // Only plain decimals are treated as numbers; the rest is quoted.
        $this->assertSame(
            '( age BETWEEN "1e3" AND "+5")',
            $this->whereOf(fn ($db) => $db->between('age', '1e3', '+5'))
        );
    }

    public function testWhereAsPassesValueThroughRaw(): void
    {
        // where_as() is for column-to-column comparisons: neither side is
        // quoted or backticked.
        $this->assertSame('a.x = b.y', $this->whereOf(fn ($db) => $db->where_as('a.x', 'b.y')));
    }

    public function testTrailingKeywordTrimIsACharacterMaskNotASuffix(): void
    {
        // CURRENT BEHAVIOUR: get() strips the trailing joiner with
        // rtrim($where, "AND "), which removes any run of the characters
        // A, N, D and space, so a raw operand ending in "ID" loses its "D".
        $where = $this->whereOf(fn ($db) => $db->where_as('a.parent', 'b.ID'));

        $this->assertSame('a.parent = b.I', $where);
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

    #[DataProvider('hostileOrderColumnProvider')]
    public function testOrderByRejectsExpressionsAndInjection(string $column): void
    {
        $this->assertRejected(fn () => $this->db->order_by($column), 'order_by: invalid column');
    }

    /** @return array<string, array{string}> */
    public static function hostileOrderColumnProvider(): array
    {
        return [
            'statement'      => ['name; DROP TABLE users'],
            'subquery'       => ['(SELECT 1)'],
            'function'       => ['RAND()'],
            'comment'        => ['name -- x'],
            'space'          => ['name desc'],
        ];
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

    public function testOrderByArrayFormValidatesKeysAndDirections(): void
    {
        $this->assertRejected(fn () => $this->db->order_by(['a; b' => 'asc']), 'order_by: invalid column');
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

    public function testGroupByRejectsExpressions(): void
    {
        $this->assertRejected(fn () => $this->db->group_by('role; DROP TABLE users'), 'group_by: invalid column');
        $this->assertRejected(fn () => $this->db->group_by(['ok', 'a b']), 'group_by: invalid column');
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

    public function testLimitPageTreatsPageNumberAsOffset(): void
    {
        // CURRENT BEHAVIOUR: for page > 1 the page number itself is used as
        // the OFFSET, so page 3 of 10 yields "LIMIT 3, 10". page() computes
        // the real offset (page - 1) * size.
        $this->db->limitpage(3, 10);

        $this->assertSame('SELECT * FROM `users` LIMIT 3, 10', $this->selectSql());
    }

    public function testNoLimitClearsPreviousLimit(): void
    {
        $this->db->limit(10)->nolimit();

        $this->assertSame('SELECT * FROM `users`', $this->selectSql());
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
        $this->assertRejected(fn () => $this->db->between('age BETWEEN 1 AND 2 OR 1', 1, 2), 'between: invalid column');
        $this->assertRejected(fn () => $this->db->where_null('a OR 1=1'), 'where_null: invalid column');
        $this->assertRejected(fn () => $this->db->where_not_null('a OR 1=1'), 'where_not_null: invalid column');
    }

    public function testValidatorsRejectTrailingNewline(): void
    {
        // PCRE's $ would accept "name\n" without the D modifier.
        $this->assertRejected(fn () => $this->db->order_by("name\n"), 'order_by: invalid column');
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
