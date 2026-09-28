<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('library:health', function () {
    $this->info('IIT Shelf Laravel API is ready.');
})->purpose('Check the library API application boundary');