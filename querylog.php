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
header( 'Status: 403' );

$regexp = '#^(\w[-\w]+\w)[:/](\w+)$#';
preg_match( $regexp, $_REQUEST[ 'a' ], $m ) or die( 'Forbidden' );

extract( array_combine( [ 'token', 'user' ], array_slice( $m, 1 ) ) );

$access_rel = 'access';

$filter = trim( file_get_contents(
    dirname( $_SERVER['SCRIPT_FILENAME'] ) . "/$access_rel/$token/$user.d/query" ) ) or die( 'Forbidden' );

$dbname = 'weblogger';
$dbuser = $filter != 'true' ? 'weblogger.query' : 'weblogger';
$dbpass = 'none';
$dbtable = 'log';
$where = trim( $_REQUEST[ 'q' ] );
if ( $where == '' ) {
	$where = 'true';
}

header( 'Status: 400' );

#preg_match( '/[\'()\w\s]/', $where ) or die( '</body></html>' );

$query = "select logid, logctime, hostname, hosttime, facility, level, message from $dbtable " .
    "where ( $filter ) and ( $where ) order by logctime desc limit 500;";

$result = pg_query(
    pg_connect( "host=localhost dbname=$dbname user=$dbuser password=$dbpass" ), $query ) or die( 'Bad Request' );

header( 'Status: 200' );

?>
<html lang="en">
<head>
<title>querylog</title>
<style>
tbody tr:hover { background-color: antiquewhite; }
</style>
</head>
<body>
<form method="GET">
<input name="a" type="hidden" value="<?= htmlspecialchars( "$token:$user" ) ?>">
<textarea name="q" autofocus style="width: 100%; height: 4em;"><?= htmlspecialchars( $_REQUEST[ 'q' ] ) ?></textarea>
<input type="submit">
</form>
<table border="1">
<thead>
<tr>
<?= $filter != 'true' ? '' : '<th>logid</th>' ?><th>logctime</th><th>hosttime</th><th>hostname</th><th>facility</th><th>level</th><th>message</th></tr>
</thead>
<tbody>
<?php
if ( $filter != 'true' ) {
  while( $row = pg_fetch_assoc( $result ) ) { 
    printf( "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
      $row[ 'logctime' ],
      $row[ 'hosttime' ],
      $row[ 'hostname' ],
      $row[ 'facility' ],
      $row[ 'level' ],
      htmlspecialchars( $row[ 'message' ]
      ));
  }
} else {
  while( $row = pg_fetch_assoc( $result ) ) { 
    printf( "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
      $row[ 'logid' ],
      $row[ 'logctime' ],
      $row[ 'hosttime' ],
      $row[ 'hostname' ],
      $row[ 'facility' ],
      $row[ 'level' ],
      htmlspecialchars( $row[ 'message' ]
      ));
  }
}
?>
</tbody>
</table>
</body>
</html>
