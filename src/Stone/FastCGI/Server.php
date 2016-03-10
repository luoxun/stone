<?php
namespace Stone\FastCGI;

use Exception;
use swoole_server;
use Stone\FastCGI\Protocol as FastCGI;
use Stone\FastCGI\Connection as FastCGIConnection;

class Server
{
    private $config;

    private $commitId = null;

    private $app;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function start()
    {
        if ($this->isRunning()) {
            throw new Exception('服务已经启动');
        }

        $config = $this->config;
        $serv = new swoole_server($config['ip'], $config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $serv->set(array(
            'worker_num' => $config['worker_num'],
            'task_worker_num' => $config['task_worker_num'],
            'daemonize' => $config['daemonize'],
            'user' => $config['user'],
            'group' => $config['group'],
            'open_eof_check' => $config['open_eof_check'],
            'package_eof' => $config['package_eof'],
            'log_file' => $config['log_file'],
        ));
        $serv->on('Start', [$this, 'onStart']);
        $serv->on('Connect', [$this, 'onConnect']);
        $serv->on('Receive', [$this, 'onReceive']);
        $serv->on('Task', [$this, 'onTask']);
        $serv->on('Finish', [$this, 'onFinish']);
        $serv->on('WorkerStart', [$this, 'onWorkerStart']);
        $serv->on('ManagerStart', [$this, 'onManagerStart']);
        $serv->on('Close', [$this, 'onClose']);

        //$this->commitId = $this->getCommitId();

        $serv->start();
    }

    public function stop()
    {
        if (!$this->isRunning()) {
            throw new Exception('服务未启动');
        }

        $pid = $this->getMainPid();
        posix_kill($pid, SIGTERM);
        unlink($this->config['pid']);

        return true;
    }

    public function reload()
    {
        if (!$this->isRunning()) {
            throw new Exception('服务未启动');
        }

        $pid = $this->getMainPid();
        posix_kill($pid, SIGUSR1);

        return true;
    }

    public function isRunning()
    {
        $pid_file = $this->config['pid'];

        if (!file_exists($pid_file)) {
            return false;
        }

        $main_pid = $this->getMainPid();
        if (!posix_kill($main_pid, 0)) {
            unlink($pid_file);
            return false;
        }

        return true;
    }

    public function writePid()
    {
        $pid = getmypid();
        $res = file_put_contents($this->config['pid'], $pid);

        if ($res === false) {
            throw new Exception('写入进程文件失败， 请用超级用户执行');
        }
    }

    public function getMainPid()
    {
        return file_get_contents($this->config['pid']);
    }

    public function onStart(swoole_server $server)
    {
        swoole_set_process_name($this->config['process_name']);
        $this->writePid();
    }

    public function onConnect()
    {
    }

    public function onTask(swoole_server $serv, $task_id, $from_id, $data)
    {
    }

    public function onFinish()
    {

    }

    public function onManagerStart(swoole_server $server)
    {
        swoole_set_process_name($this->config['process_name'] . ':manager');
    }


    public function onWorkerStart(swoole_server $server, $worker_id)
    {
        opcache_reset();
        require '/home/chunfang/qufenqi/bootstrap/autoload.php';
        $this->app = require_once '/home/chunfang/qufenqi/bootstrap/start.php';

        if ($worker_id >= $server->setting['worker_num']) {
            swoole_set_process_name($this->config['process_name'] . ':tasker');
            // $this->liveCheck($server);
        } else {
            swoole_set_process_name($this->config['process_name'] . ':worker');
        }
    }

    public function onReceive(swoole_server $server, $fd, $from_id, $data)
    {
        $fastCGI = new FastCGI(new FastCGIConnection($server, $fd, $from_id));
        $requestData = $fastCGI->readFromString($data);
        $request = current($requestData);
        $_SERVER = $request['params'];
        $_SERVER['RUNENV'] = 'local';
        $_COOKIE = $_POST = $_GET = $REQUEST = [];

        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }

        if (!empty($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode('; ', $_SERVER['HTTP_COOKIE']);
            foreach ($cookies as $item) {
                $item = explode('=', $item);
                if (count($item) === 2) {
                    $_COOKIE[$item[0]] = urldecode($item[1]);
                }
            }
        }

        $_REQUEST = array_merge($_GET, $_POST);

        try {
            $content = $this->app->render();
        } catch (\Exception $e) {
            $content = "\n\r\n\r系统繁忙";
        }

        $fastCGI->sendDataToClient(1, $content);
        $server->close($fd);
        return;
    }

    public function onClose()
    {
    }

    public function liveCheck(swoole_server $server)
    {
        $base_path = base_path();
        $output = $ret = null;
        $cmd = "cd $base_path && git log|head -1|awk '{print \$NF}'";

        while (true) {
            $commitId = exec($cmd, $output, $ret);
            $current_commitId = $this->getCommitId();

            if ($commitId != $current_commitId) {
                file_put_contents($this->config['live_check_file'], $commitId);
            }

            sleep(10);
        }
    }

    public function getCommitId()
    {
        return file_get_contents($this->config['live_check_file']);
    }
}

