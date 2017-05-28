<?php
$dbname  = 'httplog';
$dbtable = 'httplog';
$dbuser  = 'httplog';
$dbpass  = 'none';

$access_rel = 'access';
$access_dir = dirname( __FILE__ ) . "/$access_rel";

function check_sql_filter( $filter ) {
    # strip comments
    $filter = preg_replace( '#/\*.*?\*/|s*--.*?([\r\n]+|$)#ms', ' ', $filter );
    # insert spaces before left parenthesis
    $filter = preg_replace( '#\(#', ' (', $filter );
    # replace (consecutive) white-spaces by one space
    $filter = preg_replace( '#\s+#ms', ' ', $filter );
    # deny semicolons and function calls
    if ( preg_match( '#;|select|insert|(?<!^|\bor|\band|\bin|[<>=(]) \(#i', $filter ) ) {
        return false;
    } else {
        return $filter;
    }
}
