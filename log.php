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

header( 'Status: 400' ); $status = 'Bad Request';

$regexp = '#^/(\w[-.\w]+\w):(\w[-.\w]+\w):(\w[-\w]+\w)/+(.*?)/+(\w[-+.:\w]+\w)/+(.*?)(?:/|$)#';
preg_match( $regexp, $_SERVER['PATH_INFO'] . '/INFO', $m ) or die( $status );
list( $_, $groupname, $hostname, $key, $facility, $hosttime, $level ) = $m;

require( 'log.conf.php' );

$host_base = "$access_dir/$groupname/hosts/$hostname";

file_exists( "$host_base.$key" ) or die( $status );

ini_set( 'request_order', 'PG' );

pg_insert( pg_connect( "host=localhost dbname=$dbname user=$dbuser password=$dbpass" ), $dbtable, [
    'remote_addr' => $_SERVER[ 'REMOTE_ADDR' ],
    'groupname'   => $groupname,
    'hostname' => $hostname,
    'hosttime' => $hosttime,
    'facility' => $facility,
    'level'    => $level,
    'message'  => $_REQUEST[ '_' ]
  ]) or die( $status );

@include( "$host_base.$facility.php" ) || @include( "$host_base.php" );

header( 'Status: 200' ); $status = 'OK';
header( 'Content-Type: text/plain' );
echo $status;
