## Installation

#### Prerequisites

* MongoDB
* Apache/PHP
* Composer
* MongoDB extensions for PHP

#### Installation instructions

1. Ensure you have a MongoDB instance installed and running, with an empty 
database for use with the API Factory and a user account with privileges for 
creating, modifying and deleting collections, users and roles
1. Clone this repository into you web server's document tree.
1. Install dependencies using 'composer install'
1. Configure config.php
    1. MongoDB host
    1. MongoDB port
    1. MongoDB database (*the database*)
    ```php
    <?php
    
    return [
        'mongodb' => [
            'host' => 'localhost',
            'port' => '27017',
            'database' => '<database_name>',
        ],
    ];
    ```
