# Turso

This package provides Turso database abstraction and light ORM layer for the Hiraeth Nano Framework, although the package will be generally useful as a generic Turso library for others as well.

The primary goal here is to create a pretty thin layer with some basic nice to have features.  This project does not intend to reproduce all features of Doctrine or equivalently robust ORMs.  If you want Doctrine for Turso, I'd suggest working on support within Doctrine, as it's probably abstract enough that you could do it and may not even be that difficult.

> NOTE: This software is still in beta that's closer to alpha, things are changing rapidly and some features are completely incomplete.  Rely on more advanced features at your own risk for now.  Additionally, if you're wondering what happened to version 1 and 2, Hiraeth packages are versioned along with the framework, Hiraeth 1.x - 2.x did not have this package, and it will not be backported, so there's only a 3.0-beta.  That should be irrelevant if you're just using it as a library.

## Installation

`composer require hiraeth/turso`

## Testing

If you want to test and play around with this, follow these instructions:

1. Clone this repository: `git clone https://github.com/hiraeth-php/turso.git`
2. Change directory: `cd turso`
3. On Linux (only?): `chmod 666:666 test/data/sqld`
4. Run in docker: `docker compose up`
5. Execute: `php test/index.php`

> NOTE: Step #3 above seems to be necessary for some permission issues with docker on Linux.  Basically, internally the LibSQL server docker image creates and `sqld` user/group (with id 666).  The folder it writes to needs to have this uid and gid for the database to initialize properly and write.

## Basic Usage

In Hiraeth you can configure a default database connection by adding the following to your `.env`:

```ini
[TURSO]
    URL   = <your database url without trailing />
    TOKEN = Bearer <your bearer token>
```

> NOTE: A database manager is mind, however, it won't be supported for much longer.  That said, creating a new Database is pretty simple, and writing a little wrapper to configure more than one in Hiraeth should be pretty straightforward.

That's all you need.  From there your the `Hiraeth\Turso\Database` class can be auto-wire injected anywhere you normally get auto-wired injections (actions, middlewares, etc).  Your repositories can also be auto-injected with the default database.

### For Other Frameworks / As A Library

For non-integrated use or outside of Hiraeth, or to instantiate multiple databases, constructing a new instance looks like this:

```php
$database = new Hiraeth\Turso\Database(
    new GuzzleHttp\Client(),
    $url,
    $token
);
```

> NOTE: The $token must container the full Authorization string, such as "Bearer <actual token>"

## Executing Queries

There are two main types of queries you can run:

- Static Queries
- Parametized Queries

The two styles **SHOULD NOT** be mixed.  You have been warned.

### Static Queries

Static queries are full SQL queries that take no parameters, all values are assumed to be escaped and it would be just as if you typed the query directly into a database shell.  A simple example might be getting all users from a database.

```php
$result = $database->execute(
    "SELECT * FROM users"
);
```

Or, getting all users with a certain e-mail domain:

```php
$result = $database->execute(
    "SELECT * FROM users WHERE email LIKE '%@hiraeth.dev'"
);
```

A query is determined to be static when no additional arguments are passed to the `Database::execute()` function.

### Parametized Queries

By contrast, parametized queries allow you to insert variables in place of tokens.  An example of the above query rewritten as a parametized query would be as follows:

```php
$result = $database->execute(
    "SELECT * FROM @table WHERE email LIKE {domain}",
    [
        'domain' => '%@hiraeth.dev'
    ],
    [
        'table' => 'users'
    ]
);
```

There are two types of parameters which can be placed into parametized queries:

- Escapable Variables
- Raw Values

As you can probably tell from the above, the escapable values are in the style of `{varible}` (a name surrounded by {} brackets), while the raw values are in the style of `@reference` (a name preceded by an @ symbol).  Raw values are **NOT** escaped, so it's up to you to validate whether or not they are valid entries depending on where they're placed.

## Getting Results

