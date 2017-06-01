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
: ${HTTPLOG_FACILITY:=${0##*/}}
: ${HTTPLOG_BASEURL:="https://log.w3tools.de/$( cat /etc/httplog-hostid )/${HTTPLOG_FACILITY}"}

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
#!/bin/sh

HTTPLOG_FACILITY="$1"

. /etc/default/httplog

httplog_send_message "$2" "$3"
```

### /usr/local/bin/foobar

```bash
#!/bin/sh

. /etc/default/httplog

httplog_send_message INFO 'Hello World!'
```

### httplog.js (WSH)

```javascript
/*
%SystemRoot%\system32\wscript.exe httplog.js "FACILITY" "LEVEL" "MESSAGE"
%SystemRoot%\system32\cscript.exe //nologo httplog.js "FACILITY" "LEVEL" "MESSAGE"
*/
var baseurl = 'https://log.exaple.com';
var hostid = 'ACME_Ltd:host.example.com:xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';

var facility = WScript.Arguments.Unnamed(0);
var level = WScript.Arguments.Unnamed(1);
var message = WScript.Arguments.Unnamed(2).replace( '&' , '%26' );

var hosttime = ( function( date ) {
    var tz_offset = -date.getTimezoneOffset();
    var tz_sign = tz_offset < 0 ? ( tz_offset = -tz_offset, '-' ) : '+';
    return (
        [
            [
                [
                    date.getFullYear(),
                    ( '0' + ( 1 + date.getMonth() ) ).slice( -2 ),
                    ( '0' + date.getDate() ).slice( -2 )
                ].join( '-' ),
                [
                    ( '0' + date.getHours() ).slice( -2 ),
                    ( '0' + date.getMinutes() ).slice( -2 ),
                    [ ( '0' + date.getSeconds() ).slice( -2 ),
                      ( '000' + date.getMilliseconds() ).slice( -3 )
                    ].join( '.' )
                ].join( ':' )
            ].join( 'T' ),
            tz_sign,
            [
                ( '0' + parseInt( tz_offset / 60 ) ).slice( -2 ),
                ( '0' + ( tz_offset % 60 ) ).slice( -2 )
            ].join( ':' )
        ].join( '' )
    );
} )( new Date() );


var url = [ baseurl, hostid, facility, hosttime, level ]. join( '/' ) + '?_=' + message;

var xmldoc = new ActiveXObject( 'Msxml2.DOMDocument.6.0' );
xmldoc.async = false;
var res = xmldoc.load( url );

// WScript.Echo( url );
```
<https://log.example.com/ACME_Ltd:host.example.com:xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx/FACILITY/HOSTTIME/LEVEL?_=MESSAGE>
