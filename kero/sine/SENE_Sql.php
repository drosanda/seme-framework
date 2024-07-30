<?php

#[AllowDynamicProperties]
class SENE_Sql
{
    protected $query_string;

    public function __construct()
    {
        $this->query_string = '';
    }

    public function flush(): void
    {
        $this->query_string = '';
    }

    public function query_string(): string
    {
        return $this->query_string;
    }
}