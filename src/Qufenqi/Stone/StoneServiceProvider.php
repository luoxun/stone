<?php namespace Qufenqi\Stone;

use Illuminate\Support\ServiceProvider;

class StoneServiceProvider extends ServiceProvider
{

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/stone.php', 'stone'
        );
    }
}
