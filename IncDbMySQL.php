<?php
// -----------------------------------------------------------------------------
// Two objects to access database via MySQL; works under any system (Windows, Linux, ...)
// You must create MySQL data source to the database first to use its name as parameter in Database constructor
// Uses mysqli_ functions that are not in PHP4, for PHP4 use IncDbMySQLPHP4.php instead
// -----------------------------------------------------------------------------
// Recordset created by Database::Execute SQL SELECT command; used to read-only access to returned table


class Recordset implements RecordsetInteface
{
   private $rs, $row;

   function __construct($par)
   {
      $this->rs = $par;
      $this->First();
   } // Do not call constructor directly 
   function __destruct()
   {
   }
   function GetRowCount()
   {
      return mysqli_num_rows($this->rs);
   } // Returns number of records, can return -1 if no rows found
   function GetColCount()
   {
      return mysqli_num_fields($this->rs);
   } // Returns number columns
   function First()
   {
      $this->row = mysqli_data_seek($this->rs, 0) ? mysqli_fetch_array($this->rs) : NULL;
      return !!$this->row;
   } // Moves to first record, returns true on success, false on BOF
   function Next()
   {
      $this->row = mysqli_fetch_array($this->rs);
      return !!$this->row;
   }  // Moves to next record, returns true on success, false on EOF
   function Get($col)
   {
      return $this->row ? $this->row[$col] : NULL;
   }  // Returns value of the column value in actual row, column can be column name or index (from 0); if column does not exist, returns NULL
   function GetRow()
   {
      return $this->row;
   }  // Returns array of actual row's values 
   function GetRows()
   {
      $arr = [];
      for ($i = 0, mysqli_data_seek($this->rs, 0); $this->Next(); $i++) $arr[$i] = $this->row;
      return $arr;
   }                // Returns array of all rows in recordset
}

class Database implements DatabaseQueryInterface
{
   private $conn;

   function __construct($name, $user = "", $pass = "", $server = "localhost:3306")
   {
      $this->conn = mysqli_connect($server, $user, $pass);
      if (!$this->conn) exit();
      mysqli_query($this->conn, "SET NAMES 'utf8'");
      mysqli_select_db($this->conn, $name) or die("Error while selecting database $name: " . mysqli_errno($this->conn) . " - " . mysqli_error($this->conn));
   }

   function Exec($cmd)
   {
      mysqli_query($this->conn, $cmd);
      return mysqli_affected_rows($this->conn);
   }  // Executes any SQL command other then SELECT; returns number of rows affected

   function Query($cmd)
   {
      $rs = mysqli_query($this->conn, $cmd);

      return $rs ? new Recordset($rs) : NULL;
   }  // Executes SELECT command and returns opened recordset or NULL for other commands

    function __destruct()
    {
        if ($this->conn) mysqli_close($this->conn);
    }
}
// -----------------------------------------------------------------------------

interface DatabaseQueryInterface
{
    /**
     * @param string $query
     */
    public function exec($query);

    /**
     * @param string $query
     */
    public function query($query);

    /**
     * @return void
     */
    function __destruct();
}

interface RecordsetInteface
{
    function GetRowCount();

    function GetColCount();

    function First();

    function Next();

    function Get($col);

    function GetRow();

    function GetRows();
}