The `$result` in all the examples above will hold an instance of `Hiraeth\Turso\Result`.  There are several ways in which you can easily access the records from it.  However, when using the `execute()` method, there is no guarantee your query worked, so you'll want to check for errors first:

```php
if ($result->isError()) {
    //
    // handle the error
    //
}
```

It's also possible to just easily throw `\RuntimeException` with a custom message prepended:

```php
$result->throw('Failed executing the query');
```

Assuming there was no error, let's get into how to use the results.

### Iteration

You can easily iterate over the records as such:

```php
foreach ($result as $record) {
    echo sprintf(
        'User with e-mail %s has an ID of %s',
        $record->email,
        $record->id
    );
}
```

### Single Records

If you need to get a single record, such as if you used `LIMIT 1` you can use:

```php
$record = $result->getRecord(0);
```

Note, however, that if the query did not actually produce any record(s) or if the record you request exceeds the returned number of records, the `getRecord()` method will return `NULL`.

### All Records

In some cases, you may want to use PHP's built in array functions to map, walk, sort, etc, records, by some other application logic.  While this may not be feasible for large sets of records, you can get all records as follows:

```php
$records = $result->getRecords();
```

### Counting Records

If you just need to count the number of records you can use PHP's standard `count()` function:

```php
$count = count($result);
```

### Records as Entities

Records take on the form of object entities, simple DTO (Data Transfer Objects) which, by default will map one to one with your column names.  All records, regardless of the table they were retrieved from will be of the class `Hiraeth\Turso\Entity`.  While this may work for simple applications, if you want to add additional business logic to create more developed models, protect and wrap entity properties with getters and setters, etc, you will want to created a typed entity.

## Typed Entities

Typed entities are simply custom classes which extend the `Hiraeth\Turso\Entity` class.  They have a lot of advanced features over default untyped entities.  Here's a simple example of a typed entity, continuing with our user example:

```php
class User extends Hiraeth\Turso\Entity
{
    const table = 'users';

    protected $id;

    public $firstName;

    public $lastName;

    public $email;

    public function fullName()
    {
        return trim(sprintf(
            '%s %s',
            $this->firstName,
            $this->lastName
        ));
    }
}
```

In the above example, we have chosen to protect the `id` so it cannot be modified.  You could just as easily protect other properties and use setters and getters as you like.  You may also be noting that some properties are _camelCase_.  This is becaue `Hiraeth\Turso` will "automatically" figure out which properties map to which returned columns by lowercasing and removing all non-alpha-numeric characters and comparing them.

In the above example the columns in the database could easily be `first_name` and `last_name`.

### Casting Results

In order to get result records as typed entities you need to cast the result, this is done using the `of()` method, which will return the result after casting it.

```php
$records = $result->of(User::class);
```

Since the method returns the results (just with some internal properties established), you can just as easily use this in iteration:

```php
foreach ($result->of(User::class) as $user) {
    echo sprintf(
        '%s has an e-mail of %s' . PHP_EOL,
        $user->fullName(),
        $user->email
    );
}
```

### Associations

Typed entities have another benefit.  Namely, they can easily obtain related entities of other types.  Let's imagine that in addition to our `User` entity we have an `Occupation` entity, and every user has one occupation, and every occupation has many users.  Additionally, let's imagine that we have a `friends` table which allows users to add each other as friends, creating a many-to-many relationship.  Here we can review each one of these relationships in more detail:

#### Star-to-One

```php
public function occupation()
{
    return $this(Occupation::class)->hasOne(
        [
            'occupation' => 'id'
        ],
        FALSE
    );
}
```

In the above example, when we call `User::occupation()` we will obtain the `Occupation` who's `id` corresponds to the `occupation` on the `User`.   Let's go through this step by step.  Firstly, a new association to the `Occupation` type is instantiated.

```php
$this(Occupation::class)
```

We then use that association to retrieve a single record via `hasOne()`.  The first property contains a mapping of how we get from the `User` to the `Occupation`:

