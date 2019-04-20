<?php

/**
 *  Query Builder abstraction for the Official MongoDb PHP Library.
 *  https://docs.mongodb.com/php-library/current/tutorial/install-php-library/
 */
class QueryBuilder {

	private $client     = NULL;
	private $database   = NULL;
	private $collection = NULL;
	private $filters    = [];
	private $projection = [];
	private $sort 		= NULL;
	private $direction  = 1;
	private $limit      = NULL;
	private $skip 	    = NULL;

// --------------------------------------------------------------------------------
// PRIVATE METHODS - UTILITIES
// --------------------------------------------------------------------------------

	/**
	 * Resets the query builder properties to their default state.
	 * @return void
	 */
	private function resetProps(){
		$this->collection = NULL;
		$this->filters    = [];
		$this->projection = [];
		$this->sort 	  = NULL;
		$this->direction  = 1;
		$this->limit      = NULL;
		$this->skip 	  = NULL;
	}

	/**
	 * Parses and returns MongoDb query options.
	 * @return array
	 */
	private function getOptions(){
		$options = [];
		if(count($this->projection) > 0){
			foreach($this->projection as $column){
				$options['projection'][$column] = 1;
			}
		}
		if($this->sort != NULL){
			$options['sort'][$this->sort] = $this->direction;
		}
		if($this->skip != NULL){
			$options['skip'] = $this->skip;
		}
		if($this->limit != NULL){
			$options['limit'] = $this->limit;
		}
		return $options;
	}

	private function parseResult($data){
		return $this->parseResultOIDs($this->parseObject($data));
	}

	private function parseObject($data) {
		if(is_array($data) || is_object($data)){
	        $result = [];
	        foreach($data as $key => $value){
	        	$result[$key] = $this->parseObject($value);
	        }
	        return $result;
	    }
	    return $data;
	}

	private function parseResultOIDs($data){
		for($i=0; $i<count($data); $i++){
			$data[$i]['_id'] = $data[$i]['_id']['oid'];
		}
		return $data;
	}

	private function parseReturnOIDs($data){
		$ids = [];
		for($i=0; $i<count($data); $i++){
			$ids[] = (string) $data[$i];
		}
		return $ids;
	}

// --------------------------------------------------------------------------------
// PUBLIC METHODS - CHAINABLE QUERY BUILDING
// --------------------------------------------------------------------------------

	/**
	 * Constructor sets the MongoDb database name.
	 * @param   string $database MongoDb database name.
	 * @return  object
	 */
	public function __construct(string $database){
		$this->client   = new MongoDB\Client;
		$this->database = $database;
		return $this;
	}

	/**
	 * Sets the MongoDb collection.
	 * @param   string $collection MongoDb collection name.
	 * @return  object
	 */
	public function collection(string $collection){
		$this->collection = $collection;
		return $this;		
	}

	/**
	 * Sets the MongoDb query projection columns.
	 * @param   array $projection Array of collection column names.
	 * @return  object
	 */
	public function projection(array $projection){
		$this->projection = $projection;
		return $this;		
	}

	/**
	 * Sets MongoDb query filters.
	 * @param   string  $key Name of collection column to compare.
	 * @param   string  $comparator (EQ, STARTS, ENDS, CONTAINS).
	 * @param   string  $value Comparison value.
	 * @return  object
	 * @todo    Improve support for more filters and comparators.
	 */
	public function filter(string $key, string $comparator, string $value){
		switch($comparator){
			default:
			case 'EQ':
				$this->filters[$key] = ($key == '_id') ? new MongoDB\BSON\ObjectId($value) : $value;
			break;
			case 'STARTS':
				$this->filters[$key] = new MongoDB\BSON\Regex('^'.$value, 'i');
			break;
			case 'ENDS':
				$this->filters[$key] = new MongoDB\BSON\Regex($value.'$', 'i');
			break;
			case 'CONTAINS':
				$this->filters[$key] = new MongoDB\BSON\Regex($value, 'i');
			break;
		}
		return $this;
	}

