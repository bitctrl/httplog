<?php
# httplog - Collect log messages over http - querylog.php
#
# Copyright 2017 BitCtrl Systems GmbH <https://www.bitctrl.de>
# Copyright 2017 Daniel Hammerschmidt <daniel@redneck-engineering.com>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
#
# 3. Neither the name of the copyright holder nor the names of its
#    contributors may be used to endorse or promote products derived
#    from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
# "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
# LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
# A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
# HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
# INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
# BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
# CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY
# WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

header( 'Status: 400' ); $status = 'Bad Request';

$regexp = '#^/(\w[-.\w]+\w):(\w[-.\w]+\w):(\w[-\w]+\w)$#';
preg_match( $regexp, $_SERVER['PATH_INFO'], $m ) or die( $status );
list( $_, $group, $view, $key ) = $m;

require( 'log.conf.php' );

$group_dir = "$access_dir/$group";

$viewfilter = trim( @file_get_contents( "$group_dir/views/$view.$key" ) ) or die( $status );

if ( $super = is_link( "$group_dir/groups" ) ) {
    $groups = 'true';
} else {
    @$groups = array_filter( scandir( "$group_dir/groups/" ), function( $x ){ return $x[ 0 ] !== '.'; } );
    $groups or ( $groups = [ $group ] );
    $groups = 'groupname in (' . implode( ',', array_map( function( $x ){ return "'$x'"; }, $groups ) ) . ')';
} 
$groupfilter = trim( @file_get_contents( "$group_dir/filter" ) ) or $groupfilter = 'true';

if ( $group === 'w3tools.de' ) {
    ini_set( 'display_errors', 'on' );
}

$qryfilter = ( $_REQUEST[ 'q' ] ? check_sql_filter( $_REQUEST[ 'q' ] ) : 'true' ) or die( $status );

#TODO: name of view ?
$pgview = 'view_' . sha1( "${group}_${view}_${qryfilter}" ); 

@pg_connect( "host=localhost dbname=$dbname user=$dbuser password=$dbpass" ) or die( $status );

$query = <<<EOT
create temporary view "$pgview" as
    select
        logid, logtime, hosttime, remote_addr, hostname, facility, level, message
      from $dbtable
      where (
        ( $groups )
        and
        ( $groupfilter )
        and
        ( $viewfilter )
        and
        ( $qryfilter )
      )
      order by logtime desc;
EOT;

@pg_query( $query ) or die( $status . $query);

$result = @pg_query( "select * from \"$pgview\" limit 500;" ) or die( $status );

@pg_query( "drop view \"$pgview\";" ) or die( $status );

header( 'Status: 200' ); $status = 'OK';

$rows =substr_count( $_REQUEST[ 'q' ], "\n" ) + 3;
?>
<html lang="en">
<head>
<title><?= "$view - $group - querylog" ?></title>
<style>
body { background: #fff; color: #000; }
tr > th:first-of-type, tr > td:first-of-type { display: none; }
.super tr > th:first-of-type, .super tr > td:first-of-type { display: table-cell; }
td { width: 1%; }
td:last-of-type { width: 100%; }
th:nth-of-type( 1 ), td:nth-of-type( 1 ),
th:nth-of-type( 4 ), td:nth-of-type( 4 )
 { font-size: xx-small; }
th:nth-of-type( 7 ),
td:nth-of-type( 7 ) { font-size: small; }
tr.level_INFO          { background-color: #ddf; }
tr.level_INFO:hover    { background-color: #ccf; }
tr.level_SUCCESS       { background-color: #dfd; }
tr.level_SUCCESS:hover { background-color: #bfb; }
tr.level_ERROR         { background-color: #fdd; color: #000; }
tr.level_ERROR:hover   { background-color: #fbb; color: #000; }
</style>
</head>
<body>
<form method="GET">
<textarea name="q" rows="<?= $rows ?>" autofocus style="width: 100%;"><?= htmlspecialchars( $_REQUEST[ 'q' ] ) ?></textarea>
<input type="submit" value="Filter">
<tt> '/' = E'\x2f'; '-' = E'\x2d' ';' = E'\x3b', '(' = E'\x28', 'e' = E'\x45', 'E' = E'\x65'</tt>
</form>
<?= /* FIXME: 4dev */ false ? "<pre>$query</pre>" : '' ?>
<table border="1"<?= $super ? 'class="super"' : '' ?>>
<thead>
<tr>
<th>logid</th><th>logtime</th><th>hosttime</th><th>remote_addr</th><th>hostname</th><th>facility</th><th>level</th><th>message</th></tr>
</thead>
<tbody>
<?php
while( $row = pg_fetch_assoc( $result ) ) { 
$level = $row[ 'level' ];
printf( "<tr class=\"level_$level\"><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
  $super ? $row[ 'logid' ] : '',
  $row[ 'logtime' ],
  $row[ 'hosttime' ],
  $row[ 'remote_addr' ],
  #implode( '<wbr>.', explode( '.', $row[ 'hostname' ], 2 ) ),
  preg_replace( '/\./', '<wbr>.', $row[ 'hostname' ], 2 ),
  $row[ 'facility' ],
  $level,
  htmlspecialchars( $row[ 'message' ]
  ));
}
?>
</tbody>
</table>
<script>
addEventListener( 'keydown', function( event ){
    if ( event.key === 'F5' && !event.ctrlKey && !event.shiftKey && !event.shiftKey ) {
        event.preventDefault();
        document.forms[ 0 ].submit();
    }
});
</script>
</body>
</html>
