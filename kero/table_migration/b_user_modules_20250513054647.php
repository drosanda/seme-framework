<?php
class B_user_modules_20250513054647 extends \SENE_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        $this
            ->table_builder
                ->create("b_user_modules", 'id')
                ->int('id', 11, true, false, true)
                ->int('b_user_id', 10, true, false, null)
                ->int('a_module_id', 4, true, false, null)
                ->timestamps()
                ->default_flags()
                ->primary_key('id')
                ->unique_key('unq_key_1', ['b_user_id', 'a_module_id'])
                ->index('fk_b_user_id_idx', ['b_user_id'])
                ->index('fk_a_module_id_idx', ['a_module_id'])
                ->process()
        ;
    }
    public function down()
    {
        $this->drop_table("b_user_modules");
    }
}