```php
[
    'occupation' => 'id'
]
```

> NOTE: when defining returned associations we are _always_ using the actual column names, not the corresponding property names found on the entity.

The second argument to the `hasOne()` method tells the association not to _refresh_.  This means that once the related record is obtained, we won't be querying again.  If this were set to `TRUE`, every call to `occupation()` would perform a query to look up the related occupation.  While this is good if you have rapidly changing data, it's also a drain on performance.  We recommend adding an argument to the `occupation()` method itself to pass along in the event you do need to refresh:

```php
public function occupation(bool $refresh = FALSE): ?Occupation
{
    return $this(Occupation::class)->hasOne(
        [
            'occupation' => 'id'
        ],
        $refresh
    );
}
```

In the event of a *-to-one association, the returned value will always be either the corresponding entity type or `NULL` if the person does not have an associated record.

Now that we have a basic idea, let's move on to the others, which will look very similar.

#### One-to-Many

On the inverse side of our `User` example, we can imagine the following on our `Occupation` class:

```php
public function users(bool $refresh = FALSE): Result
{
    return $this(User::class)->hasMany(
        [
            'id' => 'occupation'
        ],
        $refresh
    );
}
```

Here, the default `$refresh` being false is extra important.  Although you can always store the result in a variable, in a case you performed a `count()` on calling the method, and then iterated, you'd actually be making two queries if `$refresh` were `TRUE`:

```php
if (count($occupation->users())) {
    foreach ($occupation->users() as $user) {
        // ...
    }
}
```

This could become very expensive on large relations.  Finally, the last and most complex example.

#### Many-to-Many

The major difference between the previous examples and this one is that we need to specify an additional "through" table, as well as two entries in our map.  Remember, this is a hypothetical on the `User` class, to get a user's friends, since many users can have many friends, we need a join table:

```php
public function friends($bool $refresh = FALSE): Result
{
    return $this(User::class, 'friends')->hasMany(
        [
            'id' => 'user', 'friend' => 'id'
        ],
        $refresh
    );
}
```

Take note of the second argument when invoking with `$this()`.  The first argument remains our ultimate target table, the second defines the "through" table.  Corresponding to this, our map now has two entries.  The first is how do we get from the user we're operating on to the friends, and the second from the friend to the user that is their friend.  Association maps always move from the record you're on to the record you're targeting, in this case, the middle bit `'user', 'friend'` reflects the structure of our join table:

```sqlite
CREATE TABLE friends (
    user INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    friend INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (user, friend)
);
```

#### Roadmap

The below features are not complete, but they are planned as it relates to association.

##### Setting Associations

Fundamentals have been laid to enable setting associated records which will automatically update related ids.  The proposed style of this will be to modify association functions to call the corresponding `changeOne` and `changeMany` methods, a quick example re-using the `User::occupation()` method may look like this:

```php
public function occupation(bool|Occupation $refresh = FALSE): ?Occupation
{
    if ($refresh instanceof Occupation) {
        $this(Occupation::class)->changeOne($refresh, [
            'id' => 'occupation'
        ]);
    }

    return $this(Occupation::class)->hasOne(
        [
            'occupation' => 'id'
        ],
    	$refresh
    );
}
```

You would then pass the newly associated occupation such as:

```php
$user->occupation($occupation);
```

This would:

1. Update the `occupation` property on the `User` to match the id of the `Occupation`
2. Update `users` table to set the occupation column to the id of the Occupation
3. Update the cache with the new occupation and return it.

##### Guard Clauses

A way to define guard clauses such that you may get related records, but only a sub-set based on additional conditions being met.  This is a longer term vision and there is no imagined syntax for this.  Suggestions welcomed.

## Repositories

Repositories are the more advanced gateway to your tables.  They deal solely in typed entities and offer the ability to easily create, insert, update, and delete entities.  They also enable shorter syntax for associations.

