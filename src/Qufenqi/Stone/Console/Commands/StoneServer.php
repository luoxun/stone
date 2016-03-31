<?php

namespace Qufenqi\Stone\Console\Commands;

use Illuminate\Console\Command;
use Config;
use App;

class StoneServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stone:server';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A FastCGI server base on swoole and laravel';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        try {
            $config = Config::get('stone.server');
            if ($this->option('debug')) {
                $config['daemonize'] = false;
            }

            $server = new Server($config, App::make($config['handler']));

            if ($this->option('reload')) {
                if ($server->reload()) {
                    return $this->info('reload the server success!');
                }
            }

            if ($this->option('stop')) {
                if ($server->stop()) {
                    return $this->info('stop the server success!');
                }
            }

            $server->start();
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }

    }

	protected function getArguments()
	{
		return array();
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
            ['start', 'start', InputOption::VALUE_NONE, 'start the server', null],
            ['reload', 'reload', InputOption::VALUE_NONE, 'reload the server graceful', null],
            ['stop', 'stop', InputOption::VALUE_NONE, 'stop the server', null],
            ['debug', 'debug', InputOption::VALUE_NONE, 'debug mode', null],
        ];
	}

}
