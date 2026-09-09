<?php

use App\Providers\AppServiceProvider;
use App\Providers\WafServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;

return [
    AppServiceProvider::class,
    WafServiceProvider::class,
    MongoDBServiceProvider::class,
];
