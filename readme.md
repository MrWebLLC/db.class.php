# db.class.php

This class provides a simple and easy-to-use, lazy interface for interacting with a MySQL (MariaDb) database. It is designed to be used automatically as a singleton, meaning that there can only be one instance of the class at any given time.

## Donations
If you find this class helpful, and incorporate it into your projects please consider donating to the cause.
https://www.paypal.com/donate/?hosted_button_id=5VNUTX8TYUXAL

## Requirements
PHP 8 >= 8.2.0

## Installation

To use this class, simply include the `db.class.php` file in your project.

## Usage

To use the class, you first need to get the active instance, or create the instance of it using the `getInstance()` method. 

This will return a singleton instance of the class. 

Since this wrapper is a singleton, constructed in an object context, we cannot pass in the database information, so we create it, using a global dbConfig object. 

You can use a config class, or just create a stdClass object.

```php
$dbConfig = new stdClass();
$dbConfig->host = 'localhost'; 
$dbConfig->username = 'username';
$dbConfig->password = 'password'; 
$dbConfig->name = 'mydbname';

$db = db::getInstance();
```

### Standard and Prepared Statements

You can then use any of the methods provided by the class to interact with the database.
This class allows you to run both standard and prepared statements, using the same method calls.

```php
//Standard statement use
$db->select("SELECT * FROM `users` WHERE `id` = '{$safeUserId}';");

//Prepared Statement use
$db->select("SELECT * FROM `users` WHERE `id` = ?;", array($unsafeUserId));
```

### Inserting Data

To insert data into the database, you can use the insert method.
 
 `insert()` : This method takes an SQL query and optional parameters, and returns the insert id to the new record.

```php
$userId = $db->insert("INSERT INTO `users` (`name`, `email`) VALUES (?, ?)", array("John Doe", "john@example.com") );
```

### Selecting Data 

This class provides a couple of different ways to deal with selecting data from databases.
Working with result sets, and shortcut operators, that allow us to just grab data directly passing your sql query.

### Selecting Single Values
Using mysqli without a wrapper requires several lines of code to be written. Im too lazy for that.

`getval()` : Grab a single value from the database.

```php
$userName = $db->getval("SELECT `name` FROM `users` WHERE `userId` = ''; ", array($userId));
```

### Selecting Single Rows
This class allows you to grab a single row of data as either an assosiative array, or as an object.

`getrow()` : Return a row as an associative array

`getobj()` : Return a row as an object

```php
//Get user array
$row = $db->getrow("SELECT * FROM users WHERE id = ?", array($userId));

// Get user object
$userObj = $db->getobject("SELECT * FROM users WHERE id = ?", array($userId));

```

### Selecting Number of Rows
Sometimes you just want to know how many rows exist for a query, without actually collecting an data.

`num_rows()` : Returns the amount of rows produced from an sql query

```php
$numRows = $db->num_rows("SELECT `id` FROM `users` WHERE `status` = 'active'; ");
```
Typically you would use a COUNT() query, but there are some use cases for the above.


### Selecting Result Sets (Multiple Rows)
When you need to deal with working with multiple rows of results, you will use the result set methods.

`select()` : Performs a select query and returns a result set.

```php
$sql = "SELECT * FROM users;";
$result = $db->select($sql);
```

The result set is returned to you, so you can save it for later. It is also stored in the class under the $this->result variable.
For most use cases, you can omit the $result variable from the method calls. However, if you have ran several queries, and need to
work with a prevoius result set, you just pass the result set into the method.

We will show you how to work with the result set below.

### Getting the number of rows (Result Set)

`nr()` : Return the number of rows of a given result set.

```php
//Using the most recent result set.
if($db->nr() > 0)
{}

//Using a previous saved result set
if($dbo->nr($res) > 0)
{}
```
### Getting the Row Data (Result Set)
To get the result set, you have a couple of different options

