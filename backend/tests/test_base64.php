<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$infographic = $container->get('App\Service\InfographicService');

echo "Generating infographic...\n";
$result = $infographic->generateForSection("Requisitos de Ingreso", "¿Cómo ser Guardia Civil? Pasos para el Ingreso en el Cuerpo");

echo "Result prefix: " . substr($result, 0, 80) . "...\n";
echo "Total Length: " . strlen($result) . " bytes\n";
