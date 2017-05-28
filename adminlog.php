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

header( 'Status: 400' ); $status = 'Bad Request';

$regexp = '#^/(\w[-.\w]+\w):(\w[-.\w]+\w):(\w[-\w]+\w)$#';
preg_match( $regexp, $_SERVER['PATH_INFO'], $m ) or die( $status );
list( $_, $group, $admin, $key ) = $m;

require( 'log.conf.php' );

$dir = "$access_dir/$group";

function filter_dot( $x ) {
    return $x[ 0 ] !== '.';
}

file_exists( "$dir/admins/$admin.$key" ) or die( $status );
$superadmin = is_link( "$dir/groups" );

if ( $_REQUEST[ 'do' ] ) {
    $group2 = $_REQUEST[ 'group' ];
    preg_match( '/^(\w[-.\w]+\w)$/', $group2 ) or die( $status . 1);
    $name = $_REQUEST[ 'name' ];
    preg_match( '/^(\w[-.\w]+\w)$/', $name ) or die( $status . 2 );
    $dir2 = "$access_dir/$group" . ( $group === $group2 ? '' : "/groups/$group2" );
    
    if ( $_REQUEST[ 'type' ] === 'host' ) {
        if ( $_REQUEST[ 'do' ] === 'create' ) {
            $key = trim( `uuidgen` );
            @touch( "$dir2/hosts/$name.$key" ) or die( $status );
        } elseif ( $_REQUEST[ 'do' ] === 'delete' ) {
            @unlink( "$dir2/hosts/$name" ) or die( $status );
        }
    } elseif ( $_REQUEST[ 'type' ] === 'admin' ) {
        if ( $_REQUEST[ 'do' ] === 'create' ) {
            $key = trim( `pwgen` );
            @touch( "$dir2/admins/$name.$key" ) or die( $status );
        } elseif ( $_REQUEST[ 'do' ] === 'delete' ) {
            @( "$group2/$name" !== "$group/$admin.$key" ) and unlink( "$dir2/admins/$name" ) or die( $status );
        }
    } elseif ( $_REQUEST[ 'type' ] === 'view' ) {
        if ( $_REQUEST[ 'do' ] === 'create' ) {
            check_sql_filter( $_REQUEST[ 'value' ] ) or die( $status );
            $key = trim( `pwgen` );
            @file_put_contents( "$dir2/views/$name.$key", $_REQUEST[ 'value' ] ) !== false or die( $status );
        } elseif ( $_REQUEST[ 'do' ] === 'delete' ) {
            @unlink( "$dir2/views/$name" ) or die( $status );
        }
    } elseif ( $_REQUEST[ 'type' ] === 'filter' ) {
        if ( $_REQUEST[ 'do' ] === 'save' ) {
            is_link( "$access_dir/$group/groups" ) or die( $status );
            check_sql_filter( $_REQUEST[ 'value' ] ) or die( $status );
            @file_put_contents( "$dir2/filter", $_REQUEST[ 'value' ] ) !== false or die( $status );
        }
    } elseif ( $_REQUEST[ 'type' ] === 'dir' ) {
        // $superadmin or die ( $status );
        is_dir( "$access_dir/$group/$name" ) or die( $status );
        if ( $_REQUEST[ 'do' ] === 'create' ) {
            @mkdir( "$dir2/$name" ) or die( $status );
        } elseif ( $_REQUEST[ 'do' ] === 'delete' ) {
            if ( is_link( "$dir2/$name" ) ) {
                @unlink( "$dir2/$name" ) or die( $status );
            } else {
                @rmdir( "$dir2/$name" ) or die( $status );
            } 
        } elseif ( $_REQUEST[ 'do' ] === 'grant all' && $name === 'groups' ) {
            is_link( "$access_dir/$group/groups" ) or die ( $status );
            @symlink( '..', "$dir2/groups" ) or die( $status );
        }
    } elseif ( $_REQUEST[ 'type' ] === 'group' ) {
        if ( $_REQUEST[ 'do' ] === 'create' ) {
            // $superadmin or die ( $status );
            file_exists( "$access_dir/$group/groups" ) or die( $status );
            $superadmin or ( $name = "$group.$name" );
            @mkdir( "$access_dir/$name" ) or die( $status );
            if ( @symlink( "../../$name", "$access_dir/$group/groups/$name" ) ) {
                mkdir( "$access_dir/$name/refs" );
                symlink( "../../$group", "$access_dir/$name/refs/$group" );
                @copy( "$access_dir/$group/filter", "$access_dir/$name/filter" );
            };
            $group2 = $name;
        } elseif ( $_REQUEST[ 'do' ] === 'delete' ) {
            // $superadmin or die ( $status );
            $target = "$access_dir/$group/groups/$name";
            is_dir( $target ) or is_link( $target ) or die( $status );
            // FIXME: remove dangling symlinks
            @rmdir( "$access_dir/$name/admins" );
            @unlink( "$access_dir/$name/filter" );
            @rmdir( "$access_dir/$name/views" );
            @rmdir( "$access_dir/$name/hosts" );
            @rmdir( "$access_dir/$name/groups" );
            @unlink( "$access_dir/$name/refs/$group" );
            @rmdir( "$access_dir/$name/refs" );
            @unlink( "$target" );
            @rmdir( "$access_dir/$name" ) or die( $status );
            $group2 = false;
        } elseif ( $_REQUEST[ 'do' ] === 'add' ) {
            $target = "$access_dir/$group/groups/$name";
            is_dir( $target ) or is_link( $target ) or die( $status );
            $target = "../../$name";
            $link = "$access_dir/$group/groups/$group2/groups/$name";
            @symlink( $target, $link ) or die( $status );
            @mkdir( "$access_dir/$name/refs" );
            symlink( "../../$group2", "$access_dir/$name/refs/$group2" );
        } elseif ( $_REQUEST[ 'do' ] === 'remove' ) {
            ( $group === $group2 ) and ( $group === $name ) and die( $status . 1 );
            @unlink( "$access_dir/$group/groups/$group2/groups/$name" ) or die( $status . 2 );
            unlink( "$access_dir/$name/refs/$group2" );
            @rmdir( "$access_dir/$name/refs" );
        }
    }
    
    header( 'Status: 303' );
    header( "Location: ${_SERVER[ SCRIPT_URI ]}#$group2" );
    die( 'See Other' );
}

