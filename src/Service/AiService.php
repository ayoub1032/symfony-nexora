<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AiService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(string $geminiApiKey, HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->apiKey = $geminiApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Analyse les données financières et retourne un conseil personnalisé.
     */
    public function getFinancialAdvice(array $data, string $riskProfile = 'Balanced'): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'votre_cle_ici') {
            return "Veuillez configurer votre clé API Gemini dans le fichier .env pour activer les conseils personnalisés.";
        }

        $prompt = "Tu es Nexora AI, un conseiller financier expert. 
        L'utilisateur a un profil de risque : " . $riskProfile . ". 
        En te basant sur ces données : " . json_encode($data) . ", 
        donne un conseil financier court (maximum 2 phrases) et motivant adapté à ce profil de risque. 
        Sois précis sur les chiffres s'ils sont fournis.";

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->apiKey,
                [
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ]
                    ]
                ]
            );

            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 429) {
                return $this->getMockAdvice($data);
            }

            if ($statusCode !== 200) {
                return "L'IA est temporairement indisponible (Erreur " . $statusCode . ").";
            }

            $result = $response->toArray();
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return $result['candidates'][0]['content']['parts'][0]['text'];
            }

            return "Analyse impossible pour le moment. Réessayez plus tard.";
        } catch (\Exception $e) {
            $this->logger->error('Erreur API Gemini: ' . $e->getMessage());
            // Fallback en cas d'erreur réseau
            return $this->getMockAdvice($data);
        }
    }

    /**
     * Génère une analyse simulée de haute qualité basée sur les données réelles (Demo Mode).
     */
    private function getMockAdvice(array $data): string
    {
        $progression = $data['progression'] ?? 0;
        $goalName = $data['goal_name'] ?? 'cet objectif';
        $daysRem = $data['days_remaining'] ?? 30;

        if ($progression >= 100) {
            return "Félicitations ! Votre objectif \"$goalName\" est atteint. C'est le moment idéal pour réinvestir vos bénéfices ou définir un nouvel objectif de trading plus ambitieux.";
        }

        if ($progression >= 75) {
            return "Vous y êtes presque ! Avec $progression% d'avancement, vous devriez atteindre \"$goalName\" d'ici quelques jours. Gardez votre discipline de trading actuelle, elle fonctionne parfaitement.";
        }

        if ($progression >= 40) {
            return "Bonne progression sur \"$goalName\". Vous avez déjà sécurisé $progression% de votre cible. Avec encore $daysRem jours devant vous, votre stratégie semble équilibrée et réaliste.";
        }

        if ($daysRem < 7) {
            return "Alerte Deadline : Il ne vous reste que $daysRem jours pour atteindre \"$goalName\". Un dépôt supplémentaire ou une gestion plus serrée de vos ordres ouverts pourrait être nécessaire.";
        }

        return "Analyse de Nexora : Votre progression sur \"$goalName\" est de $progression%. Le marché actuel offre des opportunités, continuez à accumuler vos gains pour atteindre votre cible de " . ($data['target'] ?? 'votre objectif') . " TND.";
    }
}
