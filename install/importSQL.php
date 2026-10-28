
<?php

require_once __DIR__ . '/helper.php';

$errors = [];
$goToNextStep = false;
$con = null;

try {
    if (!isset(
        $_SESSION['db_host'],
        $_SESSION['db_user'],
        $_SESSION['db_pass'],
        $_SESSION['db_name']
    )) {
        throw new RuntimeException(
            'Database connection settings are missing. Please return to the database step.'
        );
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $con = new mysqli(
        $_SESSION['db_host'],
        $_SESSION['db_user'],
        $_SESSION['db_pass'],
        $_SESSION['db_name']
    );

    $con->set_charset('utf8mb4');

    foreach (['structure.sql', 'data.sql'] as $file) {
        $path = __DIR__ . '/config/' . $file;
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException(
                'Cannot read SQL file: ' . $file
            );
        }

        $queries = [];
        PMA_splitSqlFile($queries, $sql);

        foreach ($queries as $index => $query) {
            $statement = trim($query['query'] ?? '');

            if ($statement === '') {
                continue;
            }

            try {
                $con->query($statement);
            } catch (mysqli_sql_exception $e) {
                $details = sprintf(
                    '%s - Statement %d - SQL Error %d: %s',
                    $file,
                    $index + 1,
                    $e->getCode(),
                    $e->getMessage()
                );

                error_log('DEWAWI Installer: ' . $details . "\n" . (string)$e);
                $errors[] = 'Import failed. Please check the server logs or contact your administrator.';
                if (getenv('APPLICATION_ENV') === 'development') $errors[] = (string)$e;

                break 2;
            }
        }
    }

} catch (Throwable $e) {
    error_log('DEWAWI Installer: ' . (string)$e);

    $missingSettings = 'Database connection settings are missing. Please return to the database step.';
    $errors[] = $e instanceof RuntimeException && $e->getMessage() === $missingSettings ? $missingSettings : 'Import failed. Please check the server logs or contact your administrator.';
    if (getenv('APPLICATION_ENV') === 'development') $errors[] = (string)$e;

} finally {
    if ($con instanceof mysqli) {
        $con->close();
    }
}

$goToNextStep = empty($errors);

include __DIR__ . '/templates/importSQL.php';
