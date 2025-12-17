<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use App\Security\TokenUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private Connection $connection
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Supporte les routes /staff/* et /report/*
        $path = $request->getPathInfo();
        return str_starts_with($path, '/staff/') || str_starts_with($path, '/report/');
    }

    public function authenticate(Request $request): Passport
    {
        // Extraire le token de l'URL (e.g., /staff/{token}/...)
        $pathParts = explode('/', trim($request->getPathInfo(), '/'));
        $token = $pathParts[1] ?? null;

        if (!$token) {
            throw new AuthenticationException('No token provided');
        }

        // Vérifier le token dans la BDD
        $sql = "SELECT id, user_email, scope, event_id FROM kp_staff_tokens
                WHERE token = ? AND expiry > NOW() AND active = 1";

        try {
            $result = $this->connection->fetchAssociative($sql, [$token]);

            if (!$result) {
                throw new AuthenticationException('Invalid or expired token');
            }

            // Vérifier que le scope correspond à la route
            $requiredScope = str_starts_with($request->getPathInfo(), '/staff/') ? 'staff' : 'report';
            if ($result['scope'] !== $requiredScope && $result['scope'] !== 'admin') {
                throw new AuthenticationException('Insufficient permissions');
            }

            // TODO: Vérifier event_id si nécessaire (pour restreindre l'accès à un événement spécifique)

            // Créer un utilisateur avec les informations du token
            $user = new TokenUser($result['user_email'], ['ROLE_USER']);

            return new SelfValidatingPassport(
                new UserBadge($result['user_email'], fn() => $user)
            );
        } catch (\Exception $e) {
            throw new AuthenticationException('Authentication failed: ' . $e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Allow the request to continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Unauthorized',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}
