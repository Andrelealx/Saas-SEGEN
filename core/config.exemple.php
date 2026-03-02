<?php
return [
  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'segs_rh',
    'user' => 'usuario',
    'pass' => 'senha',
    'charset' => 'utf8mb4'
  ],
  'app' => [
    'base_url' => '/', // ajuste se usar subpasta
    'env' => 'prod'
  ],
  'mail' => [
    'from' => 'nao-responder@seu-dominio.gov.br'
  ]
];
