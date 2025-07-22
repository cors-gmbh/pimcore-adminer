<?php

declare(strict_types=1);

/*
 * CORS GmbH
 *
 * This source file is available under the MIT license
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) CORS GmbH (https://www.cors.gmbh)
 * @license    https://www.cors.gmbh/license MIT
 *
 */

namespace CORS\Bundle\AdminerBundle\Model;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\TransactionIsolationLevel;
use Exception;
use PDOException;
use Pimcore\Db;
use Pimcore\Logger;

class PimcoreDbRepository implements Repository
{
    /** @var Connection */
    protected $connection;

    /** @var Statement[] */
    protected static $preparedStatements = [];

    /** @var self */
    private static $instances = [];

    /** @var array for tracking executed SQL queries, uncomment all occurences of self::$debug, too */
    //private static $debug = [];

    public function __construct(
        Connection $connection = null,
    ) {
        $this->connection = $connection ?? Db::get();
        $this->connection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED);

        /*register_shutdown_function(function() {
            arsort(self::$debug);
            var_dump(self::$debug);
        });*/
    }

    /**
     * @return static
     */
    public static function getInstance(Connection $connection = null)
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static($connection);
        }

        return self::$instances[static::class];
    }

    public function getTableName()
    {
        throw new Exception('Please implement getTableName() in a model class');
    }

    private function executeSql($sql, $parameters)
    {
        $parameters = array_values($parameters);
        $dataTypes = $this->getDataTypes($parameters);

        if (in_array(Connection::PARAM_STR_ARRAY, $dataTypes, true) || in_array(Connection::PARAM_INT_ARRAY, $dataTypes, true)) {
            if (class_exists(\Doctrine\DBAL\SQLParserUtils::class)) {
                [$sql, $parameters, $dataTypes] = \Doctrine\DBAL\SQLParserUtils::expandListParameters($sql, $parameters, $dataTypes);
            }

            return $this->connection->executeQuery($sql, $parameters, $dataTypes);
        }

        if (is_string($sql) && !isset(self::$preparedStatements[$sql])) {
            self::$preparedStatements[$sql] = self::prepare($sql);
        }

        if ($sql instanceof Statement) {
            $statement = $sql;
        } else {
            //self::$debug[$sql] = (self::$debug[$sql] ?? 0) + 1;
            $statement = self::$preparedStatements[$sql];
        }

        foreach ($parameters as $index => $parameter) {
            $statement->bindValue($index + 1, $parameter, $dataTypes[$index]);
        }

        return $statement->execute();
    }

    public function execute($sql, $parameters = [])
    {
        $result = $this->executeSql($sql, $parameters);
        if ($result instanceof Result || $result instanceof \Doctrine\DBAL\Driver\Statement) {
            return $result->rowCount();
        }

        return self::$preparedStatements[$sql]->rowCount();
    }

    public function findOneInSql($sql, $parameters = [])
    {
        $result = $this->findRowInSql($sql, $parameters);
        if (null === $result) {
            return false;
        }

        return reset($result);
    }

    public function findRowInSql($sql, $parameters = [])
    {
        $result = $this->findInSql($sql, $parameters);
        $result = reset($result);
        if (false === $result) {
            return null;
        }

        return $result;
    }

    public function findColumnInSql($sql, $parameters = [])
    {
        $result = $this->findInSql($sql, $parameters);

        return array_map(static function ($row) {
            return reset($row);
        }, $result);
    }

    public function findInSql($sql, $parameters = [])
    {
        $result = $this->executeSql($sql, $parameters);

        if ($result instanceof Result || $result instanceof \Doctrine\DBAL\Driver\Statement) {
            return $result->fetchAll(FetchMode::ASSOCIATIVE);
        }

        return self::$preparedStatements[$sql]->fetchAll(FetchMode::ASSOCIATIVE);
    }

    public function findInTable($table, array $where = [], string $order = null, int $count = null, int $offset = 0, $groupBy = null, array $columns = ['*']): array
    {
        $parametersHash = md5(json_encode([$table, array_keys($where), $order, $count, $offset, $groupBy, $columns]));
        if (isset(self::$preparedStatements[$parametersHash])) {
            try {
                $result = $this->executeSql(self::$preparedStatements[$parametersHash], $where);
                if ($result instanceof Result || $result instanceof \Doctrine\DBAL\Driver\Statement) {
                    return $result->fetchAll(FetchMode::ASSOCIATIVE);
                }

                return self::$preparedStatements[$parametersHash]->fetchAll(FetchMode::ASSOCIATIVE);
            } catch (\Exception $e) {
                unset(self::$preparedStatements[$parametersHash]);
            }
        }

        $cache = true;

        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder = $queryBuilder->select(implode(',', $columns))->from($table);
        if (!empty($where)) {
            foreach ($where as $value) {
                if (is_array($value) || null === $value) {
                    $cache = false;

                    break;
                }
            }

            if ($cache) {
                $queryBuilder = $queryBuilder->where(implode(' AND ', array_keys($where)));
            } else {
                $conditions = [];
                foreach ($where as $condition => $value) {
                    if (is_array($value)) {
                        $value = array_map([$this->connection, 'quote'], $value);
                        $conditions[] = str_replace('?', implode(',', $value), $condition);
                    } else {
                        if (null !== $value) {
                            $value = $this->connection->quote($value);
                        } else {
                            $value = '';
                        }
                        $conditions[] = str_replace('?', $value, $condition);
                    }
                }
                $queryBuilder = $queryBuilder->where(implode(' AND ', $conditions));
            }
        }
        if (!empty($order)) {
            $queryBuilder = $queryBuilder->add('orderBy', $order);
        }
        if ($count > 0) {
            $queryBuilder = $queryBuilder->setMaxResults($count);
        }
        $queryBuilder = $queryBuilder->setFirstResult($offset);

        if (null !== $groupBy) {
            if ('distinct' === strtolower($groupBy)) {
                $queryBuilder->distinct();
            } else {
                $queryBuilder->groupBy($groupBy);
            }
        }

        if ($cache) {
            self::$preparedStatements[$parametersHash] = self::prepare($queryBuilder->getSQL());

            return $this->findInTable($table, $where, $order, $count, $offset);
        }

        try {
            return $this->connection->fetchAllAssociative($queryBuilder->getSQL());
        } catch (\Throwable $e) {
            if (method_exists($this->connection, 'fetchAll')) {
                return $this->connection->fetchAll($queryBuilder->getSQL());
            }
        }
    }

    public function find(array $where = [], string $order = null, int $count = null, int $offset = 0, $groupBy = null, array $columns = ['*']): array
    {
        return $this->findInTable($this->getTableName(), $where, $order, $count, $offset, $groupBy, $columns);
    }

    public function findOneInTable($table, array $where = [], string $order = null, int $offset = 0): array
    {
        $result = $this->findInTable($table, $where, $order, 1, $offset);

        return $result[0] ?? [];
    }

    public function findOne(array $where = [], string $order = null, int $offset = 0): array
    {
        return $this->findOneInTable($this->getTableName(), $where, $order, $offset);
    }

    public function countRows(array $where = [], $groupBy = null)
    {
        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder = $queryBuilder->select((null === $groupBy) ? 'COUNT(*)' : '1')->from($this->getTableName());
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $condition => $value) {
                if (is_array($value)) {
                    $conditions[] = str_replace('?', implode(',', array_map([$this->connection, 'quote'], $value)), $condition);
                } else {
                    $conditions[] = str_replace('?', $this->connection->quote($value), $condition);
                }
            }
            $queryBuilder = $queryBuilder->where(implode(' AND ', $conditions));
        }

        if (null !== $groupBy) {
            $queryBuilder->groupBy($groupBy);

            return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (' . $queryBuilder->getSQL() . ') t');
        }

        return (int) $this->connection->fetchOne($queryBuilder->getSQL());
    }

    public function create(array $data)
    {
        $parametersHash = md5($this->getTableName() . '-' . implode('-', array_keys($data)));
        if (!isset(self::$preparedStatements[$parametersHash])) {
            $sql = 'INSERT INTO ' . $this->getTableName() . ' (' . implode(', ', array_map([$this->connection, 'quoteIdentifier'], array_keys($data))) . ') VALUES (' . rtrim(str_repeat('?,', count($data)), ',') . ')';
            self::$preparedStatements[$parametersHash] = self::prepare($sql);
            self::$preparedStatements[$parametersHash . '-types'] = $this->getDataTypes($data);
        }

        foreach (array_values($data) as $key => $dataItem) {
            self::$preparedStatements[$parametersHash]->bindValue($key + 1, $dataItem, self::$preparedStatements[$parametersHash . '-types'][$key]);
        }

        $result = self::$preparedStatements[$parametersHash]->execute();
        if ($result instanceof Result || $result instanceof \Doctrine\DBAL\Driver\Statement) {
            $insertedRows = $result->rowCount();
        } else {
            $insertedRows = self::$preparedStatements[$parametersHash]->rowCount();
        }

        if ($insertedRows > 0) {
            $data['id'] = $this->connection->lastInsertId();

            return $data;
        }

        return false;
    }

    public function update($data, $where)
    {
        if (!is_array($where)) {
            $where = ['id' => $where];
        }

        $query = 'UPDATE ' . $this->getTableName() . ' SET ' . implode(',', array_map(function ($field) {
            return $this->connection->quoteIdentifier($field) . '=?';
        }, array_keys($data))) . '
            WHERE ' . implode(' AND ', array_map(function ($field) {
            return $this->connection->quoteIdentifier($field) . '=?';
        }, array_keys($where)));

        return $this->execute($query, array_merge(array_values($data), array_values($where)));
    }

    public function createOrUpdate(array $data, $table = null)
    {
        if ($data && !isset($data[0])) {
            $data = [$data];
        }

        $batches = [];
        // build batches with identical columns
        foreach ($data as $dataset) {
            $batches[implode('-', array_keys($dataset))][] = $dataset;
        }

        if (null === $table) {
            $table = $this->getTableName();
        }

        foreach ($batches as $batch) {
            $columnList = array_keys($batch[0]);

            $countColumnList = count($columnList);
            if (0 === $countColumnList) {
                continue;
            }

            $query = 'INSERT INTO ' . $table . ' (' . implode(',', array_map([$this->connection, 'quoteIdentifier'], $columnList)) . ') VALUES ';

            $paramValues = [];
            foreach ($batch as $dataset) {
                foreach ($dataset as $value) {
                    $paramValues[] = $value;
                }
            }

            $updateList = array_map(function ($column) {
                return $this->connection->quoteIdentifier($column) . '=VALUES(' . $this->connection->quoteIdentifier($column) . ')';
            }, $columnList);

            $query .= rtrim(str_repeat('(' . rtrim(\str_repeat('?,', $countColumnList), ',') . '),', count($batch)), ',') . ' ON DUPLICATE KEY UPDATE ' . implode(',', $updateList);

            $this->execute($query, $paramValues);
        }
    }

    public function delete($id)
    {
        if (!empty($id)) {
            return $this->deleteWhere(['id' => $id]);
        }

        return 0;
    }

    public function deleteWhere(array $where = [])
    {
        return $this->connection->delete($this->getTableName(), $where);
    }

    public function get($id): array
    {
        return $this->findOne(['id = ?' => (int) $id]);
    }

    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    public function setTransactionIsolation($level)
    {
        $this->connection->setTransactionIsolation($level);
    }

    public function commit()
    {
        $this->connection->commit();
    }

    public function close()
    {
        $this->connection->close();
    }

    public function rollback()
    {
        $this->connection->rollBack();
    }

    public function isTransactionActive()
    {
        return $this->connection->isTransactionActive();
    }

    public function getTransactionNestingLevel()
    {
        return $this->connection->getTransactionNestingLevel();
    }

    public function isTransactionMarkedForRollbackOnly()
    {
        try {
            return $this->connection->isRollbackOnly();
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public function getDataTypes(array $data): array
    {
        return array_values(
            array_map([$this, 'getDataType'], $data),
        );
    }

    private function getDataType($item)
    {
        if (is_string($item)) {
            return \PDO::PARAM_STR;
        }

        if (is_int($item)) {
            return \PDO::PARAM_INT;
        }

        if ($item instanceof \DateTimeInterface) {
            return 'datetime';
        }

        if (is_array($item)) {
            $isIntArray = true;
            foreach ($item as $arrayItem) {
                if (!is_int($arrayItem)) {
                    $isIntArray = false;

                    break;
                }
            }

            if ($isIntArray) {
                return Connection::PARAM_INT_ARRAY;
            }

            return Connection::PARAM_STR_ARRAY;
        }

        return \PDO::PARAM_STR;
    }

    public static function prepare($sql)
    {
        $parametersHash = md5($sql);

        if (!isset(self::$preparedStatements[$parametersHash])) {
            self::$preparedStatements[$parametersHash] = Db::get()->prepare($sql);
        }

        return self::$preparedStatements[$parametersHash];
    }

    public static function retry(callable $function, callable $rollbackFunction = null)
    {
        $maxRetries = 5;
        for ($retries = 0; $retries < $maxRetries; ++$retries) {
            try {
                self::getInstance()->beginTransaction();
                $result = $function();

                try {
                    self::getInstance()->commit();
                } catch (\Throwable $e) {
                    // implicit commit happened in meantime
                }

                return $result;
            } catch (\Throwable $e) {
                try {
                    self::getInstance()->rollback();
                } catch (\Throwable $rollbackException) {
                }

                // we try to start the transaction $maxRetries times again (deadlocks, ...)
                if (($e instanceof RetryableException || (($e instanceof PDOException || $e instanceof DBALException) && 1205 == $e->getCode())) && $retries < $maxRetries - 1) {
                    if (is_callable($rollbackFunction)) {
                        $rollbackFunction();
                    }

                    $waitTime = random_int(1, 5) * 100000; // microseconds

                    usleep($waitTime); // wait specified time until we restart the transaction

                    Logger::debug('Restarting transaction');
                } else {
                    throw $e;
                }
            }
        }
    }

    public function insertOrUpdate($table, array $data)
    {
        $bind = [];
        $cols = [];
        foreach ($data as $col => $val) {
            $cols[] = $this->connection->quoteIdentifier($col);
            $bind[] = $val;
        }

        $set = [];
        foreach ($cols as $col) {
            $set[] = $col . ' = ?';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $this->connection->quoteIdentifier($table),
            implode(', ', $cols),
            rtrim(str_repeat('?,', count($cols)), ','),
            implode(', ', $set),
        );

        $bind = array_merge($bind, $bind);

        return $this->executeSql($sql, $bind);
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public static function clearPreparedStatements()
    {
        self::$preparedStatements = [];
    }
}