final class CustomPdo implements DatabaseQueryInterface
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    const DB = 'mysql';

    /**
     * CustomPdo constructor.
     *
     * @param $db_name
     * @param string $user
     * @param string $pass
     * @param string $server
     */
    public function __construct($db_name, $user = "", $pass = "", $server = "localhost")
    {
        try {
            $this->pdo = new PDO(
                $this->getPdoDsn(self::DB, $server, $db_name),
                $user,
                $pass
            );

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false);

        } catch (PDOException $exception) {
            die($exception->getMessage());
        }
    }

    /**
     * @param $db_name
     * @param string $user
     * @param string $pass
     * @param string $server
     * @return CustomPdo
     */
    public static function make($db_name, $user = "", $pass = "", $server = "localhost"): CustomPdo
    {
        return new static($db_name, $user, $pass, $server);
    }

    /**
     * @param string $database
     * @param string $host
     * @param string $db_name
     * @return string
     */
    private function getPdoDsn($database, $host, $db_name)
    {
        return "$database:host=$host;dbname=$db_name";
    }

    /**
     * @param string $query
     * @return false|int
     */
    public function exec($query)
    {
        $result = $this->pdo->exec($query);

        return $result;
    }

    /**
     * @param string $query
     * @return false|PDOStatement
     */
    public function query($query)
    {
        $result = $this->pdo->query($query);

        return $result;
    }

    /**
     * @param string $query
     * @param array $data
     * @param array $driver_options
     * @return PDORecordset|null
     */
    public function prepareAndExec(string $query, array $data, array $driver_options = [])
    {
        if (empty($driver_options)) {
            $driver_options = [
                PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL
            ];
        }

        $statement = $this->pdo->prepare($query, $driver_options);
        $statement->setFetchMode(PDO::FETCH_BOTH);

        $statement->execute($data);

        return $statement ? new PDORecordset($statement) : null;
    }

    /**
     * @param string $query
     * @param array $driver_options
     * @return bool|PDOStatement
     */
    public function prepare(string $query, array $driver_options = [])
    {
        if (empty($driver_options)) {
            $driver_options = [
                PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL
            ];
        }

        $statement = $this->pdo->prepare($query, $driver_options);
        $statement->setFetchMode(PDO::FETCH_BOTH);

        return $statement;
    }

    /**
     * @param PDOStatement $statement
     * @param array $params
     * @return PDORecordset|null
     */
    public function execute(PDOStatement $statement, array $params)
    {
        $statement->execute($params);

        return $statement ? new PDORecordset($statement) : null;
    }

    /**
     * @return void
     */
    function __destruct()
    {
        if (isset($this->connection)) {
            $this->connection = null;
        }
    }
}

/**
 * I'm not sure that this class was needed but
 * I decided not to change interface of this project and created new Recordset class for PDO.
 * PDO is not support features like mysqli_seek and etc. that's why I changed a bit of logic in realization
 * This class works with array which was fetched from PDOStatement
 *
 * Class PDORecordset
 */
class PDORecordset implements RecordsetInteface
{
    /**
     * @var PDOStatement
     */
    private PDOStatement $record;

    /**
     * @var array
     */
    private array $rows;

    /**
     * @var array
     */
    private array $row;

    /**
     * @var int
     */
    private int $index;

    /**
     * PDORecordset constructor.
     *
     * @param PDOStatement $record
     */
    public function __construct(PDOStatement $record)
    {
        $this->record = $record;
        $this->rows = $this->record->fetchAll();

        if (!empty($this->rows)) {
            $this->First();
        }
    }

    /**
     * @return int
     */
    function GetRowCount()
    {
        return $this->record->rowCount();
    }

    /**
     * @return int
     */
    function GetColCount()
    {
        return $this->record->columnCount();
    }

    /**
     * @return bool
     */
    function First(): bool
    {
        $this->index = 0;
        $this->row = $this->rows[0];

        return !!$this->row;
    }

    /**
     * Switch to next object
     *
     * @return bool
     */
    function Next()
    {
        $this->row = $this->rows[$this->index + 1];
        $this->index++;

        return !!$this->row;
    }

    /**
     * Get value from specified columns of current row
     *
     * @param $col
     * @return mixed
     */
    function Get($col)
    {
        return isset($this->row) ? $this->row[$col] : null;
    }

    /**
     * @return array
     */
    function GetRow()
    {
        return $this->row;
    }

    /**
     * @return array
     */
    function GetRows()
    {
        return $this->rows;
    }
}
