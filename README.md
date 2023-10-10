## Rhino

Provides and up to date status of supported modules with a series of statically generated tables which regularly rebuilt using using ququedjobs

## Filtering via querystring

Use the following querystring syntax to select a table and filter the results - this allow you to to bookmark filters:

`?t=merged-prs&filters={"authorType"%3A"!product%20%26%26%20!bot"}`

## Docker commands

**Start containers**

docker-compose up --build -d

**Stop containers**

docker-compose down

**SSH in as root**

docker exec -it rhino_webserver /bin/bash

**SSH in as www-data user**

docker exec -it rhino_webserver sh -c "cd /var/www && su -s /bin/bash www-data"

## Log in to database from host

`mysql -uroot -proot -h0.0.0.0 -P3398 -DSS_mysite`

## GitHub API token

Rhino is designed to only access public repos, therefore the GitHub API token should have zero permissions. The token is used purely to increase the allowed API rate limit.

Create a [new GitHub token](https://github.com/settings/tokens/new) with NONE of the checkboxes ticked.

When creating the the token on a non-dev environment such as uat or prod, the token should be created against a machine user that has zero access to anything.

## Environment variables

It is necessary to add the following environment variables so that requests can be made to the GitHub API.

These should be saved as secrets in the webhost i.e. not visible to anyone after you save them.

```
GITHUB_USER="xyz"
GITHUB_TOKEN="123"
```

## Test environment queuedjobs

Queued jobs on test environments (i.e. UAT) will intentionally not recreate themselves because of some logic in `AbstractLoggableJob`. This was done to prevent lots of unnecessary GitHub API requests being made. To get a job to run in UAT, manually create and run it from within the jobs admin.

