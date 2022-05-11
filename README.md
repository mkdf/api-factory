# README #

### Notes for /changes/ endpoint

#### Data limits
By default, this API endpoint will only return a maximum of 100 items. 
This can be changed by using either the timestamp parameter, the limit parameter, or
a combination of the two. 

If only the **timestamp** parameter is specified, *all* entries since that timestamp will
be returned, with no limits. If you wish to limit the amount of data returned whilst 
also specifying a **timestamp** parameter, simply also specify a **limit** paramter. 

*Please take care not to make repeated requests for unlimited change entries. Requesting 
all entries since the creation of the dataset should generally 
be done once only, when building or rebuilding a complete replica of a dataset. You should then 
keep a record of the timestamp of when the most recent request was made and subsequently only 
request incremental changes since that timestamp.*

*Examples*

`GET /changes/{dataset-id}` - The most recent 100 entries will be returned

`GET /changes/{dataset-id}?timestamp=1651363200` - return all entries since 
*Sunday, 1 May 2022 00:00:00*, with no limits on the amount of entries returned

`GET /changes/{dataset-id}?timestamp=1651363200&limit=20` - return entries since
*Sunday, 1 May 2022 00:00:00*, limited to the 20 most recent entries.

`GET /changes/{dataset-id}?limit=200` - The most recent 200 entries will be returned, 
regardless of timestamp.

#### Sorting
The `/changes/` endpoint returns entries sorted in reverse chronological 
order by default (most recent first). Specifying the parameter `sort=1` will 
reverse the default sort order, giving the oldest entries first. An example of returning 
 20 entries since *Sunday, 1 May 2022 00:00:00* starting with the oldest would be:

`GET /changes/{dataset-id}?timestamp=1651363200&limit=20&sort=1`


### Notes for installing SPARQL add-on module

* Ensure you are running **v0.7.4** or later which includes support for add-on modules
* From the root of your API Factory installation, run: `composer require mkdf/api-factory-sparql`
* Copy the additional sparql configuration from config/autoload/local.php.dist into your main local.php file, specifying the location and port number for your Blazegraph installation and also any prefix that is used for graph namespaces (as configured in rdf.uploader)
* Uncomment `'APIF\Sparql'` from config/modules.config.php
