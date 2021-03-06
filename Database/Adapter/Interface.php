<?php
/**
 * $Id$
 * 
 * @package phpDataMapper
 * @author Vance Lucas <vance@vancelucas.com>
 * @link http://phpdatamapper.com
 */
interface phpDataMapper_Database_Adapter_Interface
{
    /**
    * @param string $host
    * @param string $username
    * @param string $password
	* @param string $database
    * @param array $options
    * @return void
    */
    public function __construct($host, $database, $username, $password = NULL, array $options = array());
	
	
	/**
	 *	Get database connection
	 */
	public function getConnection();
	
	
	/**
	 *	Get DSN string for PDO to connect with
	 */
	public function getDsn();
	
	
	/**
	 *	Get database DATE format
	 */
	public function getDateFormat();
	
	
	/**
	 *	Get database full DATETIME
	 */
	public function getDateTimeFormat();
	
	
	/**
	 * Escape/quote direct user input
	 *
	 * @param string $string
	 */
	public function escape($string);
}