$groups = @array_merge( @array_filter( @scandir( "$dir/groups/" ), filter_dot ) ) or $groups = [ $group ];
sort( $groups, SORT_NATURAL | SORT_FLAG_CASE );

$data = [ 'group' => $group, 'access' => [] ];
$superadmin and ( $data[ 'superadmin' ] = true );

foreach ( $groups as $group ) {
    $dir = "$access_dir/$group";
    $data[ 'access' ][ $group ] = [];
    $subdirs = [ 'hosts', 'admins' ];
    if ( is_link( "$dir/groups" ) ) {
        $data[ 'access' ][ $group ][ 'groups' ] = true;
    } else {
        $subdirs[] = 'groups';
    }
    foreach ( $subdirs as $x ) {
        if ( $content = @scandir( "$dir/$x/" ) ) {
            $data[ 'access' ][ $group ][ $x ] = array_merge( array_filter( $content, filter_dot ) );
        }
    }
    if ( $content = @scandir( "$dir/views/" ) ) {
        $data[ 'access' ][ $group ][ 'views' ] = [];
        foreach ( array_merge( array_filter( $content, filter_dot ) ) as $x ) {
            $data[ 'access' ][ $group ][ 'views' ][ $x ] = file_get_contents( "$dir/views/$x" );
        }
    }
    if ( $content = @file_get_contents( "$dir/filter" ) ) {
        $data[ 'access' ][ $group ][ 'filter' ] = $content;
    }
    @$data[ 'access' ][ $group ][ 'refs' ] = array_merge( array_filter( scandir( "$dir/refs/" ), filter_dot ) );
}

