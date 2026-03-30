<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpClient\HttpClient;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
if (!$apiKey) {
    die("No API key found\n");
}

$prompt = "Professional minimalist infographic for a blog post section titled 'Panorama Laboral 2026: Sectores con Mayor Demanda y Crecimiento Salarial en España'. Theme: Sueldos 2026: Los Estudios que Aseguran un Empleo Bien Remunerado en España. Soft professional color palette, informative icons, clean vector style. High quality, 16:9 aspect ratio, academic style.\n\nCRITICAL REQUIREMENT: You MUST include this exact data in the infographic text:\n1.  **Tecnología y Datos:** Demanda disparada (IA, Ciberseguridad, Big Data). Salarios iniciales: +28.000€/año.\n2.  **Salud y Cuidados:** Envejecimiento poblacional aumenta la demanda (Enfermería, Atención Sociosanitaria).\n3.  **Sostenibilidad y Energías Renovables:** Crecimiento explosivo impulsado por normativas europeas.\n4.  **Logística y Cadena de Suministro:** Auge del e-commerce asegura empleabilidad constante.\n5.  **Formación Profesional (FP):** Alta inserción laboral (especialmente Grados Superiores técnicos).";

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image-preview:generateContent?key=' . $apiKey;

$body = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'responseModalities' => ['IMAGE']
    ]
];

$client = HttpClient::create();
$response = $client->request('POST', $url, [
    'json' => $body,
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'timeout' => 90,
]);

try {
    $data = $response->toArray();
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponse')) {
        echo "BODY: " . $e->getResponse()->getContent(false) . "\n";
    }
}
