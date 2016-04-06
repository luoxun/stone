# 关于Stone
Stone是一个基于swoole的php应用，目的是为了提高基于laravel实现的接口性能。当然， 理论上适用于任何框架。 目前这个项目有特定的应用场景限制， 主要适用于无状态的接口调用的场景。能在继续使用Laravel优雅代码的前提下得到极大的性能提升。 

其实，包含视图和Session请求也可以通过这个手段来优化， 只是涉及到很多修改laravel的地方， 有点过于复杂。 对此感兴趣的朋友可以参考一个叫LaravelFly的项目。

我特意写了一个基于laravel5.2的例子， 性能对比大概是120QPS vs. 7600QPS。
详情请看：[https://github.com/chefxu/stone-laravel-example](https://github.com/chefxu/stone-laravel-example)

# 原理说明
1. 本项目可以理解为一个胶水项目，把swoole和laravel较好的揉和起来，既享受Laravel优雅的编码体验又享受swoole的高性能。
2. 通过swoole将应用常驻内存， 避免每次请求前都需要初始化框架代码，请求后都需要销毁框架代码，大大提高接口的性能。
3. 实现fastcgi协议， 主要是为了继续使用成熟的nginx，也能保持架构的简单。
4. 简单来说就是 nginx+stone(swoole+fastcgi) 替换 nginx+PHP-FPM。

# 性能对比
测试内容为输出一个简单的json数组, 具体测试：[https://github.com/chefxu/stone-laravel-example#performance](https://github.com/chefxu/stone-laravel-example#performance)

1. nginx + stone + laravel5.2

		Server Software:        stone
		Server Hostname:        192.168.1.10
		Server Port:            7412
		
		Document Path:          /server/workbench/listdata
		Document Length:        211 bytes
		
		Concurrency Level:      100
		Time taken for tests:   1.305 seconds
		Complete requests:      10000
		Failed requests:        0
		Total transferred:      3400000 bytes
		HTML transferred:       2110000 bytes
		Requests per second:    7662.11 [#/sec] (mean)
		Time per request:       13.051 [ms] (mean)
		Time per request:       0.131 [ms] (mean, across all concurrent requests)
		Transfer rate:          2544.06 [Kbytes/sec] received
		
2. nginx + php-fpm + laravel5.2

		Server Software:        nginx
		Server Hostname:        192.168.1.10
		Server Port:            7412
		
		Document Path:          /workbench/listdata
		Document Length:        211 bytes
		
		Concurrency Level:      30
		Time taken for tests:   8.335 seconds
		Complete requests:      1000
		Failed requests:        0
		Total transferred:      1185252 bytes
		HTML transferred:       211000 bytes
		Requests per second:    119.97 [#/sec] (mean)
		Time per request:       250.054 [ms] (mean)
		Time per request:       8.335 [ms] (mean, across all concurrent requests)
		Transfer rate:          138.87 [Kbytes/sec] received
		
		
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
