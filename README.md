# httplog

## Apache Virtual Host

```apache
ErrorDocument 403 "Forbidden"

RewriteEngine on

RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}  !-f
RewriteRule .* /log.php$0
RewriteRule !/(query|admin)?log.php(/|$) - [F,L]
```

## /etc/default/httplog
```bash
: ${LOG_BASEURL:="https://log.example.com/log.php/$( cat /etc/httplog-hostid )/${LOG_FACILITY:=${0##*/}}"}

httplog_send_message() {
  local ts=$( date +%FT%T%z )
  curl --insecure --max-time 10 -G "$LOG_BASEURL/$ts/${1:-INFO}" --data-urlencode "_=${2:-<empty>}" >/dev/null 2>&1
  echo "$ts $LOG_FACILITY ${1:-INFO} ${2:-<empty>}"
}
```

## /etc/httplog-hostid
```
b95eb916-xxxx-xxxx-xxxxxxxxxxxxxxxxx:host.example.com
```

## /usr/local/bin/foobar
```bash
#!/bin/bash

. /etc/default/httplog

httplog_send_message INFO 'Hello World!'
```
