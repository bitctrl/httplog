<?php
# httplog - Collect log messages over http
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

$regexp = '#^/+(\w[-\w]+\w)(?::|/+)(\w[-.\w]+\w?)/+(.*?)/+(\w[-+.:\w]+\w)/+(.*?)(?:/|$)#';
preg_match( $regexp, $_SERVER['PATH_INFO'] . '/INFO', $m ) or die( 'Forbidden' );

extract( array_combine( [ 'token', 'hostname', 'facility', 'hosttime', 'level' ], array_slice( $m, 1 ) ) );

$tokens_rel = 'access';

file_exists( dirname( $_SERVER['SCRIPT_FILENAME'] ) . "/$tokens_rel/$token/$hostname" ) or die( 'Forbidden' );

header( 'Status: 400' );

$dbname = 'weblogger';
$dbuser = 'weblogger';
$dbpass = 'none';
$dbtable = 'log';

ini_set( 'request_order', 'PG' );

pg_insert( pg_connect( "host=localhost dbname=$dbname user=$dbuser password=$dbpass" ), $dbtable, [
    'hostname' => $hostname,
    'hosttime' => $hosttime,
    'facility' => $facility,
    'level'    => $level,
    'message'  => $_REQUEST[ '_' ]
  ]) or die( 'Bad Request' );

header( 'Status: 200' );
header( 'Content-Type: text/plain' );
echo 'OK';
