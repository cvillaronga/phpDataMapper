<?php
/**
 * $Id$
 * 
 * DataMapper abstract class for relations
 * 
 * @package phpDataMapper
 * @author Vance Lucas <vance@vancelucas.com>
 * @link http://phpdatamapper.com
 * 
 * @version			$Revision$
 * @modifiedby		$LastChangedBy$
 * @lastmodified	$Date$
 */
abstract class phpDataMapper_Model_Relation
{
	protected $mapper;
	protected $foreignKeys;
	protected $relationData;
	protected $relationRows = array();
	protected $relationRowCount;
	
	
	/**
	 * Constructor function
	 *
	 * @param object $mapper DataMapper object to query on for relationship data
	 * @param array $resultsIdentities Array of key values for given result set primary key
	 */
	public function __construct(phpDataMapper_Model $mapper, array $foreignKeys, array $relationData)
	{
		$this->mapper = $mapper;
		$this->foreignKeys = $foreignKeys;
		$this->relationData = $relationData;
	}
	
	
	/**
	 * Get related DataMapper object
	 */
	public function getMapper()
	{
		return $this->mapper;
	}
	
	
	/**
	 * Get foreign key relations
	 *
	 * @return array
	 */
	public function getForeignKeys()
	{
		return $this->foreignKeys;
	}
	
	
	/**
	 * Called automatically when attribute is printed
	 */
	public function __toString()
	{
		// Load related records for current row
		$success = $this->findAllRelation();
		return ($success) ? "1" : "0";
	}
	
	
	
	/**
	 * Select all related records
	 */
	abstract public function all();
	
	
	/**
	 * Internal function, caches fetched related rows from all() function call
	 */
	protected function findAllRelation()
	{
		if(!$this->relationRows) {
			$this->relationRows = $this->all();
		}
		return $this->relationRows;
	}
}