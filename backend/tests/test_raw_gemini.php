<?php
require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use App\Service\GeminiService;

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
if (!$apiKey) {
    die("No API key found in env.\n");
}

$httpClient = HttpClient::create();
$geminiService = new GeminiService($httpClient, $apiKey);

$prompt = "Minimalist professional infographic. Theme: Sueldos 2026 en España. Section: Oportunidades.";

try {
    echo "Sending request without timeout to Gemini...\n";
    $result = $geminiService->generateImage($prompt, 'gemini-3.1-flash-image-preview');
    echo "SUCCESS! Response keys:\n";
    print_r(array_keys($result));
    
    if (isset($result['candidates'][0]['content']['parts'][0]['inlineData'])) {
        echo "Successfully received inlineData base64 image payload!\n";
    } else {
        echo "Response successfully completed but returned NO_IMAGE reason or empty parts:\n";
        print_r($result);
    }
} catch (\Exception $e) {
    echo "\nCRASHED WITH ERROR:\n";
    echo $e->getMessage() . "\n";
}
