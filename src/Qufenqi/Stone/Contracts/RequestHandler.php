<?php namespace Qufenqi\Stone\Contracts;

interface RequestHandler
{
    public function process($url, $params = []);
}
