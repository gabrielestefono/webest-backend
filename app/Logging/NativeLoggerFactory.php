<?php

namespace App\Logging;

class NativeLoggerFactory
{
    public function __invoke(array $config)
    {
        return new NativeLogger();
    }
}
