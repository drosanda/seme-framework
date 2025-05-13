<?php
/**
 * @author: Daeng Rosanda
 * @package SemeFramework
 * @since SemeFramework 4.0.3
 */
class SENE_Cli extends \SENE_Engine
{
    const TABLE_MIGRATION = '__table_migration_history';

    public function __construct()
    {
        parent::__construct();
        $this->directories->old_app_controller = $this->directories->app_controller;
        $this->directories->app_controller = $this->directories->dev_controller;
        $this->directories->old_app_model = $this->directories->app_model;
        $this->directories->old_app_core = $this->directories->app_core;
        $this->directories->app_model = $this->directories->dev_model;
        $this->directories->app_core = $this->directories->dev_core;

        $this->config->routes['db:schema'] = 'db/schema/';
        $this->config->routes['schema:generate'] = 'schema/generate/';
        $this->config->routes['table:migration:create'] = 'migration/create/';
        $this->config->routes['migration:create'] = 'migration/create/';
    }

}