You can create a repository by extending `Hiraeth\Turso\Repository`.  A repository looks like this:

```php
class Users extends Hiraeth\Turso\Repository
{
    const entity = User::class;

    const identity = [
        'id'
    ];

    const order = [
        'firstName' => 'asc',
        'lastName'  => 'asc'
    ];
}
```

> NOTE: The `order` constant which defines the default sort ordering when selecting entities as well as the `identity` constant which specified fields that constitute the primary key or unique ID of the entities are expressed in _field names_, not columns.

### Getting Repositories

In Hiraeth, repositories will be auto-injected with the default configured database, so you can add them directly to any dependency injected location (actions, middlewares, etc).  Alternatively, and as is likely to be used when using this as a library, you can request a repository from your `Database` instance:

```php
$users = $database->getRepository(Users::class);
```

If you were to instantiate directly, then you'd have to pass the database instance to it:

```php
$users = new Users($database);
```

### Performing Common Operations

Repositories support the following common operations:

1. Creating a new entity instance.
2. Inserting the instance into the database.
3. Updating the instance in the database.
4. Deleting the instance in the database.
5. Finding entities in the database.

We will cover each of these very briefly as they are all pretty straightfoward and shouldn't need much explanation.  Let's take a look at the entire lifecycle:

#### Create

```php
$user = $users->create([
    'firstName' => 'Hiraeth',
    'lastName'  => 'User',
    'email'     => 'info@hiraeth.dev'
]);
```

> NOTE: Create does not insert the entity into the database, it simply creates an instance and populate the data.

You can set your properties distinctly as well:

```php
$user = $users->create();

$user->firstName = 'Hiraeth';
$user->lastName  = 'User';
$user->email     = 'info@hiraeth.dev';
```

#### Insert

Once you have an instance created, you can insert it easily:

```php
$result = $users->insert($user);
```

Unlike with `Database::execute()` all repository actions will throw exceptions immediately on error response from Turso.  This means you'll likely want to wrap operations in a try/catch.  Regardless, a `Hiraeth\Turso\Result` is returned upon success in the event you want to examine the result further.  This can come in handy for updates, as we'll see.

#### Update

When entities are loaded from the repository, their inital values are stored in the `$_values` property on the entity (normally used by untyped entities to store actual property values).  The benefit of using repostory only access with typed entities is then that a diff can be performed so that only the updated columns are sent with the update.  For example, if we change the user's `firstName` it only it will only `SET` that in the SQL.

```php
$user->firstName = 'Laravel';
```

While we should probably just send this directly to `delete()` now, let's go ahead and show some grace.  We'll perform the update anyway:

```php
$result = $users->update($user);
```

Upon calling:

1. A "diff" will be performed on the current property values and the original values obtained when the entity was instantiated by the repository.
2. The user's corresponding `first_name` column (the only column that changed) will be updated via standard `UPDATE` query.
3. The change will be pushed to the `$_values` property such that the updated information is now considered to reflect what's in the database.

##### Success?

Unfortunately, due to the nature of `UPDATE` itself, the fact that Turso did not return an error _does not_ suggest that this entity was actually updated.  In fact, it's quite possible while we were doing work on them that they were deleted from the database entirely.  Since `UPDATE` can perform updates on multiple (and therefore 0) matching records to its `WHERE` clause, we actually probably want to double check by looking at the affected rows:

```php
if (!$result->getAffectedRows()) {
    //
    // Handle the user having gone missing
    //
}
```

If you don't particularly care that the user may have gone missing, you obviously don't need to do anything here.

### Delete

Well, I guess we got here anyway.  Now that our Hiraeth User has been updated to a Laravel User, we can delete them:

```php
$users->delete($user);
```

You're probably seeing a pattern by now as to how these methods work.  Similar to `UPDATE`, you can check to see if this affected a row, but why bother?

### Finding

Finding entities is pretty straightfoward.  Like all other repository operations, most finds and select will return a result (not arrays), with one exception:

