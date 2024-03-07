# Turso

This package provides Turso database abstraction for the Hiraeth Nano Framework, although the package will be generally useful as a generic Turso library for others as well.  The primary goal here is to create a pretty thin layer that will help with basic DB abstraction.  The goal of this is not to reproduce all features of Doctrine or equivalently robust ORMs.  If you want Doctrine for Turso, I'd suggest working on support within Doctrine, as it's probably abstract enough that you could do it.

## Installation

`composer require hiraeth/turso`

## Basic Usage

In Hiraeth you can configure a default database connection by adding the following to your `.env`:

```ini
[TURSO]
	NAME         = <your database name>
	ORGANIZATION = <your organization name>
	TOKEN        = <your bearer token>
```

> NOTE: A database manager is mind, however, it won't be supported for much longer.  That said, creating a new Database is pretty simple, and writing a little wrapper to configure more than one in Hiraeth should be pretty straightforward.

That's all you need.  From there your the `Hiraeth\Turso\Database` class can be auto-wire injected anywhere you normally get auto-wired injections (actions, middlewares, etc).

For non-integrated (outside of Hiraeth) use, or to instantiate multiple databases, constructing a new instance looks like this:

```php
$database = new Hiraeth\Turso\Database(new Guzzle\Http\Client(), $name, $token, $organization);
```

> NOTE: This is not written to PSR-18 standard, or at least no check was done to ensure it wasn't using Guzzle specific implementation.  So for now guzzle is a hard dependency, but the first arg will probably change to any PSR-18 implementation at a later date.

Run some basic SQL:

```php
$result = $database->execute("SELECT * FROM users");
```

Check for error any error returned:

```php
if ($result->isError()) {
    $error = $result->getError();

    throw new RuntimeException(sprintf(
    	'Could not get users, error %s: %s',
        $error->code,
        $error->message
    ));
}
```

Count results:

```php
echo sprintf('There were %d results from the query', count($result));
```

Iterate over results:

```php
foreach ($result() as $user) {
    echo $user->first_name . PHP_EOL;
}
```

Mapping results to a typed DTO allows you to turn columns into nice properties:

```php
foreach ($result(User::class) as $user) {
    echo $user->firstName . PHP_EOL;
}
```

In the above example the DTO looks like this:

```php
class User extends Hiraeth\Turso\Entity
{
    public id;
    public firstName;
    public lastName;
    public email;
}
```

The properties can also be `protected` if you prefer to add getters/setters for access.

>  NOTE: The mapping of column names to properties works on the basis that property name and column name match when all non alpha-numeric characters are removed, e.g. `first_name` would map to `firstName`.  You must declare all properties for typed DTOs.  All untyped DTOs will be of class `Hiraeth\Turso\Entity` and it will use an internal variable storage, but will only support the original column names.

You can get a single record such as:

```php
$record = $result->getRecord(0);
```

And similar to using the iteration model, you can pass a DTO to map to:

```php
$record = $result->getRecord(0, User::class);
```

> NOTE: You SHOULD NOT use `count()` and `getRecord()` to loop over results as this has lower performance than using the `$result()` callable iteration style.

You can get all the records as an array so that you can use `array_map` or `array_filter` using the following:

```php
$records = $result->getRecords();
```

And you can map them to a typed DTO by passing the class there too:

```php
$records = $result->getRecords(User::class);
```

## Query Parameters

When executing queries you can pass parameters by setting placeholders:

```php
$result = $database->execute(
	"SELECT * FROM users WHERE email = {email}",
	[ 'email' => 'info@hiraeth.dev' ]
);
```

You can do this with lists too:

```php
$result = $database->execute(
	"INSERT INTO users (first_name, last_name, email) VALUES {values}",
    [ 'values' => ['Matthew', 'Sahagian', 'msahagian@hiraeth.dev'] ]
);
```

If you need to pass in raw parameters, use this style:

```php
$result = $database->execute(
	"SELECT * FROM @table WHERE @column = 1",
    [],
    [ 'table' => 'users', 'column' => 'id' ]
);
```

## DTO Relationships

You can get related records by adding a method on your typed DTOs:

```php
class User extends Hiraeth\Turso\Entity
{
    // ... properties

    public function occupation()
    {
        return $this('occupations')->hasOne(['occupation' => 'id'], Occupation::class);
    }
}
```

Then calling `$user->occupation()` will return their related `Occupation` from the `occupations` table.

You can do one-to-many like this:

