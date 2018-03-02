<?php
    require_once('vendor/autoload.php');

    // Sentry Block
    Raven_Autoloader::register();
    $sentry = new Raven_Client(trim(file_get_contents('/opt/secrets/owm_sentry')));
    $error_handler = new Raven_ErrorHandler($sentry);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();
    // /Sentry Block    

    use Monolog\Logger;
    use Monolog\Handler\StreamHandler;

    $logger = new Logger('default');
    $logger->pushHandler(new StreamHandler('/var/log/owm.log', Logger::DEBUG));
