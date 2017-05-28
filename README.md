# httplog

## Apache Virtual Host

```apache
ErrorDocument 400 "Bad Request"

RewriteEngine on

RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}  -f
RewriteRule .* - [R=400,L]
RewriteRule ^/(?:(query|admin)/)?(.*) /$1log.php/$2
```

## Database

```sql
-- Role: @httplog

-- DROP ROLE "@httplog";

CREATE ROLE "@httplog"
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE NOREPLICATION;

-- Role: httplog

-- DROP ROLE httplog;

CREATE ROLE httplog LOGIN
  PASSWORD 'none'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE NOREPLICATION;
GRANT "@httplog" TO httplog;

-- Database: httplog

-- DROP DATABASE httplog;

CREATE DATABASE httplog
  WITH OWNER = postgres
       ENCODING = 'UTF8'
       TABLESPACE = pg_default
       LC_COLLATE = 'C'
       LC_CTYPE = 'C'
       CONNECTION LIMIT = -1;

-- Table: httplog

-- DROP TABLE httplog;

CREATE TABLE httplog
(
  logid bigserial NOT NULL,
  logtime timestamp with time zone NOT NULL DEFAULT now(),
  hosttime timestamp with time zone NOT NULL,
  hostname character varying NOT NULL,
  facility character varying NOT NULL,
  level character varying NOT NULL,
  message character varying NOT NULL,
  remote_addr character varying NOT NULL,
  groupname character varying NOT NULL,
  CONSTRAINT httplog_pkey PRIMARY KEY (logid)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE httplog
  OWNER TO postgres;
GRANT ALL ON TABLE httplog TO postgres;
GRANT SELECT, INSERT ON TABLE httplog TO "@httplog";
```

## Client

## /etc/default/httplog

```bash
: ${HTTPLOG_BASEURL:="https://log.example.com/$( cat /etc/httplog-hostid )/${HTTPLOG_FACILITY:=${0##*/}}"}

httplog_send_message() {
  local ts=$( date +%FT%T%z )
  local level=${1:-INFO}
  local url="$HTTPLOG_BASEURL/$ts/$level"
  local message=${2:-<empty>}
  # echo "curl --insecure --max-time 10 -G \"$url\" --data-urlencode \"_=$message\""
  curl --insecure --max-time 10 -G "$url" --data-urlencode "_=$message" >/dev/null 2>&1
  echo "$ts $HTTPLOG_FACILITY $level $message"
}
```

### /etc/httplog-hostid

```
ACME_Ltd:host.example.com:xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

### /usr/local/bin/httplog

```bash
#!/bin/bash

HTTPLOG_FACILITY="$1"

. /etc/default/httplog

httplog_send_message "$2" "$3"
```

### /usr/local/bin/foobar

```bash
#!/bin/bash

. /etc/default/httplog

httplog_send_message INFO 'Hello World!'
```

<https://log.example.com/ACME_Ltd:host.example.com:xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx/FACILITY/HOSTTIME/LEVEL?_=MESSAGE>
