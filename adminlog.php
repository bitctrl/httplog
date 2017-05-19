<?php
# httplog - Collect log messages over http - adminlog.php
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

file_exists( dirname( __FILE__ ) . "/$access_rel/$token/$user.d/admin" ) or die( 'Forbidden' );

header( 'Status: 400' );

$action = $_POST[ 'action' ];

if ( $action == 'save' ) {
  $create_token = $_POST[ 'create_token' ];
  $grantee = $_POST[ 'grantee' ];
  $write = !! $_POST[ 'write' ];
  $admin = $_POST[ 'admin' ];
  $query = $_POST[ 'query' ];

  @mkdir( "$access_rel/$create_token" );
  if ( $write ) {
    file_put_contents( "$access_rel/$create_token/$grantee", '' );
  } else {
    @unlink( "$access_rel/$create_token/$grantee" );
  }
  if ( !! $query ) {
    @mkdir( "$access_rel/$create_token/$grantee.d" );
    file_put_contents( "$access_rel/$create_token/$grantee.d/query", $query );
  } else {
    @unlink( "$access_rel/$create_token/$grantee.d/query" );
    @rmdir( "$access_rel/$create_token/$grantee.d" );
  }
  if ( !! $admin ) {
    @mkdir( "$access_rel/$create_token/$grantee.d" );
    file_put_contents( "$access_rel/$create_token/$grantee.d/admin", $force_query );
  } else {
    @unlink( "$access_rel/$create_token/$grantee.d/admin" );
    @rmdir( "$access_rel/$create_token/$grantee.d" );
  }
  @rmdir( "$access_rel/$create_token" );
  header( 'Status: 301' );
  header( 'Location: ' . $_SERVER[ 'SCRIPT_URI' ] . "?a=$token:$user" );
  die();
} elseif ( $action == 'delete' ) {
  #print_r( $_POST ); die();
  foreach ( $_POST[ 'grantee' ] as $grantee ) {
    preg_match( '/(.*?):(.*)/', $grantee, $m );
    $delete_token = $m[ 1 ];
    $grantee = $m[ 2 ];
    @unlink( dirname( __FILE__ ) . "/$access_rel/$delete_token/$grantee.d/admin" );
    @unlink( dirname( __FILE__ ) . "/$access_rel/$delete_token/$grantee.d/query" );
    @rmdir( dirname( __FILE__ ) . "/$access_rel/$delete_token/$grantee.d" );
    @unlink( dirname( __FILE__ ) . "/$access_rel/$delete_token/$grantee" );
    @rmdir( dirname( __FILE__ ) . "/$access_rel/$delete_token" );
  }
  header( 'Status: 301' );
  header( 'Location: ' . $_SERVER[ 'SCRIPT_URI' ] . "?a=$token:$user" );
  die();
}

?>
<html lang="en">
<head>
<title>adminlog</title>
<style>
table { font-family: Consolas,monospace; }
.save label, .save input { display: block; }
.save input[type="text"] { width: 100%; }
tbody th { text-align: left; }
tbody tr:hover { background-color: antiquewhite; }
a { color: blue; }
</style>
</head>
<body>
<form method="post" class="save">
<input name="a" type="hidden" value="<?= htmlspecialchars( "$token:$user" ) ?>">
<label>token: <input name="create_token" type="text" value="<?= `uuidgen`?>"></label>
<label>grantee: <input name="grantee" type="text"></label>
<label>write: <input name="write" type="checkbox"></label>
<label>query: <input name="query" type="text"></label>
<label>admin: <input name="admin" type="checkbox"></label>
<input name="action" type="submit" value="save">
</form>
<form method="post">
<table border="1">
<thead>
<tr>
<th>grantee</th><th>admin</th><th>write</th><th>query</th></tr>
</thead>
<tbody>
<?php

header( 'Status: 500' );

$access_rel = 'access';

$token_iter = new DirectoryIterator( dirname( __FILE__ ) . '/access' );
foreach ( $token_iter as $f ) {
  if ( $f->isDot() ) continue;
  if ( $f->isDir() ) {
    $token = $f->getFileName();
    echo "<tr><th colspan=\"4\">$token</th></tr>\n";
    $grantee_iter = new DirectoryIterator( dirname( __FILE__ ) ."/$access_rel/$token" );
    foreach ( $grantee_iter as $f ) {
      if ( $f->isDot() ) continue;
      $grantee = $f->getFileName();
      if ( $f->isDir() ) {
        if ( preg_match( '/^(.*)\.d$/', $grantee, $m ) ) {
          $grantee = $m[1];
          $force_query = @file_get_contents( dirname( __FILE__ ) ."/$access_rel/$token/$grantee.d/query" );
          $is_admin = file_exists( dirname( __FILE__) . "/$access_rel/$token/$grantee.d/admin" ) ? 'yes' : '';
          if ( file_exists( dirname( __FILE__ ) . "/$access_rel/$token/$grantee" ) ) {
            $write_link = "<a href=\"\">yes</a>";
          } else {
            $write_link = '';
          }
        }
      } elseif ( $f->isFile() && ! is_dir( dirname( __FILE__ ) . "/$access_rel/$token/$grantee.d" ) ) {
        $write_link = "<a href=\"https://${_SERVER[HTTP_HOST]}/$token:$grantee/FACILITY/TIMESTAMP/LEVEL?_=MESSAGE\">yes</a>";
        $force_query = '';
        $is_admin = '';
      } else {
        continue;
      }
      echo "<tr><td><label><input name=\"grantee[]\" type=\"checkbox\" value=\"$token:$grantee\">$grantee</label></td><td>$is_admin</td><td>$write_link</td><td>$force_query</td></th>\n";
    }
  }
}

header( 'Status: 200' );

?>
</tbody>
</table>
<input name="action" type="submit" value="delete">
</form>
</body>
</html>