`getassoc()` : Returns an associative array from the result set

`getArray($res,$type)` : Returns the result set as associative array, a numbered array, or both. Type should be 'ASSOC','NUM' or 'BOTH'.

`getobj()` : Returns an object from the result set.

```php
//Associate Array
while($row = $db->getassoc($res)){
    echo $row['id'];
    echo $row['email'];
}

//Object
while($row = $db->getobj($res)){
    echo $row->id;
    echo $row->email;
}

```

Above is the most common use cases for working with results. We will go over the rest of the methods at the end of this file.

### Updating Data
To update a record in the database

`update()` : This method takes an SQL query and optional parameters, and returns a boolean indicating whether the update was successful.

```php
if($db->update("UPDATE users SET name = ? WHERE id = ?", array("Jane Doe", 1)))
{
    echo "User updated successfulle!";
}

```

### Deleting Data

To delete records from the database.

`delete()` : This method takes an SQL query and optional parameters, and returns a boolean indicating whether the deletion was successful.

```php
if(!$db->delete("DELETE FROM users WHERE id = ?", array($userId)))
{
    echo "ERROR: Unable to remove user.";
}
```

### Creating Tables

To add a new table into the database.

`create()` :  This method takes an SQL query and returns a boolean indicating whether the creation was successful.

```php
$sql = "CREATE TABLE `users` (`id` INT NOT NULL AUTO_INCREMENT , `name` VARCHAR(255) NOT NULL , `email` VARCHAR(255) NOT NULL , `status` VARCHAR(55) NOT NULL , PRIMARY KEY (`id`)) ENGINE = MyISAM; ";
$db->create($sql);
```

### Deleting Tables

To delete tables from the database. 

`drop()` : This method takes an SQL query, and returns a boolean indicating whether the deletion was successful.

```php
$db->drop("DROP `users`;);
```

### Database Methods

`getVersionNumber()` : Returns the version number of the database.

```php
$mysqlVersion = $db->getVersionNumber();
```


`getDatabaseType()` : Return the database type. (MariaDb or Mysql).

```php
$dbType = $db->getDatabaseType();
```


`selectDb($dbname)` : Select the database you want to work this. This is handled in the constructor, but exists to allow you to switch between different databases.

```php
$db->selectDb('tempDatabase');
```


### Connection Methods
The connection to the database is created with the instance of the class, so there is no methods for opening a connection.
However, we have provided a method for closing a connection.

`close()` : Closes the connection to the database;

```php
$isClosed = $db->close();
```

### Additional Result Set Methods

`seek($result, $offset)` : Sets the internal pointer of the result set to the specified offset.

`numfields()` : Retrieves the number of fields in the given result set.

`fieldtype($result, $index)` : Retrieves the type of the field at the specified index in the given result set.

`fieldname($result, $index)` : Retrieves the name of a field from a result set at a specified index.

`free()` : Frees the result set.

### Error Handling

If an error occurs during any of the database operations, the class will set the `errorCode` and `errorMessage` properties of the instance. You can check these properties to see if an error occurred.

```php
$user = $dbo->getobject("SELECT * FROM `users` WHERE `ix` = '1';");
if(!$user)
{
    if ($db->errorCode) 
    {
        // Error: #1054 - Unknown column 'ix' in 'where clause'
        echo "Error: " . $db->errorMessage;
    }
}

```

If an error occurs within the class itsself, the class will set the `error` property with the class provided error message;

```php
if(!$db->insert("DELETE * FROM `users` WHERE 1;"))
{
    if($db->error){
        // Error: Invalid SQL provided for the insert command
        echo "Error: $db->error";
    }
}

```

## License

This class is licensed under the MIT License. See the LICENSE file for more information.

## Contributing

Contributions are welcome! If you find any bugs or have suggestions for improvement, please submit a pull request.

## Author

This class was created by [MrWebLLC](https://github.com/MrWebLLC).