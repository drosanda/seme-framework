<?php
require_once SEMEROOT.'kero/sine/SENE_Migration.php';

class Migration_Executor
{
    private $current_object;
    public function __construct($migration_file_path)
    {
        require_once($migration_file_path);
        $class_name = ucfirst(basename($migration_file_path, '.php'));
        $this->current_object = new $class_name();
    }
    public function up()
    {
        $this->current_object->up();
    }
    public function down()
    {
        $this->current_object->down();
    }
}

class Migration extends \JI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load('schema_model', 's');
        $this->table_migration_directory = SEMEROOT.'/kero/table_migration/';
    }
    public function index($table_name = '')
    {
        $schema_file = SEMEROOT.DS.$this->schema_file;
        if (!is_file($schema_file)) {
            touch($schema_file);
        }
        try {
            $filesize = @filesize($schema_file);
        } catch(Exception $e) {
            echo 'error: '.$e;
            echo 'Schema file on: '.$schema_file.' not exist or empty.'.$this->rn.'Please generate one `php -f ./sine schema:generate`';
        } finally {
            $filesize = 0;
        }

        if($filesize <= 70) {
            echo 'Schema file on: '.$schema_file.' not exist or empty.'.$this->rn.'Please generate one `php -f ./sine schema:generate`';
        } else {
            $fh = fopen($schema_file, 'r');
            $migrate_existing = json_decode(@fread($fh, filesize($schema_file)));
            fclose($fh);


            if(strlen($table_name)) {
                if(isset($migrate_existing->tables->{$table_name})) {
                    print_r($migrate_existing->tables->{$table_name});
                } else {
                    echo 'Schema for table: '.$table_name.' is undefined.'.$this->rn;
                }
            } else {
                echo 'Schema date created: '.$migrate_existing->cdate.$this->rn;
                echo 'Schema last update: '.$migrate_existing->ldate.$this->rn;
            }
        }
    }

    private function table_migration_file_template($migration_identifier)
    {
        $migration_identifier = ucfirst($migration_identifier);
        return <<<EOT
<?php
class $migration_identifier extends \SENE_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        // Your migration code here
    }
    public function down()
    {
        // Your migration code here
    }
}
EOT;

    }

    public function create($name = '')
    {
        if (!is_dir($this->table_migration_directory)) {
            mkdir($this->table_migration_directory, 0755, true);
        }
        $name = strip_tags($name);
        $name = trim(preg_replace("/^[a-z0-9]+$/i", '', $name));
        if (strlen($name)<=4) {
            echo 'Name of migration or identifier name for migration is required';
            exit(1);
        }
        $created_at_string = date('YmdHis');
        $migration_identifier = $name.'_'.$created_at_string;
        $migration_file = $migration_identifier.'.php';
        $table_migration_file_path = $this->table_migration_directory.'/'.$migration_file;
        $fh = fopen($table_migration_file_path, 'w+');
        fwrite($fh, $this->table_migration_file_template($migration_identifier));
        fclose($fh);

        echo 'New table migration file at '.$table_migration_file_path.'.'.PHP_EOL;
    }

    public function run()
    {
        $directory = SEMEROOT."kero/table_migration/";
        $files = glob($directory.'*.php');
        foreach ($files as $file) {
            $migration = new \Migration_Executor($file);
            $migration->up();
        }
    }
}
