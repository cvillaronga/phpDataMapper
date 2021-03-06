<?php
/**
 * $Id$
 * 
 * Base DataMapper Model
 * 
 * @package phpDataMapper
 * @author Vance Lucas <vance@vancelucas.com>
 * @link http://phpdatamapper.com
 * 
 * @version			$Revision$
 * @modifiedby		$LastChangedBy$
 * @lastmodified	$Date$
 */
require('Exception.php');
class phpDataMapper_Model implements Countable, IteratorAggregate
{
	protected $adapter;
	
	// Class Names for required classes
	protected $rowClass = 'phpDataMapper_Model_Row';
	protected $resultSetClass = 'phpDataMapper_Model_ResultSet';
	protected $exceptionClass = 'phpDataMapper_Exception';
	protected $validationClass = 'phpDataMapper_Validation';
	
	protected $table;
	protected $fields = array();
	protected $primaryKey;
	/**
	 // EXAMPLE 'fields' definition: 
	protected $fields = array(
		'id' => array('type' => 'int', 'primary' => true)
		);
	*/
	protected $relations = array();
	/**
	 // Relationship associations
	    protected $relations = array(
	        // Comments
	        'comments' => array(
	            'relation' => 'HasMany',
	            'mapper' => 'CommentsModel',
	            'foreign_keys' => array('parent' => 'module_id', 'id' => 'module_item_id'),
				)
			);
	*/
	
	// Current active query
	protected $activeQuery;
	protected $activeQueryResults;
	
	// Class loader instance and action name
	protected $loader;
	protected $loaderAction;
	
	// Array of error messages and types
	protected $errors = array();
	
