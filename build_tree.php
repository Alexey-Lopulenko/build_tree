<?php
declare(strict_types=1);


$config = require __DIR__ . '/config.php';

final class Db
{
    private \PDO $pdo;

    public function __construct(array $dbConfig)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConfig['host'],
            (int)$dbConfig['port'],
            $dbConfig['name'],
            $dbConfig['charset'] ?? 'utf8'
        );

        $this->pdo = new \PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }
}

final class CategoryTreeBuilder
{
    /** @var array<int, array<int>> */
    private array $childrenByParent = [];

    /**
     * @param array<array{categories_id:int|string|null, parent_id:int|string|null}> $rows
     */
    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            if ($row['categories_id'] === null) {
                continue;
            }

            $id = (int)$row['categories_id'];
            $parentId = (int)($row['parent_id'] ?? 0);

            $this->childrenByParent[$parentId][] = $id;
        }
    }

    public function build(int $rootParentId = 0): array
    {
        return $this->buildNode($rootParentId);
    }

    private function buildNode(int $parentId): array
    {
        $result = [];
        $children = $this->childrenByParent[$parentId] ?? [];

        foreach ($children as $childId) {
            $hasChildren = !empty($this->childrenByParent[$childId] ?? []);

            $result[$childId] = $hasChildren
                ? $this->buildNode($childId)
                : $childId;
        }

        return $result;
    }
}

$start = microtime(true);

try {
    $db = new Db($config['db']);

    $table = $config['table'];
    $rootParentId = (int)$config['root_parent_id'];

    $sql = "SELECT SQL_NO_CACHE categories_id, parent_id FROM {$table}";
    $rows = $db->pdo()->query($sql)->fetchAll();

    $tree = (new CategoryTreeBuilder($rows))->build($rootParentId);

    $elapsed = microtime(true) - $start;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'time_sec' => round($elapsed, 6),
        'rows' => count($rows),
        'result' => $tree,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}