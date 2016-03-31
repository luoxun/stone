<?php namespace Quefenqi\Stone\Contracts;

interface RequestHandler
{
    public function process($url, $params = []);
}
