<?php return array (
  'providers' => 
  array (
    0 => 'Framework\\Database\\DatabaseServiceProvider',
    1 => 'Framework\\Filesystem\\FilesystemServiceProvider',
    2 => 'Framework\\Hashing\\HashServiceProvider',
    3 => 'App\\Infrastructure\\Providers\\AppServiceProvider',
    4 => 'App\\Infrastructure\\Providers\\RouteServiceProvider',
  ),
  'eager' => 
  array (
    0 => 'Framework\\Database\\DatabaseServiceProvider',
    1 => 'Framework\\Filesystem\\FilesystemServiceProvider',
    2 => 'App\\Infrastructure\\Providers\\AppServiceProvider',
    3 => 'App\\Infrastructure\\Providers\\RouteServiceProvider',
  ),
  'deferred' => 
  array (
    'hash' => 'Framework\\Hashing\\HashServiceProvider',
    'hash.driver' => 'Framework\\Hashing\\HashServiceProvider',
  ),
  'when' => 
  array (
    'Framework\\Hashing\\HashServiceProvider' => 
    array (
    ),
  ),
);