header( 'Status: 200' ); $status = 'OK';
?>
<html lang="en">
<head>
<title>adminlog</title>
<style>
html { height: 100%; }
body { min-height: 100%; overflow-y: scroll; margin: 0; padding: 8px; box-sizing: border-box; }
pre, form { margin: 0; }
textarea { width: 100%; resize: vertical; }
form { display: inline; }
a { color: blue; }
h2, h3 { margin: 1em 0 0; }
h4 { margin: 0; }
body > div:last-child { height: 100%; }
</style>
<script id="data" type="application/json">
<?= json_encode( $data ); ?>
</script>
</head>
<body>
<script>
"use strict";
var createElement = document.createElement.bind( document );
var createInput = function( type, name, value ) {
    var input = createElement( 'input' );
    input.type = type;
    input.name = name;
    input.value = value;
    return input;
};

var reName = [ /^(.*?)()(~)?$/, /^(.*)\.(.*?)(~)?$/ ];
function parseName( name, hasKey ) {
    var m = name.match( reName[ +!!hasKey ] );
    return { name: m[ 1 ], key: m[ 2 ], disabled: !!m[ 3 ], full: m[ 0 ] };
}

var data = JSON.parse( document.getElementById( 'data' ).text );

var ul = document.body.appendChild( createElement( 'ul' ) );
/**********************************************************************
 * create group
 *********************************************************************/
if ( data.access[ data.group ].groups ) {
    var li = ul.appendChild( createElement( 'li' ) );
    var form = li.appendChild( createElement( 'form' ) );
    form.appendChild( createInput( 'hidden', 'type', 'group' ) );
    form.appendChild( createInput( 'hidden', 'group', 'none' ) );
    form.appendChild( createInput( 'text', 'name', '' ) );
    form.appendChild( createInput( 'submit', 'do', 'create' ) );
}

