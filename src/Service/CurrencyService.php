<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CurrencyService
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Récupère les taux de change pour le TND vers USD et EUR.
     */
    public function getLatestRates(): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.frankfurter.app/latest?from=TND&to=USD,EUR');
            
            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['rates'] ?? [];
            }
        } catch (\Exception $e) {
            // Log entry or handle error
        }

        // Fallback rates if API fails
        return [
            'USD' => 0.32,
            'EUR' => 0.30
        ];
    }
}
