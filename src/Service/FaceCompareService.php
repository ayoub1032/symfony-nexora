<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceCompareService
{
    private const API_URL    = 'https://api-us.faceplusplus.com/facepp/v3/compare';
    private const THRESHOLD  = 80.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    /**
     * Compare two base64-encoded face images.
     * Returns the confidence score (0-100), or null on API error.
     */
    public function compare(string $base64A, string $base64B): ?float
    {
        // Strip data-URI prefix if present  e.g. "data:image/jpeg;base64,..."
        $base64A = preg_replace('/^data:image\/\w+;base64,/', '', $base64A) ?? $base64A;
        $base64B = preg_replace('/^data:image\/\w+;base64,/', '', $base64B) ?? $base64B;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'body' => [
                    'api_key'       => $this->apiKey,
                    'api_secret'    => $this->apiSecret,
                    'image_base64_1' => $base64A,
                    'image_base64_2' => $base64B,
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);

            if (isset($data['confidence'])) {
                return (float) $data['confidence'];
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns true when confidence >= threshold (80%).
     */
    public function isMatch(string $base64A, string $base64B): bool
    {
        $score = $this->compare($base64A, $base64B);

        return $score !== null && $score >= self::THRESHOLD;
    }
}
