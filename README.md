## Rhino

Provides and up to date status of supported modules with a series of statically generated tables which regularly rebuilt using using ququedjobs

## Filtering via querystring

https://emtek.net.nz/rhino/tables?t=merged-prs&filters={"authorType"%3A"!product %26%26 !bot"}

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

Only accessing public repos, use a create a token for a GitHub machine userwith the NO scopes ticked for the API token

## Environment variables

```
GITHUB_USER="xyz"
GITHUB_TOKEN="123"
```