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
    'pid' => $basePath . '/run/stone-fpm.pid',
    'process_name' => 'stone-fpm',
    'open_eof_check' => false, //打开EOF检测
    'package_eof' => "\r\n", //设置EOF
    'live_check_file' => $basePath . '/logs/live_check',
    'log_file' => $basePath . '/logs/stone-fpm.log',
];

if (isset($options['debug'])) {
    $config['daemonize'] = false;
    $config['worker_num'] = 1;
}

require_once $basePath . '/src/Stone/FastCGIException.php';
require_once $basePath . '/src/Stone/FastCGI/Connection.php';
require_once $basePath . '/src/Stone/FastCGI/Server.php';
require_once $basePath . '/src/Stone/FastCGI/Protocol.php';

$server = new Stone\FastCGI\Server($config);

if (isset($options['reload'])) {
    $server->reload();
} elseif (isset($options['stop'])) {
    $server->stop();
} else {
    $server->start();
}
