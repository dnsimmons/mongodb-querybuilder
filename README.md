# mongodb-querybuilder

PHP Query Builder Abstraction for MongoDB

mongodb-querybuilder is an abstraction layer for use with the official [MongoDb PHP Library](https://github.com/mongodb/mongo-php-library) that provides a chainable set of methods for simplifying working with queries.


### Basic Examples

Let's connect to the MongoDB database "`example`" and fetch some data from the "`products`" collection. 

		$DB = new QueryBuilder('example');

		$data = $DB->collection('products')->get();


Let's do the same but apply a projection, sort, and a limit to our results.

		$data = $DB->collection('products')
			->projection('title', 'sku')
			->sort('title', 1)
			->limit(5)
			->get();	

Insert a document into the products collection ...

	$insert = $DB->collection('products')
		->insert([
			'title' => 'Widget',
			'sku'   => 'WIDGET-1'
		]);

Insert multiple documents into the products collection ...

	$insert = $DB->collection('products')
		->insert([
			[
				'title' => 'Widget 1',
				'sku'   => 'WIDGET-1'
				'options' => [
					'color' => 'red',
					'size'  => 'small'
				]
			],
			[
				'title' => 'Widget 2',
				'sku'   => 'WIDGET-2'
			]
		], TRUE);


### Methods

- collection
- projection
- filter
- sort
- skip
- limit
- count
- get
- insert
- upsert
- update
- replace
- delete

