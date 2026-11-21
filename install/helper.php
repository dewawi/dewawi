<?php


function installerTranslate(string $key, array $args = []): string
{
    require_once dirname(__DIR__) . '/library/DEEC/Translate.php';
    $selected = $_SESSION['installer_locale'] ?? 'en_US';
    $locale = DEEC_Translate::normalizeLocale(is_string($selected) ? $selected : '', 'en_US');
    static $translators = [];
    if (!isset($translators[$locale])) {
        $translators[$locale] = new DEEC_Translate($locale);
        $translators[$locale]->loadDir('installer', dirname(__DIR__) . '/languages/' . $locale . '/installer');
    }
    return $translators[$locale]->t($key, $args);
}

function installerText(string $key, array $args = []): string
{
    return htmlspecialchars(installerTranslate($key, $args), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

ini_set('display_errors', getenv('APPLICATION_ENV') === 'development' ? '1' : '0');
ini_set('display_startup_errors', getenv('APPLICATION_ENV') === 'development' ? '1' : '0');
ini_set('log_errors', '1');
set_exception_handler(static function (Throwable $exception): void {
    error_log((string)$exception);
    $message = installerTranslate('INSTALL_FAILURE');
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p>' . $message . '</p>';
    if (getenv('APPLICATION_ENV') === 'development') echo '<pre>' . htmlspecialchars((string)$exception, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
});

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


require_once __DIR__ . '/SqlSplitter.php';
