<?php

namespace AZCode\AZModule;

use AZCode\AZModule\Console\Commands\AZModuleCommand;
use Illuminate\Support\ServiceProvider;

class AZModuleServiceProvider extends ServiceProvider
{
  public function register()
  {
    $this->commands([
      AZModuleCommand::class,
    ]);
  }

  public function boot()
  {
    //
  }
}
