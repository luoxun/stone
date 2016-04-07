<?php
$basePath = realpath(dirname(__FILE__) . '/../');
$options = getopt('', ['start::', 'reload::', 'stop::', 'debug::']);

$config = [
    'ip' => '0.0.0.0',
    'port' => 9600,
    'daemonize' => true,
    'user' => 'apple',
    'group' => 'apple',
    'chroot' => '',
    'worker_num' => 30,
    'task_worker_num' => 1,
    'max_request' => 10000,
    'pid' => '/run/stone-http-fpm.pid',
    'process_name' => 'stone-http-fpm',
    'open_eof_check' => false, //打开EOF检测
    'package_eof' => "\r\n", //设置EOF
    'live_check_file' => '/tmp/live_check',
    'log_file' => '/tmp/stone-http-fpm.log',
    'laravel_path' => '/set/the/laravel/project/path/',
];

if (isset($options['debug'])) {
    $config['daemonize'] = false;
    $config['worker_num'] = 1;
}

require_once $basePath . '/src/Qufenqi/Stone/FastCGIException.php';
require_once $basePath . '/src/Qufenqi/Stone/FastCGI/Connection.php';
require_once $basePath . '/src/Qufenqi/Stone/FastCGI/Server.php';
require_once $basePath . '/src/Qufenqi/Stone/FastCGI/Protocol.php';
require_once $basePath . '/src/Qufenqi/Stone/Contracts/RequestHandler.php';
require_once $basePath . '/src/Qufenqi/Stone/Http/Handler.php';

try {
    $server = new Qufenqi\Stone\FastCGI\Server($config, new Qufenqi\Stone\Http\Handler($config['laravel_path']));

    if (isset($options['reload'])) {
        $server->reload();
    } elseif (isset($options['stop'])) {
        $server->stop();
    } else {
        $server->start();
    }
} catch (Exception $e) {
    echo $e;
}
