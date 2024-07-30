<?php
/**
 * Class SENE_Sql
 *
 * This is an initial class for building SQL queries. It provides methods to append query parts,
 * handle placeholders, and fetch the final query string.
 */
#[AllowDynamicProperties]
class SENE_Sql
{
    protected $query_string;

    public function __construct()
    {
        $this->query_string = '';
    }
    /**
     * Reset the query_string property to an empty string.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->query_string = '';
    }

    /**
     * Append a SQL clause or expression to the current query string.
     *
     * @param string $clause The SQL clause or expression to append.
     *
     * @return self For method chaining.
     */
    public function query_string(): string
    {
        return $this->query_string;
    }
}