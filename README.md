## Installation

#### Prerequisites

* MongoDB
* Apache/PHP
* Composer
* MongoDB extensions for PHP

#### Installation instructions

1. Ensure you have a MongoDB instance installed and running, with a new empty 
database ready for use with the API Factory and a user account with privileges for 
creating, modifying and deleting collections, users and roles.
1. Clone this repository into your web server's document tree.
1. Install dependencies using `composer install`
1. Edit config.php with your MongoDB host, port and database name
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
1. Configure the web-based Swagger API tools. Locate the following section 
toward the bottom of the following **two** files:  
`swagger-api-factory.json`  
`management/swagger-api-factory-management.json`
```json
"servers": [
    {
      "url": "http://<yourdomain>/<installation folder>/"
    }
  ],
```
The url should be updated to reflect the URL of you API Factory installation

### SECURITY NOTE
**You should configure your web server to only allow requests to the API Factory 
(both the data stream API and management API) via SSL. The API Factory *can* operate 
in an unsecure environment but should only be allowed to do so on a private and secure 
network. Since username and password authentication is done using HTTP Basic Auth, 
all production use of the API Factory should happen via HTTPS.**
   
## Usage

#### API Factory - Management
The Management API is used for creating new datasets and modifying permissions.

API methods are documented and can be tested using the web tools provided at:  
http://\<yourdomain\>/\<installation folder\>/management/  
Before testing the API methods, use the `Authorize` button at the top right of 
the page, logging in using the MongoDB username and password mentioned in Step 1 of
the installation instructions.  
[FULL DOCS HERE]

#### API Factory - Data stream usage
API methods are documented and can be tested using the web tools provided at:  
http://\<yourdomain\>/\<installation folder\>/
Before testing the API methods, use the `Authorize` button at the top right of 
the page. You should authenticate using the specific dataset key as both the 
username and password.  
[FULL DOCS HERE]
