# README #

###Notes for installing SPARQL add-on module

* Ensure you are running **v0.7.4** or later which includes support for add-on modules
* From the root of your API Factory installation, run: `composer require mkdf/api-factory-sparql`
* Copy the additional sparql configuration from config/autoload/local.php.dist into your main local.php file, specifying the location and port number for your Blazegraph installation and also any prefix that is used for graph namespaces (as configured in rdf.uploader)
* Uncomment `'APIF\Sparql'` from config/modules.config.php
