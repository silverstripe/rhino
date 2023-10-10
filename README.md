## Rhino

Provides and up to date status of supported modules with a series of statically generated tables which regularly rebuilt using using ququedjobs

## Filtering via querystring

https://rhino.test/tables?t=merged-prs&filters={"authorType"%3A"!product %26%26 !bot"}

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

Only accessing public repos, create a GitHub token with the NO scopes ticked. For uat/product, use a machine user to create the token.

## Environment variables

It is necessary to add the following environment variables so that requests can be made to the GitHub API.

```
GITHUB_USER="xyz"
GITHUB_TOKEN="123"
```

## UAT queuedjobs

Queuedjobs in UAT will intentionally not auto-generate because of some logic in `AbstractLoggableJob`. This was done to prevent lots of unnecessary GitHub API requests being made. To get a job to run in UAT, manually create and run it from within the jobs admin.
