<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // The API renders every timestamp in UTC, whatever the timezone of the
        // server is.
        date_default_timezone_set('UTC');

        parent::boot();
    }
}
