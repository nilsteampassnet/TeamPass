<?php
/**
 * An extension of the PDO class that allows for one-line prepared statements
 * Used for reference
 */
class DB extends PDO
{
	/**
	 * Prepared statement
	 * 
	 * @param string $statement
	 * @param array $params
	 * @return mixed -- array if SELECT, 
	 */
	public function pQuery($statement, $params = array(), $fetch_style = PDO::FETCH_ASSOC)
	{
		$stmt = $this->prepare($statement);
		$exec = $stmt->execute($params);
		if ($exec) {
			return $stmt->fetchAll($fetch_style);
		} else {
			return FALSE;
		}
	}
}