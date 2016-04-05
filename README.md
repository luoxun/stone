# 关于Stone
是一个基于swoole的fastcgi应用，用来大幅提高基于laravel实现的接口性能。当然， 理论上适用于任何框架。

先说明一下：

1. 面向有一定php基础的用户， 例子很可能不能直接跑起来， 如果你没有一定php功底， 和可能会感到抑郁。
2. 目前没有在生成环境下使用过，因此也不对使用结果负责。
3. 测试结果大概为laravel框架的30-50倍， 性能接近纯原生php的80%。
4. 本项目的意义是在使用Laravel优雅代码的前提下不损失性能， 适用于调用频率较高的接口调用的场合。

# 原理说明
1. 利用swoole常驻内存， 避免每次请求都需要初始化框架， 减少框架初始化带来的性能损耗
2. 实现fastcgi协议，绕开php-fpm直接与nginx通信，简化架构
3. 去除了Cookie， 因此不支持Session等依赖Cookie的包含状态的会话。

# 性能对比
测试内容为输出一个简单的json数组。 

1. nginx + stone + laravel5.2

	 	Server Software:        stone
		Server Hostname:        192.168.1.10
		Server Port:            7411
		Document Path:          /server/account/get?id=123
		Document Length:        33 bytes
		Concurrency Level:      100
		Time taken for tests:   1.273 seconds
		Complete requests:      10000
		Failed requests:        0
		Total transferred:      1620000 bytes
		HTML transferred:       330000 bytes
		Requests per second:    7857.77 [#/sec] (mean)
		Time per request:       12.726 [ms] (mean)
		Time per request:       0.127 [ms] (mean, across all concurrent requests)
		Transfer rate:          1243.12 [Kbytes/sec] received
		
2. nginx + php-fpm + laravel5.2

		Server Software:        nginx
		Server Hostname:        192.168.1.10
		Server Port:            7411
		Document Path:          /server2?a=123
		Document Length:        29 bytes	
		Concurrency Level:      30
		Time taken for tests:   9.043 seconds
		Complete requests:      1000
		Failed requests:        0
		Total transferred:      1011110 bytes
		HTML transferred:       29000 bytes
		Requests per second:    110.58 [#/sec] (mean)
		Time per request:       271.289 [ms] (mean)
		Time per request:       9.043 [ms] (mean, across all concurrent requests)
		Transfer rate:          109.19 [Kbytes/sec] received
		
	测试代码类似：
	
		Route::get('/server2', function () {
	   		return json_encode(['code' => 0, 'data' => $_GET]);
		});
		
# 使用说明
> 本项目仅为学习交流使用， 不建议在生成环境下使用，如因在生成环境下使用带来的损失， 本人一概不负责任。

1. composer安装包 composer require qufenqi/stone
2. 引入artisan管理脚本， app/Console/Kernel.php 添加一行

	    protected $commands = [
	        Commands\Inspire::class,
	        \Qufenqi\Stone\Console\Commands\StoneServer::class,
	    ];

3. 引入ServiceProvider，config/app.php添加一行

	    Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        Qufenqi\Stone\StoneServiceProvider::class,
        
4. 自定义handler类

	每一次请求需要有一个handler去处理，因为处理请求属于业务的一部分， 所以交由业务层去处理。 要求实现RequestHandler接口。
		
		<?php namespace App\Servers;

		use Qufenqi\Stone\Contracts\RequestHandler;
		use App;
		use Log;
		use Exception;
		
		class Handler implements RequestHandler
		{
		    public function process($url, $params = [])
		    {
		        list($class, $method) = $this->route($url);
		
		        if (!class_exists($class)) {
		            return $this->output(['code' => -1, 'message' => $class . '不存在', 'data' => null]);
		        }
		
		        $instance = App::make($class);
		
		        if (!method_exists($instance, $method)) {
		            return $this->output(['code' => -1, 'message' => $method . '不存在', 'data' => null]);
		        }
		
		        try {
		            $data = call_user_func_array([$instance, $method], $params);
		        } catch (Exception $e) {
		            Log::error($e);
		            return $this->output(['code' => -1, 'message' => '系统繁忙', 'data' => null]);
		        }
		
		        return $this->output($data);
		    }
		
		    public function output($data)
		    {
		        return json_encode($data);
		    }
		
		    public function route($url)
		    {
		        $path = parse_url($url, PHP_URL_PATH);
		        $path = explode('/', $path);
		        $class = $method = '';
		
		        if (!empty($path[2])) {
		            $class = 'App\\Servers\\' . ucfirst(camel_case($path[2] . '_server'));
		        }
		
		        if (!empty($path[3])) {
		            $method = camel_case($path[3]);
		        }
		
		        return [$class, $method];
		    }
		}
5. 新增自定义配置 config/stone.php

		<?php
		return [
		    'handler' => 'App\Servers\Handler',
		    'user' => 'apple',
		    'group' => 'apple',
		];

6. 管理服务
		
		sudo php ./artisan stone:server --start --stop --reload
7. 调试模式

		sudo php ./artisan stone:server --start --debug
		
8. 修改nginx配置

	    location /server/ {
	        fastcgi_pass 127.0.0.1:9101;
	        fastcgi_split_path_info ^(.+\.php)(/.+)$;
	        fastcgi_index index.php;
	        include fastcgi.conf;
	    }
