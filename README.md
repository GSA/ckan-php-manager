ckan-php-manager
================

[![Build Status](https://travis-ci.org/GSA/ckan-php-manager.svg?branch=master)](https://travis-ci.org/GSA/ckan-php-manager)
[![Codacy Badge](https://api.codacy.com/project/badge/a07828e07ef9416583a88beedf6ff072)](https://www.codacy.com/app/alexandr-perfilov/ckan-php-manager)
[![Join the chat at https://gitter.im/GSA/ckan-php-manager](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/GSA/ckan-php-manager?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

A bunch of scripts to perform tasks using CKAN API and https://github.com/GSA/ckan-php-client

## Requirements

* PHP 7.0+ : <http://php.net>

## Installation

### Clone repository
    $ git clone https://github.com/GSA/ckan-php-manager.git

### Composer
Use [composer](#composer) to install/update dependencies
If you don't have Composer [install](https://getcomposer.org/download/) it:

    $ curl -sS https://getcomposer.org/installer | php

#### Run composer self-udpate

    $ composer self-update

#### Refresh your dependencies:

    $ php composer.phar update

### Configuration
Copy config.sample.php to config.php. Update it with your custom values, if needed.

    $ cp inc/config.sample.php inc/config.php

## Usage

### Export all packages by Agency name, including all Sub Agencies

* Update `cli/export_packages_by_org.php`, editing the title of exported organization ORGANIZATION_TO_EXPORT
* Run importer using php

```
    $ php cli/export_packages_by_org.php
```

Script is taking all terms, including sub-agencies from http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json and makes CKAN requests,
looking for packages by these organization list.

Results can be found in /results/{timestamp} dir after script finished its work, including `_{term}.log` with package counts for each agency.

### DMS legacy tag

To add tag `add_legacy_dms_and_make_private` to all datasets of some group:

* Update ORGANIZATION_TO_TAG in the `cli/add_legacy_dms_and_make_private.php`
* Double check CKAN_URL and CKAN_API_KEY for editing datasets
* Run script

```
    $ php cli/add_legacy_dms_and_make_private.php
```

### Assign groups and category tags to datasets

* Put csv files to /data dir, with `assign_<any-title>.csv` (must have `assign_` prefix)
    The format of these files must be:
    `dataset, group, categories`

    First line is caption, leave the first line in each file:
    `dataset,group,categories`

    Then put one dataset per line.

    1. Dataset can be:
      * Dataset url, ex. https://catalog.data.gov/dataset/food-access-research-atlas
      * Dataset name, ex. download-crossing-inventory-data-highway-rail-crossing
      * Dataset id

    2. Group
    just one group per line. If you need to add multiple groups, you must create another row in csv with same dataset and another group,
    because all the categories are tagged by current row group. Make sure your group exist in your CKAN instance (to list all
    existing groups, go to http://catalog.data.gov/api/3/action/group_list?all_fields=true , replacing `catalog.data.gov` with your
     CKAN domain)

    3. Categories
    one of multiple categories per current row group, separated by semicolon `;`

    Example csv file:

    ```
    dataset, group, categories
    https://catalog.data.gov/dataset/food-access-research-atlas,Agriculture,"Natural Resources and Environment"
    download-crossing-inventory-data-highway-rail-crossing,Agriculture, "Natural Resources and Environment;Plants and Plant Systems Agriculture"
    ```
* Double check CKAN_URL and CKAN_API_KEY for editing datasets
* Run script

```
    $ php cli/tagging/assign_groups_and_tags.php
```

### Remove groups and category tags from datasets (revert previous script changes)

* Prepare same csv file as for previous script, and put them to /data dir, with `<any-title>.csv`

```
    $ php cli/tagging/remove_groups_and_tags.php
```

## CKAN API DOCs

http://docs.ckan.org/en/latest/api/index.html
