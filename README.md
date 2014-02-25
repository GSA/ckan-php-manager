ckan-php-manager
================

## API DOCs

http://docs.ckan.org/en/latest/api/index.html


## Requirements

* PHP 5.4+ : <http://php.net>

## Installation

Use [composer](#composer) to install/update dependencies

### Composer

If you don't have Composer [install](https://getcomposer.org/download/) it:

    $ curl -sS https://getcomposer.org/installer | php

Refresh your dependencies:

    $ php composer.phar update


## Usage

* Update `cli/export_packages_by_org.php`, editing the title of exported organization ORGANIZATION_TO_EXPORT
* Run importer using php

```
    $ php cli/export_packages_by_org.php
```

Script is taking all terms, including sub-agencies from http://idm.data.gov/fed_agency.json and makes CKAN requests,
looking for packages by these organization list.

Results can be found in /results dir after script finished its work.


