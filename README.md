# FoodExpress

Sample app to manage orders to a dummy restaurant. It allows users to make
orders based on a daily menu and receive an estimation of the delivery time.
It also keeps track of the orders history showing both pending and completed
orders. Some daily stats about the workers can also be viewed (e.g. number of
dishes prepared, average value of the prepared dishes, overall working time,
etc...).  
The menu and the workers that prepare the dishes must be setted before making
the orders: you can't order nothing if there's nothing to order and you can't
receive nothing if nobody prepares it!


## Usage

**NB**: Postgres PDO driver is required.

Install the dependencies
```
composer install
```

Create a new database and load the [dump](https://github.com/raxell/FoodExpress/blob/master/db.sql)
```
createdb food_express
psql food_express < db.sql
```

Start the server
```
php -S localhost:8000 -t public public/index.php
```

Go to `http://localhost:8000/` to use the app.  
You can use the following credentials to login to the app:

* **Regular user**  
  Email: `utente@mail.com`  
  Password: `abc`

* **Administrator** (can set the menu and workers)  
  Email: `ristoratore@mail.com`  
  Password: `cba`