```php
class Occupation extends Hiraeth\Turso\Entity
{
	// ... properties

	public function workers()
	{
		return $this('users')->hasMany(['id' => 'occupation'], User::class);
	}
}
```

For a many-to-many, you need to go through another table:

```php
public function relatedOccupations()
{
    return $this('occupations', 'related_occupations')->hasMany(
    	['id' => 'occupation', 'related_occupation' => 'id'],
     	Occupation::class
    );
}
```

> NOTE: The column paths are always from the DTO you're on to the related DTO you're trying to get.  In the case of many-to-many, just as you need both a target and a through table, you need two paths, first to the join table, then from the join table to the target.  Many-to-many is implemented using a sub-select to avoid join/excess column returns/filtering.

### Repositories

You can create a repository by extending `Hiraeth\Turso\Repository`.  Right now repository functionality is mostly limited to finding records, however, the next priority is to implement INSERT, UPDATE, DELETE, etc.

A repository looks like this:

```php
class Users extends Hiraeth\Turso\Repository
{
    static protected $table = 'users';
    static protected $entity = User::class;
    static protected $identity = ['id'];
}
```

> NOTE:  The the identity corresponds to the column(s) which constitute the primary key for the record.  They are expressed as the FIELD properties of your `$entity` class, not as database column names.

Once you have the repository you can get it from the database:

```php
$users = $database->getRepository(Users::class);
```

If you choose to instantiate repositories on your own, you'll need to inject the database into their constructor:

```php
$users = new Users($database);
```

Now that you have your repository, you can do things like finding a record:

```php
$user = $users->find(1);
```

If your `$identity` is compound, or if you want to search by other unique columns or a combination of columns that produces a unique result you can pass an array:

```php
$user = $users->find(['email' => 'info@hiraeth.dev']);
```

> NOTE: The `find()` method will return directly the custom DTO or `NULL` if it cannot be found.  It will also throw exceptions if the constraints you pass as the first parameter don't yield a single record.  So you should only use it on unique columns and/or multi-columns with actual unique constraints.

If you just want to get all records you can do:

```php
$all_users = $users->findAll();
```

You can also perform a `findBy()` with optional order, limits, and page:

```php
$some_users = $users->findBy(['occupation' => $occupation->id], [], 15, $page);
```

The second parameter above is an ordering set.  You can specify the default order on the Repository like so:

```php
static protected $order = ['lastName' => 'asc', 'firstName' => 'asc'];
```

If you pass the order, to `findBy()` it will overload it... the default order also applies to `findAll()`.

> NOTE: The `findAll()`  and `findBy()` method returns a `Result`, same as `Database::execute()`, so the syntax for iteration is a bit weird if you do it directly, e.g. `foreach ($users->findAll()() as $user) { ... }`.  This will be updated at a later date to implement the Iterator interface directly, but they will always return a result object so you can access the records in different ways without necessary instantiation hundred or thousands of DTOs.

### Queries

Queries are highly experimental even though they do power the basic function.  So consider this documentation not ready for use (some of the features described here may not even be implemented yet).

The basic idea, however, is that they use the basic templating language for `execute()` along with the ability for raw values to be, themselves, queries or arrays of queries.  If you're interested in understanding that a bit more, look at the `Query.php` file in the `src` directory source.  Here's what the `findBy()` query looks like:

```php
$result = $this->database->execute(
    $query("SELECT * FROM @table @where ORDER BY @order @limit @start")
        ->raw('table', static::$table)
        ->raw('where', $query->where(...$conditions))
        ->raw('order', $query->order(...$order_bys))
        ->raw('limit', $query->limit($limit))
        ->raw('start', $query->offset(($page - 1) * $limit))
);
```

This is not really a "builder" per say (like Doctrine's query builder), it's more like a template expander.  Since the queries ultimately just resolve to strings, you can pass these to `Databse::execute()`.

However, similar to Hiraeth's Doctrine Repositories, we do intend to implement a similar `query()` method, with a shorter syntax:

```php
$users->query(function(Query $query) {
   return $query
       ->setWhere(
           $query->like('email', '%@hiraeth.dev'),
           $query->gte('codingAge', 28),
           $query->any(
               $query->eq('primaryLanguage', 'php'),
               $query->eq('primaryLanguage', 'pascal')
           )
   	   )
       ->setOrderBy(
           $query->sort('lastName', 'asc'),
           $query->sort('firstName', 'asc')
   	   )
       ->setLimit($limit)
       ->setOffset($offset)
   ;+
});
```

