<?php

define( "_PL_OS_SEP", "/" );
define( "_CUR_OS", substr( php_uname( ), 0, 7 ) == "Windows" ? "Win" : "_Nix" );

function checkCurrentOS( $_OS )
{
    if ( strcmp( $_OS, _CUR_OS ) == 0 ) {
        return true;
    }
    return false;
}

function isRelative( $_dir )
{
    if ( checkCurrentOS( "Win" ) ) {
        return ( preg_match( "/^\w+:/", $_dir ) <= 0 );
    }
    else {
        return ( preg_match( "/^\//", $_dir ) <= 0 );
    }
}

function unifyPath( $_path )
{
    if ( checkCurrentOS( "Win" ) ) {
        return str_replace( "\\", _PL_OS_SEP, $_path );
    }
    return $_path;
}

function getRealpath( $_path )
{
    /*
     * This is the starting point of the system root.
     * Left empty for UNIX based and Mac.
     * For Windows this is drive letter and semicolon.
     */
    $__path = $_path;
    if ( isRelative( $_path ) ) {
        $__curdir = unifyPath( realpath( "." ) . _PL_OS_SEP );
        $__path = $__curdir . $__path;
    }
    $__startPoint = "";
    if ( checkCurrentOS( "Win" ) ) {
        list( $__startPoint, $__path ) = explode( ":", $__path, 2 );
        $__startPoint .= ":";
    }
    # From now processing is the same for WIndows and Unix, and hopefully for others.
    $__realparts = array( );
    $__parts = explode( _PL_OS_SEP, $__path );
    for ( $i = 0; $i < count( $__parts ); $i++ ) {
        if ( strlen( $__parts[ $i ] ) == 0 || $__parts[ $i ] == "." ) {
            continue;
        }
        if ( $__parts[ $i ] == ".." ) {
            if ( count( $__realparts ) > 0 ) {
                array_pop( $__realparts );
            }
        }
        else {
            array_push( $__realparts, $__parts[ $i ] );
        }
    }
    return $__startPoint . _PL_OS_SEP . implode( _PL_OS_SEP, $__realparts );
}


function PMA_splitSqlFile(&$ret, $sql)
{
    // do not trim, see bug #1030644
    //$sql          = trim($sql);
    $sql          = rtrim($sql, "\n\r");
    $sql_len      = strlen($sql);
    $char         = '';
    $string_start = '';
    $in_string    = FALSE;
    $nothing      = TRUE;
    $time0        = time();

    for ($i = 0; $i < $sql_len; ++$i) {
        $char = $sql[$i];

        // We are in a string, check for not escaped end of strings except for
        // backquotes that can't be escaped
        if ($in_string) {
            for (;;) {
                $i         = strpos($sql, $string_start, $i);
                // No end of string found -> add the current substring to the
                // returned array
                if (!$i) {
                    $ret[] = array('query' => $sql, 'empty' => $nothing);
                    return TRUE;
                }
                // Backquotes or no backslashes before quotes: it's indeed the
                // end of the string -> exit the loop
                else if ($string_start == '`' || $sql[$i-1] != '\\') {
                    $string_start      = '';
                    $in_string         = FALSE;
                    break;
                }
                // one or more Backslashes before the presumed end of string...
                else {
                    // ... first checks for escaped backslashes
                    $j                     = 2;
                    $escaped_backslash     = FALSE;
                    while ($i-$j > 0 && $sql[$i-$j] == '\\') {
                        $escaped_backslash = !$escaped_backslash;
                        $j++;
                    }
                    // ... if escaped backslashes: it's really the end of the
                    // string -> exit the loop
                    if ($escaped_backslash) {
                        $string_start  = '';
                        $in_string     = FALSE;
                        break;
                    }
                    // ... else loop
                    else {
                        $i++;
                    }
                } // end if...elseif...else
            } // end for
        } // end if (in string)

        // lets skip comments (/*, -- and #)
        else if (($char == '-' && $sql_len > $i + 2 && $sql[$i + 1] == '-' && $sql[$i + 2] <= ' ') || $char == '#' || ($char == '/' && $sql_len > $i + 1 && $sql[$i + 1] == '*')) {
            $i = strpos($sql, $char == '/' ? '*/' : "\n", $i);
            // didn't we hit end of string?
            if ($i === FALSE) {
                break;
            }
            if ($char == '/') $i++;
        }

        // We are not in a string, first check for delimiter...
        else if ($char == ';') {
            // if delimiter found, add the parsed part to the returned array
            $ret[]      = array('query' => substr($sql, 0, $i), 'empty' => $nothing);
            $nothing    = TRUE;
            $sql        = ltrim(substr($sql, min($i + 1, $sql_len)));
            $sql_len    = strlen($sql);
            if ($sql_len) {
                $i      = -1;
            } else {
                // The submited statement(s) end(s) here
                return TRUE;
            }
        } // end else if (is delimiter)

        // ... then check for start of a string,...
        else if (($char == '"') || ($char == '\'') || ($char == '`')) {
            $in_string    = TRUE;
            $nothing      = FALSE;
            $string_start = $char;
        } // end else if (is start of string)

        elseif ($nothing) {
            $nothing = FALSE;
        }

        // loic1: send a fake header each 30 sec. to bypass browser timeout
        $time1     = time();
        if ($time1 >= $time0 + 30) {
            $time0 = $time1;
            header('X-pmaPing: Pong');
        } // end if
    } // end for

    // add any rest to the returned array
    if (!empty($sql) && preg_match('@[^[:space:]]+@', $sql)) {
        $ret[] = array('query' => $sql, 'empty' => $nothing);
    }

    return TRUE;
} 