```php
$user = $users->find(1);
```

The simple `find()` method which will get a single result based on a primary key id or a set of criteria will return the entity directly (or `NULL` if one can't be found).  Strictly speaking, the identity does not need to be a primary key or even a single column.  It can be any set of criteria that yield a single result:

```php
$user = $users->find(['email' => 'info@hiraeth.dev'])
```

Scalars are supported only when there is a single column `Repository::identity` which, admittedly, is likely most cases.

> NOTE: Before trying to use `find()` as a general shorthand, it will throw `\InvalidArgumentException` if the criteria yields more than a single result.  For this reason, you should only ever actually use criteria that correspond to a primary key or unique constraint (whether single or multi column)

#### FindAll

If you just want to find all records, that's simple enough:

```php
$all_users = $users->findAll();
```

You can pass an optional ordering array as an argument or, if left empty, it will use the default `order` defined on the repository.

```php
$all_users = $users->findAll(['email' => 'asc']);
```

#### FindBy

The middle ground between `find()` aand `findAll()` is the `findBy()` method.  It accepts a set of criteria similar to `find()`, but won't throw exceptions if more than one record is returned in the result.  It also accepts the optional ordering array as its second argument.  Lastly, it accepts a `$limit` and `$page` (not _offset_), for its third and fourth arguments.  A complete example may look something like this:

```php
$taylors = $users->findBy(
    [
        'firstName' = 'Taylor'
    ],
    [
        'lastName' => 'desc'
    ],
    20,
    1
);
```

Stated more plainly, find all users who's first name is Taylor, sort them reverse alphabetical by their last name, and give me only the first 20 results.

### Selecting

Alas, not all searches for users are conditioned by simple equalities like x = y, or this equals that.  So the last and most powerful (also the most complex) feature is, of course, the complete `SELECT` query.

```php
use Hiraeth\Turso\SelectQuery;
use Hiraeth\Turso\Expression;

$entities = $users->select(
    function(SelectQuery $query, Expression $is) {
        $query
            ->where(
                $is->like('email', '%@hiraeth.dev'),
                $is->gte('age', 30)
            )
            ->order(
                $query->sort('age', 'desc')
            )
            ->limit(20)
            ->offset(0)
    }
);
```

Hopefully this is easy enough to follow, as I don't feel like explaining it.  Obviously inspired by Doctrine's query builder, but it's important to note that it's _not_ a builder.  It's more like a template expander.  The `SelectQuery` is just a templatized and method enabled query, which takes us all the way back to our `Database::execute` examples.  Here's the template, in case you're curious:

```
SELECT @names FROM @table @where @order @limit @offset
```

Here's what calling `order()` on it does:

```php
/**
 * Set the "ORDER BY" portion of the statement
 */
public function order(Query ...$sorts): static
{
    if (empty($sorts)) {
        $clause = $this('');
    } else {
        $clause = $this('ORDER BY @sorts')->bind(', ', FALSE)->raw('sorts', $sorts);
    }

    $this->raw('order', $clause);

    return $this;
}
```

With that in mind, calling any one of the methods shown above will _replace_ (not add to as a builder would).  So to create complex `where()` conditions you can employ the `all` (and) and `any` (or) expressions:

```php
where(
    $is->like('email', '%@hiraeth.dev'),
    $is->any(
        $is->gte('age', 30),
        $is->lte('age', 50)
    )
);
```

This would result in:

```sqlite
WHERE email LIKE '%@hiraeth.dev' AND (age >= 30 OR age <= 50)
```

You can nest as many `any()` and `all()` as you need to to group conditions.  The names are chosen as such because `any()` indicates that _any_ of the grouped expressions must be `TRUE` (hence 'or' equivalence) while `all()` indicates that _all_ of the grouped expression must be TRUE (hence 'and' equivalence).

For a list of all supported operators, see the `src/Expression.php` file in the source.