	/**
	 * Sets the MongoDb query sort.
	 * @param   string  $sort Name of collection column to order by.
	 * @param   integer $direction Sort direction (1 = ASC, -1 = DESC).
	 * @return  object
	 */
	public function sort(string $sort, int $direction){
		$this->sort      = $sort;
		$this->direction = $direction;
		return $this;		
	}

	/**
	 * Sets the MongoDb query skip.
	 * @param   integer $index Range starting index.
	 * @return  object
	 */
	public function skip(int $index){
		$this->skip = $index;
		return $this;		
	}

	/**
	 * Sets the MongoDb query limit.
	 * @param   integer $limit Query results limit.
	 * @return  object
	 */
	public function limit(int $limit){
		$this->limit = $limit;
		return $this;		
	}

// --------------------------------------------------------------------------------
// PUBLIC METHODS - CHAINABLE RENDERING
// --------------------------------------------------------------------------------

	/**
	 * Performs a find query and returns a count of the results.
	 * Returns FALSE on failure.
	 * @return  integer
	 */
	public function count(){
		try{
			$collection = $this->client->{$this->database}->{$this->collection};		
			$result 	= $collection->count($this->filters, $this->getOptions());
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}
		return $result;		
	}

	/**
	 * Performs a find query and returns the results.
	 * Returns FALSE on failure.
	 * @return  object
	 */
	public function get(){
		try{
			$result 	= [];
			$collection = $this->client->{$this->database}->{$this->collection};
			$cursor 	= $collection->find($this->filters, $this->getOptions());
			foreach($cursor as $document){
			   $result[] = $document;
			}
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}
		return $this->parseResult($result);
	}

	public function insert(array $data, bool $is_many=FALSE){
		try{
			$collection = $this->client->{$this->database}->{$this->collection};
			$result     = ($is_many) ? $collection->insertMany($data) : $collection->insertOne($data);
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}
		return [
			'inserted'  => $result->getInsertedCount(),
			'insertid'  => ($is_many) ? $this->parseReturnOIDs($result->getInsertedIds()) : (string) $result->getInsertedId()
		];
	}

	public function update(array $data, bool $is_many=TRUE){
		try{
			$collection = $this->client->{$this->database}->{$this->collection};
			$result     = ($is_many) ? $collection->updateMany($this->filters, ['$set' => $data]) : $collection->updateOne($this->filters, ['$set' => $data]);
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}	
		return [
			'matched'  => $result->getMatchedCount(),
			'modified' => $result->getModifiedCount()
		];	
	}

	public function upsert(array $data){
		try{
			$collection = $this->client->{$this->database}->{$this->collection};
			$result     = $collection->updateOne($this->filters, ['$set' => $data], ['upsert' => TRUE]);
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}
		return [
			'matched'  => $result->getMatchedCount(),
			'modified' => $result->getModifiedCount(),
			'upserted' => $result->getUpsertedCount()
		];		
	}

	public function replace(array $data, bool $upsert=FALSE){
		try{
			$collection = $this->client->{$this->database}->{$this->collection};
			$result     = $collection->replaceOne($this->filters, ['$set' => $data], ['upsert' => $upsert]);
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}	
		return [
			'matched'  => $result->getMatchedCount(),
			'modified' => $result->getModifiedCount()
		];	
	}

	public function delete(bool $is_many=TRUE){
		try{
			$collection = $this->client->{$this->database}->{$this->collection};
			$result     = ($is_many) ? $collection->deleteMany($this->filters) : $collection->deleteOne($this->filters);
			$this->resetProps();
		} catch(\Exception $e){
			echo $e->getMessage();
			return FALSE;
		}	
		return [
			'matched'  => $result->getMatchedCount(),
			'deleted'  => $result->getDeletedCount()
		];		
	}

}

?>