	// Array of all queries that have been executed for any DataMapper (static)
	protected static $queryLog = array();
	
	
	/**
	 *	Constructor Method
	 */
	public function __construct(phpDataMapper_Database_Adapter_Interface $adapter)
	{
		$this->adapter = $adapter;
		
		// Ensure table has been defined
		if(!$this->table) {
			throw new $this->exceptionClass("Error: Table name must be defined - please define \$table variable.");
		}
		
		// Ensure fields have been defined for current table
		if(!$this->fields || !is_array($this->fields)) {
			throw new $this->exceptionClass("Error: Fields for current table must be defined");
		}
		
		// Find and store primary key field
		foreach($this->fields as $field => $options) {
			if(array_key_exists('primary', $options)) {
				$this->primaryKey = $field;
			}
		}
		
		// Register loadClass() function as an autoloader
		spl_autoload_register(array($this, 'loadClass'));
	}
	
	
	/**
	 * SPL Countable function
	 * Called automatically when attribute is used in a 'count()' function call
	 *
	 * @return int
	 */
	public function count()
	{
		// Execute query and return count
		$result = $this->execute();
		return ($result !== false) ? count($result) : 0;
	}
	
	
	/**
	 * SPL IteratorAggregate function
	 * Called automatically when attribute is used in a 'foreach' loop
	 *
	 * @return phpDataMapper_Model_ResultSet
	 */
	public function getIterator()
	{
		// Execute query and return ResultSet for iteration
		$result = $this->execute();
		return ($result !== false) ? $result : array();
	}
	
	
	/**
	 * Convenience function passthrough for ResultSet
	 * Triggers execute() and empties current active query
	 *
	 * @return array 
	 */
	public function toArray($keyColumn, $valueColumn)
	{
		// Execute query and call the 'toArray' function on the ResultSet
		$result = $this->execute();
		return ($result !== false) ? $result->toArray($keyColumn, $valueColumn) : array();
	}
	
	
	/**
	 * Execute and return current active query result set
	 * @param boolean $clearActiveQuery Clears current active query content if true
	 */
	public function execute()
	{
		// Use cached results if found (previous count() or other internal call)
		if($this->activeQueryResults) {
			$results = $this->activeQueryResults;
		} else {
			if($this->activeQuery instanceof phpDataMapper_Database_Query_Interface) {
				$results = $this->query($this->activeQuery->sql(), $this->activeQuery->getParameters());
				$this->activeQueryResults = $results;
			} else {
				$results = array();
			}
		}
		
		return $results;
	}
	
	
	/**
	 * Clears current active query content to begin a new query
	 */
	public function clearActiveQuery()
	{
		$this->activeQuery = null;
		$this->activeQueryResults = null;
		return true;
	}
	
	
	/**
	 * Get current adapter object
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}
	
	
	/**
	 * Get value of primary key for given row result
	 */
	public function getTable()
	{
		return $this->table;
	}
	
	
	/**
	 * Get value of primary key for given row result
	 */
	public function getPrimaryKey(phpDataMapper_Model_Row $row)
	{
		$pkField = $this->getPrimaryKeyField();
		return $row->$pkField;
	}
	
	
	/**
	 * Get value of primary key for given row result
	 */
	public function getPrimaryKeyField()
	{
		return $this->primaryKey;
	}
	
	
	/**
	 * Check if field exists in defined fields
	 */
	public function fieldExists($field)
	{
		return array_key_exists($field, $this->fields);
	}
	
	
	/**
	 * Load record from primary key
	 */
	public function get($primaryKeyValue = 0)
	{
		// Create new row object
		if(!$primaryKeyValue) {
			$row = new $this->rowClass();
			return $row;
		
		// Find record by primary key
		} else {
			return $this->first(array($this->getPrimaryKeyField() => $primaryKeyValue));
		}
	}
	
	
	/**
	 * Load defined relations 
	 */
	public function getRelationsFor(phpDataMapper_Model_Row $row)
	{
		$relatedColumns = array();
		if(is_array($this->relations) && count($this->relations) > 0) {
			foreach($this->relations as $column => $relation) {
				$mapperName = $relation['mapper'];
				// Ensure related mapper can be loaded
				if($loaded = $this->loadClass($mapperName)) {
					// Load foreign keys with data from current row
					$foreignKeys = array_flip($relation['foreign_keys']);
					foreach($foreignKeys as $relationCol => $col) {
						$foreignKeys[$relationCol] = $row->$col;
					}
					
					// Create new instance of mapper
					$mapper = new $mapperName($this->adapter);
					
					// Load relation class
					$relationClass = 'phpDataMapper_Model_Relation_' . $relation['relation'];
					if($loadedRel = $this->loadClass($relationClass)) {
						// Set column equal to relation class instance
						$relationObj = new $relationClass($mapper, $foreignKeys, $relation);
						$relatedColumns[$column] = $relationObj;
					}
				}
			}
		}
		return (count($relatedColumns) > 0) ? $relatedColumns : false;
	}
	
	
	/**
	 * Get result set for given PDO Statement
	 */
	public function getResultSet($stmt)
	{
		if($stmt instanceof PDOStatement) {
			$results = array();
			$resultsIdentities = array();
			
			// Set object to fetch results into
			$stmt->setFetchMode(PDO::FETCH_CLASS, $this->rowClass, array());
			
			// Fetch all results into new DataMapper_Result class
			while($row = $stmt->fetch(PDO::FETCH_CLASS)) {
				
				// Load relations for this row
				$relations = $this->getRelationsFor($row);
				if($relations && is_array($relations) && count($relations) > 0) {
					foreach($relations as $relationCol => $relationObj) {
						$row->$relationCol = $relationObj;
					}
				}
				
				// Store in array for ResultSet
				$results[] = $row;
				
				// Store primary key of each unique record in set
				$pk = $this->getPrimaryKey($row);
				if(!in_array($pk, $resultsIdentities) && !empty($pk)) {
					$resultsIdentities[] = $pk;
				}
				
				// Mark row as loaded
				$row->loaded(true);
			}
			// Ensure set is closed
			$stmt->closeCursor();
			
			return new $this->resultSetClass($results, $resultsIdentities);
			
		} else {
			return array();
			//throw new $this->exceptionClass(__METHOD__ . " expected PDOStatement object");
		}
	}
	
	
	/**
	 * Find records with given conditions
	 * If all parameters are empty, find all records
	 *
	 * @param array $conditions Array of conditions in column => value pairs
	 * @param array $orderBy Array of ORDER BY columns/values
	 * @param array $clauses Array of clauses/conditions - limit, etc.
	 * 
	 * @todo Implement extra $clauses array
	 */
	public function all(array $conditions = array(), array $orderBy = array(), array $clauses = array())
	{
		// Clear previous active query if found
		if($this->activeQueryResults) {
			$results = $this->clearActiveQuery();
		}
		
		// Build on active query if it has not been executed yet
		if($this->activeQuery instanceof phpDataMapper_Database_Query_Interface) {
			$this->activeQuery->where($conditions)->orderBy($orderBy);
		} else {
			// New active query
			$this->activeQuery = $this->select()->where($conditions)->orderBy($orderBy);
		}
		return $this;
	}
	
	
	/**
	 * Find first record matching given conditions
	 *
	 * @param array $conditions Array of conditions in column => value pairs
	 * @param array $orderBy Array of ORDER BY columns/values
	 */
	public function first(array $conditions = array(), array $orderBy = array())
	{
		$query = $this->select()->where($conditions)->orderBy($orderBy)->limit(1);
		$rows = $this->query($query->sql(), $query->getParameters());
		if($rows) {
			return $rows->first();
		} else {
			return false;
		}
	}
	
	
	/**
	 * Find records with custom SQL query
	 *
	 * @param string $sql SQL query to execute
	 * @param array $binds Array of bound parameters to use as values for query
	 * @throws phpDataMapper_Exception
	 */
	public function query($sql, array $binds = array())
	{
		// Add query to log
		$this->logQuery($sql, $binds);
		
		// Prepare and execute query
		if($stmt = $this->adapter->prepare($sql)) {
			$results = $stmt->execute($binds);
			if($results) {
				$r = $this->getResultSet($stmt);
			} else {
				$r = false;
			}
			
			return $r;
		} else {
			throw new $this->exceptionClass(__METHOD__ . " Error: Unable to execute SQL query - failed to create prepared statement from given SQL");
		}
		
	}
	
	
	/**
	 * Begin a new database query - get query builder
	 * Acts as a kind of factory to get the current adapter's query builder object
	 * 
	 * @param mixed $fields String for single field or array of fields
	 */
	public function select($fields = "*")
	{
		$adapterName = get_class($this->adapter);
		$adapterClass = $adapterName . "_Query";
		if($this->loadClass($adapterClass)) {
			return new $adapterClass($fields, $this->table);
		} else {
			throw new $this->exceptionClass(__METHOD__ . " Error: Unable to load new query builder for adapter: '" . $adapterName . "'");
		}
	}
	
	
	/**
	 * Limit executed query to specified amount of rows
	 * Implemented at adapter-level for databases that support it
	 * 
	 * @param int $limit Number of records to return
	 * @param int $offset Row to start at for limited result set
	 *
	 * @todo Implement limit functionality for database adapters that do not support any kind of LIMIT clause
	 */
	public function limit($limit = 20, $offset = null)
	{
		if($this->activeQuery instanceof phpDataMapper_Database_Query_Interface) {
			$this->activeQuery->limit($limit, $offset);
		}
		return $this;
	}
	
	
	/**
	 * Builds an SQL string given conditions
	 */
	protected function getSqlFromConditions($sql, array $conditions)
	{
		$sqlWhere = array();
		$defaultColOperators = array(0 => '', 1 => '=');
		$ci = 0;
		foreach($conditions as $column => $value) {
			// Column name with comparison operator
			$colData = explode(' ', $column);
			$col = $colData[0];
			
			// Array of values, assume IN clause
			if(is_array($value)) {
				$sqlWhere[] = $col . " IN('" . implode("', '", $value) . "')";
			
			// NULL value
			} elseif(is_null($value)) {
				$sqlWhere[] = $col . " IS NULL";
			
			// Standard string value
			} else {
				$colComparison = isset($colData[1]) ? $colData[1] : '=';
				$columnSql = $col . ' ' . $colComparison;
				
				// Add to binds array and add to WHERE clause
				$colParam = str_replace('.', '_', $col) . $ci;
				$sqlWhere[] = $columnSql . " :" . $colParam . "";
			}
			
			// Increment ensures column name distinction
			$ci++;
		}
		
		$sql .= empty($sqlWhere) ? "" : " WHERE " . implode(' AND ', $sqlWhere);
		return $sql;
	}
	
	
	/**
	 * Returns array of binds to pass to query function
	 */
	protected function getBindsFromConditions(array $conditions)
	{
		$binds = array();
		$ci = 0;
		foreach($conditions as $column => $value) {
			// Can't bind array of values
			if(!is_array($value)) {
				// Column name with comparison operator
				list($col) = explode(' ', $column);
				$colParam = str_replace('.', '_', $col) . $ci;
				
				// Add to binds array and add to WHERE clause
				$binds[$colParam] = $value;
			}
			
			// Increment ensures column name distinction
			$ci++;
		}
		return $binds;
	}
	
	
	/**
	 * Save related rows of data
	 */
	protected function saveRelatedRowsFor($row, array $fillData = array())
	{
		$relationColumns = $this->getRelationsFor($row);
		foreach($row->getData() as $field => $value) {
			if($relationColumns && array_key_exists($field, $relationColumns) && (is_array($value) || is_object($value))) {
				foreach($value as $relatedRow) {
					// Determine relation object
					if($value instanceof phpDataMapper_Model_Relation) {
						$relatedObj = $value;
					} else {
						$relatedObj = $relationColumns[$field];
					}
					$relatedMapper = $relatedObj->getMapper();
					
					// Row object
					if($relatedRow instanceof phpDataMapper_Model_Row) {
						$relatedRowObj = $relatedRow;
						
					// Associative array
					} elseif(is_array($relatedRow)) {
						$relatedRowObj = new $this->rowClass($relatedRow);
					}
					
					// Set column values on row only if other data has been updated (prevents queries for unchanged existing rows)
					if(count($relatedRowObj->getDataModified()) > 0) {
						$fillData = array_merge($relatedObj->getForeignKeys(), $fillData);
						$relatedRowObj->setData($fillData);
					}
					
					// Save related row
					$relatedMapper->save($relatedRowObj);
				}
			}
		}
	}
	
	
	/**
	 * Save result object
	 */
	public function save(phpDataMapper_Model_Row $row)
	{
		// Run validation
		if($this->validate($row)) {
			$pk = $this->getPrimaryKey($row);
			// No primary key, insert
			if(empty($pk)) {
				$result = $this->insert($row);
			// Has primary key, update
			} else {
				$result = $this->update($row);
			}
		} else {
			$result = false;
		}
		
		return $result;
	}
	
	
	/**
	 * Insert given row object with set properties
	 */
	public function insert(phpDataMapper_Model_Row $row)
	{
		$rowData = $row->getData();
		
		// Fields that exist in the table
		$tableFields = array_keys($this->fields);
		
		// Fields that have been set/updated on the row that also exist in the table
		$rowFields = array_intersect($tableFields, array_keys($rowData));
		
		// Get "col = :col" pairs for the update query
		$insertFields = array();
		$binds = array();
		foreach($rowData as $field => $value) {
			if($this->fieldExists($field)) {
				$insertFields[] = $field;
				// Empty values will be NULL (easier to be handled by databases)
				$binds[$field] = $this->isEmpty($value) ? null : $value;
			}
		}
		
		// Ensure there are actually values for fields on THIS table
		if(count($insertFields) > 0) {
			// build the statement
			$sql = "INSERT INTO " . $this->getTable() .
				" (" . implode(', ', $rowFields) . ")" .
				" VALUES(:" . implode(', :', $rowFields) . ")";
			
			// Add query to log
			$this->logQuery($sql, $binds);
			
			// Prepare update query
			$stmt = $this->adapter->prepare($sql);
			
			if($stmt) {
				// Bind values to columns
				$this->bindValues($stmt, $binds);
				
				// Execute
				if($stmt->execute()) {
					$rowPk = $this->adapter->lastInsertId();
					$pkField = $this->getPrimaryKeyField();
					$row->$pkField = $rowPk;
					$result = $rowPk;
				} else {
					$result = false;
				}
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}
		
		// Save related rows
		if($result) {
			$this->saveRelatedRowsFor($row);
		}
		
		return $result;
	}
	
	
	/**
	 * Update given row object
	 */
	public function update(phpDataMapper_Model_Row $row)
	{
		// Get "col = :col" pairs for the update query
		$placeholders = array();
		$binds = array();
		foreach($row->getDataModified() as $field => $value) {
			if($this->fieldExists($field)) {
				$placeholders[] = $field . " = :" . $field . "";
				// Empty values will be NULL (easier to be handled by databases)
				$binds[$field] = $this->isEmpty($value) ? null : $value;
			}
		}
		
		// Ensure there are actually updated values on THIS table
		if(count($placeholders) > 0) {
			// Build the query
			$sql = "UPDATE " . $this->getTable() .
				" SET " . implode(', ', $placeholders) .
				" WHERE " . $this->getPrimaryKeyField() . " = '" . $this->getPrimaryKey($row) . "'";
			
			// Add query to log
			$this->logQuery($sql, $binds);
			
			// Prepare update query
			$stmt = $this->adapter->prepare($sql);
			
			// Bind column values
			$this->bindValues($stmt, $binds);
			
			if($stmt) {
				// Execute
				if($stmt->execute($binds)) {
					$result = $this->getPrimaryKey($row);
				} else {
					$result = false;
				}
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}
		
		// Save related rows
		if($result) {
			$this->saveRelatedRowsFor($row);
		}
		
		return $result;
	}
	
	
	/**
	 * Destroy/Delete given row object
	 */
	public function destroy(phpDataMapper_Model_Row $row)
	{
		$where = $this->getPrimaryKeyField() . " = '" . $this->getPrimaryKey($row) . "'";
		$sql = "DELETE FROM " . $this->table . " WHERE " . $where;
		
		// Add query to log
		$this->logQuery($sql);
		
		$this->adapter->exec($sql);
		return true;
	}
	
	
	/**
	 * Delete rows matching given conditions
	 *
	 * @param array $conditions Array of conditions in column => value pairs
	 */
	public function delete(array $conditions)
	{
		$sql = "DELETE FROM " . $this->table . "";
		$sql = $this->getSqlFromConditions($sql, $conditions);
		$result = $this->query($sql, $this->getBindsFromConditions($conditions));
		return true;
	}
	
	
	/**
	 * Run set validation rules on fields
	 * 
	 * @todo A LOT more to do here... More validation, break up into classes with rules, etc.
	 */
	public function validate(phpDataMapper_Model_Row $row)
	{
		// Check validation rules on each feild
		foreach($this->fields as $field => $fieldAttrs) {
			if(isset($fieldAttrs['required']) && true === $fieldAttrs['required']) {
				// Required field
				if(empty($row->$field)) {
					$this->addError("Required field '" . $field . "' was left blank");
				}
			}
		}
		
		// Check for errors
		if($this->hasErrors()) {
			return false;
		} else {
			return true;
		}
	}
	
	
	/**
	 * Migrate table structure changes from model to database
	 */
	public function migrate()
	{
		return $this->getAdapter()->migrate($this->getTable(), $this->fields);
	}
	
	
	/**
	 * Check if a value is empty, excluding 0 (annoying PHP issue)
	 *
	 * @param mixed $value
	 * @return boolean
	 */
	public function isEmpty($value)
	{
		return (empty($value) && 0 !== $value);
	}
	
	
	/**
	 * Check if any errors exist
	 * 
	 * @return boolean
	 */
	public function hasErrors()
	{
		return count($this->errors);
	}
	
	
	/**
	 *	Get array of error messages
	 *
	 * @return array
	 */
	public function getErrors()
	{
		return $this->errors;
	}
	
	
	/**
	 *	Add an error to error messages array
	 */
	public function addError($msg)
	{
		// Add to error array
		$this->errors[] = $msg;
	}
	
	
	/**
	 *	Add an array of errors all at once
	 */
	public function addErrors(array $msgs)
	{
		foreach($msgs as $msg) {
			$this->addError($msg);
		}
	}
	
	
	/**
	 * Shortcut function to get current adapter's FORMAT_DATE
	 * Should return date only
	 */
	public function getDateFormat()
	{
		return $this->adapter->getDateFormat();
	}
	
	
	/**
	 * Shortcut function to get current adapter's FORMAT_DATETIME
	 * Should return full date and time
	 */
	public function getDateTimeFormat()
	{
		return $this->adapter->getDateTimeFormat();
	}
	
	
	/**
	 * Attempt to load class file
	 */
	public function loadClass($className)
	{
		$loaded = false;
		
		// If class has already been defined and it's a subclass of phpDataMapper, skip loading
		if(class_exists($className, false) && is_subclass_of($className, "phpDataMapper_Model")) {
			$loaded = true;
		} else {
		
			// Call specified loader function
			if($this->loader) {
				$loaded = call_user_func_array(array($this->loader, $this->loaderAction), array($className));
			
			// Require phpDataMapper_* files by assumed folder structure (naming convention)
			} elseif(strpos($className, "phpDataMapper") !== false) {
				$classFile = str_replace("_", "/", $className);
				$loaded = require_once(dirname(dirname(__FILE__)) . "/" . $classFile . ".php");
			}
		}
		
		// Ensure required class was loaded
		if(!$loaded) {
			throw new $this->exceptionClass(__METHOD__ . " Failed: Unable to load class '" . $className . "'!");
		}
		
		return $loaded;
	}
	
	
	/**
	 * Set 'loader' class to load external files with
	 */
	public function setLoader($instance, $action)
	{
		$this->loader = $instance;
		$this->loaderAction = $action;
	}
	
	
	/**
	 * Bind array of field/value data to given statement
	 *
	 * @param PDOStatement $stmt
	 * @param array $binds
	 */
	protected function bindValues($stmt, array $binds)
	{
		// Bind each value to the given prepared statement
		foreach($binds as $field => $value) {
			$stmt->bindValue($field, $value);
		}
		return true;
	}
	
	
	/**
	 * Prints all executed SQL queries - useful for debugging
	 */
	public function debug($row = null)
	{
		if($row) {
			// Dump debugging info for current row
		}
		
		echo "<p>Executed " . $this->getQueryCount() . " queries:</p>";
		echo "<pre>\n";
		print_r(self::$queryLog);
		echo "</pre>\n";
	}
	
	
	/**
	 * Log query
	 *
	 * @param string $sql
	 * @param array $data
	 */
	public function logQuery($sql, $data = null)
	{
		self::$queryLog[] = array(
			'query' => $sql,
			'data' => $data
			);
	}
	
	
	/**
	 * Get count of all queries that have been executed
	 * 
	 * @return int
	 */
	public function getQueryCount()
	{
		return count(self::$queryLog);
	}
}