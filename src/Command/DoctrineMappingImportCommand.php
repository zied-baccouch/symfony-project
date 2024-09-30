<?php
namespace App\Command;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
#[AsCommand(
    name: 'doctrine:mapping:import',
    description: 'instead of `php bin/console doctrine:mapping:import App\\Entity attribute --path=src/Entity` [--ucfirst=true] [--table=test,test1] [--without-table-prefix=eq_]',
)]
class DoctrineMappingImportCommand extends Command
{
    private string $tableName = "";
    private string $tableList = "";
    private string $ucfirst = "";
    private string $withoutTablePrefix = "";
    private array $tableInfo = [];
    private string $database;
    private string $entityName = "";
    private string $root = "";
    private string $entityDir = "";
    private string $repositoryDir = "";
    private string $namespace = "App\\Entity";
    private string $type = "attribute";
    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel
    ) {
        parent::__construct();
        $this->database = $this->connection->getDatabase();
        $this->root = $this->kernel->getProjectDir();
        $this->entityDir = $this->root . "/src/Entity/";
        $this->repositoryDir = $this->root . "/src/Repository/";
        if (!file_exists($this->entityDir)) {
            mkdir($this->entityDir);
        }
        if (!file_exists($this->repositoryDir)) {
            mkdir($this->repositoryDir);
        }
    }
    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::OPTIONAL, "the entity's namespace, App\\Entity on default")
            ->addArgument('type', InputArgument::OPTIONAL, "attribute, xml, yaml, php; attribute on default")
            ->addOption('path', "", InputOption::VALUE_OPTIONAL, "the Entity's path, src/Entity on default")
            ->addOption('table', "t", InputOption::VALUE_OPTIONAL, 'the import tables of the database')
            ->addOption('ucfirst', "", InputOption::VALUE_OPTIONAL, 'convert first character of word to uppercase')
            ->addOption('without-table-prefix', "", InputOption::VALUE_OPTIONAL, 'without table prefix');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        if (!empty($namespace)) {
            $this->namespace = $namespace;
        }
        $type = $input->getArgument('type');
        if (!empty($type)) {
            $this->type = $type;
        }
        $path = (string)$input->getOption('path');
        if (!empty($path)) {
            $this->entityDir = $this->root . "/" . $path;
            if (!file_exists($this->entityDir)) {
                mkdir($this->entityDir, 0755, true);
            }
        }
        $this->tableList = (string)$input->getOption('table');
        $this->ucfirst = (string)$input->getOption('ucfirst');
        $this->withoutTablePrefix = (string)$input->getOption('without-table-prefix');
        $this->import();
        $io->success('Import success!');
        return Command::SUCCESS;
    }
    private function import(): void
    {
        if (empty($this->tableList)) {
            $tableList = $this->getTableList();
        } else {
            $tableList = trim($this->tableList, ',');
            $tableList = explode(',', $tableList);
        }
        foreach ($tableList as $tableName) {
            $this->tableName = $tableName;
            $this->do($this->tableName);
        }
    }
    /**
     * @throws Exception
     */
    private function do(string $tableName): void
    {
        $this->tableInfo = $this->getTableInfo($tableName);
        $this->makeEntity($tableName);
        $this->makeRepository();
    }
    /**
     * @throws Exception
     */
    private function getTableList(): array
    {
        $rs = $this->connection->fetchAllAssociative("show tables");
        return array_column($rs, 'Tables_in_' . $this->database);
    }
    /**
     * @throws Exception
     */
    private function getTableInfo(string $tableName = ""): array
    {
        $sql = "select * from information_schema.columns where table_name='{$tableName}' and TABLE_SCHEMA = '{$this->database}' order by ORDINAL_POSITION";
        return $this->connection->fetchAllAssociative($sql);
    }
    private function makeRepository(): void
    {
        $fileName = $this->entityName . "Repository.php";
        $filePath = $this->repositoryDir . $fileName;
        if (!file_exists($filePath)) {
            $content = <<<EOF
<?php
namespace App\Repository;
use {$this->namespace}\\{$this->entityName};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
/**
 * @extends ServiceEntityRepository<{$this->entityName}>
 *
 * @method {$this->entityName}|null find(\$id, \$lockMode = null, \$lockVersion = null)
 * @method {$this->entityName}|null findOneBy(array \$criteria, array \$orderBy = null)
 * @method {$this->entityName}[]    findAll()
 * @method {$this->entityName}[]    findBy(array \$criteria, array \$orderBy = null, \$limit = null, \$offset = null)
 */
class {$this->entityName}Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry \$registry)
    {
        parent::__construct(\$registry, {$this->entityName}::class);
    }
//    /**
//     * @return {$this->entityName}[] Returns an array of {$this->entityName} objects
//     */
//    public function findByExampleField(\$value): array
//    {
//        return \$this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', \$value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }
//    public function findOneBySomeField(\$value): ?{$this->entityName}
//    {
//        return \$this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', \$value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
EOF;
            file_put_contents($filePath, $content);
        } else {
            $content = file_get_contents($filePath);
            if (!str_contains($content, "@extends")) {
                $replace = <<<EOF
/**
 * @extends ServiceEntityRepository<{$this->entityName}>
 *
 * @method {$this->entityName}|null find(\$id, \$lockMode = null, \$lockVersion = null)
 * @method {$this->entityName}|null findOneBy(array \$criteria, array \$orderBy = null)
 * @method {$this->entityName}[]    findAll()
 * @method {$this->entityName}[]    findBy(array \$criteria, array \$orderBy = null, \$limit = null, \$offset = null)
 */
class {$this->entityName}Repository extends ServiceEntityRepository
EOF;
                $origin = "class {$this->entityName}Repository extends ServiceEntityRepository";
                $newContent = str_replace($origin, $replace, $content);
                file_put_contents($filePath, $newContent);
            }
        }
    }
    private function makeEntity(string $tableName): void
    {
        if (!empty($this->withoutTablePrefix) && str_starts_with($this->tableName, $this->withoutTablePrefix)) {
            $tableName = substr($tableName, strlen($this->withoutTablePrefix));
        }
        $entityName = $this->upperName($tableName);
        $this->entityName = $entityName;
        $fileName = $this->entityName . ".php";
        $filePath = $this->entityDir . $fileName;
        $indexes = $this->makeIndexes();
        [$properties, $getSet] = $this->makeProperties();
        $content = <<<EOF
<?php
namespace {$this->namespace};
use App\Repository\\{$entityName}Repository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Table(name: '{$this->tableName}')]{$indexes}
#[ORM\Entity(repositoryClass: {$entityName}Repository::class)]
class {$entityName}
{
{$properties}
{$getSet}
}
EOF;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        file_put_contents($filePath, $content);
    }
    public function makeProperties(): array
    {
        $properties = "";
        $getSet = "";
        $primaryArray = array_filter($this->tableInfo,function($v){
            return $v['COLUMN_KEY'] === 'PRI';
        });
        $othersArray = array_filter($this->tableInfo,function($v){
            return $v['COLUMN_KEY'] !== 'PRI';
        });
        $this->tableInfo = [];
        array_push($this->tableInfo, ...$primaryArray, ...$othersArray);
        foreach ($this->tableInfo as $item) {
            $type = $item['DATA_TYPE'];
            $columnName = $item['COLUMN_NAME'];
            if ($this->ucfirst === 'true') {
                $columnName = $this->upper($columnName);
            }
            $isNullable = $item['IS_NULLABLE'];
            $columnDefault = $item['COLUMN_DEFAULT'];
            $characterMaximumLength = $item['CHARACTER_MAXIMUM_LENGTH'];
            $numericPrecision = $item['NUMERIC_PRECISION'];
            $numericScale = $item['NUMERIC_SCALE'];
            $columnComment = $item['COLUMN_COMMENT'];
            $isPrimaryKey = ($item['COLUMN_KEY'] === 'PRI');
            $isAutoIncrement = ($item['EXTRA'] === 'auto_increment');
            $nullable = "";
            $nullableType = "";
            if ($isNullable === "YES") {
                $nullable = "nullable: true";
                $nullableType = "?";
            } else {
                if ($type === 'json') {
                    $columnDefault = "[]";
                }
            }
            $varType = match ($type) {
                "bigint", "decimal", "varchar", "char", "text" => "string",
                "smallint", "tinyint", "mediumint" => "int",
                "double" => "float",
                "set", "json" => "array",
                "date", "time", "datetime", "timestamp", "year" => "\DateTimeInterface",
                default => $type,
            };
            $ormColumnParam = [];
            if ($this->ucfirst === 'true') {
                $ormColumnParam[] = "name: \"{$item['COLUMN_NAME']}\"";
            }
            $ormColumnOptionParam = [];
            if(in_array($type, ["int", "smallint", "tinyint", "mediumint", "bigint", "float", "double", "decimal", "char",
                "varchar", "varbinary", "binary", "blob", "text", "set", "json", "date", "time", "datetime", "timestamp", "year"])){
                if ($type === 'smallint') {
                    $ormColumnParam[] = "type: Types::SMALLINT";
                } else if ($type === 'bigint') {
                    $ormColumnParam[] = "type: Types::BIGINT";
                } else if ($type === 'decimal') {
                    $ormColumnParam[] = "type: Types::DECIMAL";
                    $ormColumnParam[] = "precision: $numericPrecision";
                    $ormColumnParam[] = "scale: $numericScale";
                } else if (in_array($type, ['binary', 'varbinary'])) {
                    $ormColumnParam[] = "type: Types::BINARY";
                } else if ($type === 'blob') {
                    $ormColumnParam[] = "type: Types::BLOB";
                } else if ($type === 'text') {
                    $ormColumnParam[] = "type: Types::TEXT";
                } else if ($type === 'set') {
                    $ormColumnParam[] = "type: Types::SIMPLE_ARRAY";
                } else if($type === 'date'){
                    $ormColumnParam[] = "type: Types::DATE_MUTABLE";
                } else if($type === 'time'){
                    $ormColumnParam[] = "type: Types::TIME_MUTABLE";
                } else if (in_array($type, ['datetime', 'timestamp', 'year'])) {
                    $ormColumnParam[] = "type: Types::DATETIME_MUTABLE";
                }
                if (in_array($type, ['char', 'varchar'])) {
                    $ormColumnParam[] = "length: {$characterMaximumLength}";
                }
                if (!empty($nullable)) {
                    $ormColumnParam[] = $nullable;
                }
                if (!empty($columnComment)) {
                    $ormColumnOptionParam[] = "\"comment\" => \"{$columnComment}\"";
                }
                if ($type === "char") {
                    $ormColumnOptionParam[] = "\"fixed\" => true";
                }
                if (!empty($ormColumnOptionParam)) {
                    $ormColumnParam[] = "options: [" . implode(', ', $ormColumnOptionParam) . "]";
                }
                if (empty($ormColumnParam)) {
                    $properties .= "    #[ORM\Column]" . PHP_EOL;
                } else {
                    $ormColumnParam = implode(', ', $ormColumnParam);
                    $properties .= "    #[ORM\Column({$ormColumnParam})]" . PHP_EOL;
                }
                if ($isPrimaryKey) {
                    $properties .= "    #[ORM\Id]" . PHP_EOL;
                    if ($isAutoIncrement) {
                        $strategy = "strategy: \"IDENTITY\"";
                    } else {
                        $strategy = "strategy: \"NONE\"";
                    }
                    $properties .= "    #[ORM\GeneratedValue({$strategy})]" . PHP_EOL;
                }
                if (in_array($type, ['binary', 'varbinary', 'blob'])) {
                    if (isset($columnDefault)) {
                        $properties .= "    private \${$columnName} = {$columnDefault};" . PHP_EOL . PHP_EOL;
                    } else {
                        $properties .= "    private \${$columnName} = null;" . PHP_EOL . PHP_EOL;
                    }
                } else {
                    if (in_array($type, ['bigint', 'decimal', 'char', 'varchar'])) {
                        if (isset($columnDefault)) {
                            $columnDefault = "'{$columnDefault}'";
                            $properties .= "    private ?{$varType} \${$columnName} = {$columnDefault};" . PHP_EOL . PHP_EOL;
                        } else {
                            $properties .= "    private ?{$varType} \${$columnName} = null;" . PHP_EOL . PHP_EOL;
                        }
                    } else {
                        if (isset($columnDefault)) {
                            if (in_array($type, ['date', 'time', 'datetime', 'timestamp', 'year', 'text'])) {
                                $columnDefault = 'null';
                            }
                            $properties .= "    private ?{$varType} \${$columnName} = {$columnDefault};" . PHP_EOL . PHP_EOL;
                        } else {
                            $properties .= "    private ?{$varType} \${$columnName} = null;" . PHP_EOL . PHP_EOL;
                        }
                    }
                }
            }
            $functionName = $this->upperName($columnName);
            if(in_array($type, ["int", "smallint", "bigint", "tinyint", "mediumint", "float", "double", "decimal", "char",
                "varchar", "text", "set", "json", "date", "time", "datetime", "timestamp", "year"])){
                $getSet .= "    public function get{$functionName}(): ?{$varType}" . PHP_EOL;
                $getSet .= "    {" . PHP_EOL;
                $getSet .= "        return \$this->{$columnName};" . PHP_EOL;
                $getSet .= "    }" . PHP_EOL . PHP_EOL;
                if (!$isAutoIncrement) {
                    $getSet .= "    public function set{$functionName}({$nullableType}{$varType} \${$columnName}): static" . PHP_EOL;
                    $getSet .= "    {" . PHP_EOL;
                    $getSet .= "        \$this->{$columnName} = \${$columnName};" . PHP_EOL;
                    $getSet .= PHP_EOL;
                    $getSet .= "        return \$this;" . PHP_EOL;
                    $getSet .= "    }" . PHP_EOL . PHP_EOL;
                }
            }
            if(in_array($type, ["varbinary", "binary", "blob"])){
                $getSet .= "    public function get{$functionName}()" . PHP_EOL;
                $getSet .= "    {" . PHP_EOL;
                $getSet .= "        return \$this->{$columnName};" . PHP_EOL;
                $getSet .= "    }" . PHP_EOL . PHP_EOL;
                $getSet .= "    public function set{$functionName}(\${$columnName}): static" . PHP_EOL;
                $getSet .= "    {" . PHP_EOL;
                $getSet .= "        \$this->{$columnName} = \${$columnName};" . PHP_EOL;
                $getSet .= PHP_EOL;
                $getSet .= "        return \$this;" . PHP_EOL;
                $getSet .= "    }" . PHP_EOL . PHP_EOL;
            }
        }
        return [rtrim($properties), rtrim($getSet)];
    }
    private function makeIndexes(): string
    {
        $sql = "show index from {$this->tableName} from {$this->database} where key_name <> 'PRIMARY'";
        $rs = $this->connection->fetchAllAssociative($sql);
        if (empty($rs)) {
            return "";
        }
        $indexes = [];
        $indexArray = [];
        foreach ($rs as $r) {
            if (isset($indexArray[$r['Key_name']])) {
                $indexArray[$r['Key_name']]['Column_name'][] = $r['Column_name'];
            } else {
                $indexArray[$r['Key_name']] = [
                    'Non_unique' => $r['Non_unique'],
                    'Column_name' => [$r['Column_name']]
                ];
            }
        }
        foreach ($indexArray as $key => $item) {
            $class = "ORM\Index";
            if ($item['Non_unique'] == '0') {
                $class = "ORM\UniqueConstraint";
            }
            $columns = implode(', ', array_map(function ($v) {
                return "'{$v}'";
            }, $item['Column_name']));
            $tmp = "#[{$class}(name: '{$key}', columns: [{$columns}])]";
            $indexes[] = $tmp;
        }
        return PHP_EOL . implode(PHP_EOL, $indexes);
    }
    private function upperName(string $name): string
    {
        return str_replace("_", "", ucwords($name, '_'));
    }
    private function upper(string $name): string
    {
        return lcfirst($this->upperName($name));
    }
}