for ( var group in data.access ) {
    /******************************************************************
     group
     *****************************************************************/
    group = parseName( group );
    var li = ul.appendChild( createElement( 'li' ) );
    if ( group.disabled ) {
        li.classList.add( 'disabled' );
    }
    var h2 = li.appendChild( createElement( 'h2' ) );
    h2.innerText = group.name;
    h2.id = group.name;
    
    if ( data.access[ data.group ].groups ) {
        var form = li.appendChild( createElement( 'form' ) );
        form.appendChild( createInput( 'hidden', 'type', 'group' ) );
        form.appendChild( createInput( 'hidden', 'name', group.full ) );
        form.appendChild( createInput( 'hidden', 'group', 'none' ) );
        form.appendChild( createInput( 'submit', 'do', 'delete' ) );
    }
    /******************************************************************
     directories
     *****************************************************************/
    [ 'hosts', 'groups', 'views', 'admins' ].forEach( function( x ){
        if ( data.access[ data.group ] === true || data.access[ data.group ][ x ] ) {
            var form = li.appendChild( createElement( 'form' ) );
            form.appendChild( createElement( 'span' ) ).innerText = x;
            form.appendChild( createInput( 'hidden', 'type', 'dir' ) );
            form.appendChild( createInput( 'hidden', 'name', x ) );
            form.appendChild( createInput( 'hidden', 'group', group.full ) );
            form.appendChild( createInput( 'submit', 'do', data.access[ group.full ][ x ] ? 'delete' : 'create' ) );
            if ( x === 'groups' && data.access[ data.group ].groups === true && ! data.access[ group.name ].groups ) {
                var form = li.appendChild( createElement( 'form' ) );
                // form.appendChild( createElement( 'span' ) ).innerText = x;
                form.appendChild( createInput( 'hidden', 'type', 'dir' ) );
                form.appendChild( createInput( 'hidden', 'name', 'groups' ) );
                form.appendChild( createInput( 'hidden', 'group', group.full ) );
                form.appendChild( createInput( 'submit', 'do', 'grant all') );
            }
        }
    });
    
    var ul2 = li.appendChild( createElement( 'ul' ) );
    
    /******************************************************************
     hosts
     *****************************************************************/
    if ( data.access[ group.full ].hosts ) {
        var li2 = ul2.appendChild( createElement( 'li' ) );
        li2.appendChild( createElement( 'h3' ) ).innerText = 'hosts';
        var ul3 = li2.appendChild( createElement( 'ul' ) );
        for ( var host in data.access[ group.name ].hosts ) {
            host = parseName( data.access[ group.name ].hosts[ host ], true );
            var li3 = ul3.appendChild( createElement( 'li' ) );
            var link = li3.appendChild( createElement( 'a' ) );
            link.href = `/${group.name}:${host.name}:${host.key}/FACILITY/HOSTTIME/LEVEL?_=MESSAGE`;
            link.innerText = host.name;
            link.target = '_blank';
            var form = li3.appendChild( createElement( 'form' ) );
            form.appendChild( createInput( 'hidden', 'type', 'host' ) );
            form.appendChild( createInput( 'hidden', 'group', group.full ) );
            form.appendChild( createInput( 'hidden', 'name', host.full ) );
            form.appendChild( createInput( 'submit', 'do', 'delete' ) );
            li3.appendChild( createElement( 'div' ) ).appendChild( createElement( 'tt' ) ).innerText = `${group.name}:${host.name}:${host.key}`;
        }
        var li3 = ul3.appendChild( createElement( 'li' ) );
        var form = li3.appendChild( createElement( 'form' ) );
        form.appendChild( createInput( 'hidden', 'type', 'host' ) );
        form.appendChild( createInput( 'hidden', 'group', group.full ) );
        form.appendChild( createInput( 'text', 'name', '' ) );
        form.appendChild( createInput( 'submit', 'do', 'create' ) );
    }
    
    /******************************************************************
     groups
     *****************************************************************/
    if ( data.access[ group.full ].groups ) {
        var li2 = ul2.appendChild( createElement( 'li' ) );
        li2.appendChild( createElement( 'h3' ) ).innerText = 'groups';
        var ul3 = li2.appendChild( createElement( 'ul' ) );
        if ( data.access[ group.name ].groups === true ) {
            var li3 = ul3.appendChild( createElement( 'li' ) );
            li3.appendChild( createElement( 'span' ) ).innerText = 'all';
        } else {
            for ( var in_group in data.access[ group.name ].groups ) {
                in_group = parseName( data.access[ group.name ].groups[ in_group ] );
                var li3 = ul3.appendChild( createElement( 'li' ) );
                li3.appendChild( createElement( 'span' ) ).innerText = in_group.name;
                var form = li3.appendChild( createElement( 'form' ) );
                form.appendChild( createInput( 'hidden', 'type', 'group' ) );
                form.appendChild( createInput( 'hidden', 'group', group.full ) );
                form.appendChild( createInput( 'hidden', 'name', in_group.full ) );
                form.appendChild( createInput( 'submit', 'do', 'remove' ) );
            }
            var li3 = ul3.appendChild( createElement( 'li' ) );
            var form = li3.appendChild( createElement( 'form' ) );
            form.appendChild( createInput( 'hidden', 'type', 'group' ) );
            form.appendChild( createInput( 'hidden', 'group', group.full ) );
            form.appendChild( createInput( 'text', 'name', '' ) );
            form.appendChild( createInput( 'submit', 'do', 'add' ) );
        }
    }
    
    /******************************************************************
     views
     *****************************************************************/
    if ( data.access[ group.full ].views ) {
        var li2 = ul2.appendChild( createElement( 'li' ) );
        li2.appendChild( createElement( 'h3' ) ).innerText = 'views';
        var ul3 = li2.appendChild( createElement( 'ul' ) );
        
        var li3 = ul3.appendChild( createElement( 'li' ) );
        li3.appendChild( createElement( 'h4' ) ).innerText = 'filter';
        li3.appendChild( createElement( 'pre' ) ).innerText = data.access[ group.full ].filter;
        if ( data.access[ data.group ].groups === true ) {
            var form = li3.appendChild( createElement( 'form' ) );
            form.appendChild( createInput( 'hidden', 'type', 'filter' ) );
            form.appendChild( createInput( 'hidden', 'group', group.full ) );
            form.appendChild( createInput( 'hidden', 'name', 'none' ) );
            form.appendChild( createElement( 'textarea' ) ).name = 'value';
            form.appendChild( createInput( 'submit', 'do', 'save' ) );
        }
        
        for ( var view in data.access[ group.name ].views ) {
            view = parseName( view, true );
            view.filter = data.access[ group.name ].views[ view.full ];
            var li3 = ul3.appendChild( createElement( 'li' ) );
            var link = li3.appendChild( createElement( 'a' ) );
            link.href = `/query/${group.name}:${view.name}:${view.key}`;
            link.innerText = view.name;
            link.target = '_blank';
            var form = li3.appendChild( createElement( 'form' ) );
            form.appendChild( createInput( 'hidden', 'type', 'view' ) );
            form.appendChild( createInput( 'hidden', 'group', group.full ) );
            form.appendChild( createInput( 'hidden', 'name', view.full ) );
            form.appendChild( createInput( 'submit', 'do', 'delete' ) );
            li3.appendChild( createElement( 'pre' ) ).innerText = view.filter;
        }
        var li3 = ul3.appendChild( createElement( 'li' ) );
        li3.appendChild( createElement( 'div' ) ).innerText = '+';
        var form = li3.appendChild( createElement( 'form' ) );
        form.appendChild( createElement( 'textarea' ) ).name = 'value';
        form.appendChild( createInput( 'hidden', 'type', 'view' ) );
        form.appendChild( createInput( 'hidden', 'group', group.full ) );
        form.appendChild( createInput( 'text', 'name', '' ) );
        form.appendChild( createInput( 'submit', 'do', 'create' ) );
    }
    
    /******************************************************************
     admins
     *****************************************************************/
    if ( data.access[ group.full ].admins ) {
        var li2 = ul2.appendChild( createElement( 'li' ) );
        li2.appendChild( createElement( 'h3' ) ).innerText = 'admins';
        var ul3 = li2.appendChild( createElement( 'ul' ) );
        for ( var admin in data.access[ group.name ].admins ) {
            admin = parseName( data.access[ group.name ].admins[ admin ], true );
            var li3 = ul3.appendChild( createElement( 'li' ) );
            var link = li3.appendChild( createElement( 'a' ) );
            link.href = `/admin/${group.name}:${admin.name}:${admin.key}`;
            link.innerText = admin.name;
            link.target = '_blank';
            var form = li3.appendChild( createElement( 'form' ) );
            form.appendChild( createInput( 'hidden', 'type', 'admin' ) );
            form.appendChild( createInput( 'hidden', 'group', group.full ) );
            form.appendChild( createInput( 'hidden', 'name', admin.full ) );
            form.appendChild( createInput( 'submit', 'do', 'delete' ) );
        }
        var li3 = ul3.appendChild( createElement( 'li' ) );
        var form = li3.appendChild( createElement( 'form' ) );
        form.appendChild( createInput( 'hidden', 'type', 'admin' ) );
        form.appendChild( createInput( 'hidden', 'group', group.full ) );
        form.appendChild( createInput( 'text', 'name', '' ) );
        form.appendChild( createInput( 'submit', 'do', 'create' ) );
    }
}
document.body.appendChild( createElement( 'div' ) );
// document.body.appendChild( createElement( 'pre' ) ).innerText = JSON.stringify( data, null, '  ' );
</script>
</body>
</html>
