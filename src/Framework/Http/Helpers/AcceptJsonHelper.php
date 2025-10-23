<?php

namespace Framework\Http\Helpers;

use Psr\Http\Message\ServerRequestInterface;

class AcceptJsonHelper
{
    /**
     * Vérifie si la requête accepte du JSON
     */
    public static function acceptsJson(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');

        if (empty($accept)) {
            return false;
        }

        // Vérifie si le header contient application/json
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si la requête accepte du JSON en priorité
     * (vérifie le q-factor pour la négociation de contenu)
     */
    public static function prefersJson(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');

        if (empty($accept)) {
            return false;
        }

        $types = self::parseAcceptHeader($accept);

        // Vérifie si JSON a le meilleur score
        if (isset($types['application/json'])) {
            foreach ($types as $type => $quality) {
                if ($type !== 'application/json' && $quality > $types['application/json']) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Parse le header Accept et retourne un tableau avec les types et leur qualité
     * Exemple: "text/html,application/json
     */
    public static function parseAcceptHeader(string $accept): array
    {
        $types = [];
        $parts = explode(',', $accept);

        foreach ($parts as $part) {
            $part = trim($part);

            // Sépare le type MIME et les paramètres
            if (strpos($part, ';') !== false) {
                list($type, $params) = explode(';', $part, 2);

                // Extrait le q-factor (qualité)
                $quality = 1.0;

                if (preg_match('/q=([\d.]+)/', $params, $matches)) {
                    $quality = (float) $matches[1];
                }
            } else {
                $type = $part;
                $quality = 1.0;
            }

            $types[trim($type)] = $quality;
        }

        // Trie par qualité décroissante
        arsort($types);

        return $types;
    }

    /**
     * Retourne le meilleur type MIME accepté parmi ceux proposés
     */
    public static function getBestMatch(ServerRequestInterface $request, array $available): ?string
    {
        $accept = $request->getHeaderLine('Accept');

        if (empty($accept)) {
            return $available[0] ?? null;
        }

        $acceptedTypes = self::parseAcceptHeader($accept);

        // Cherche la meilleure correspondance
        foreach ($acceptedTypes as $acceptedType => $quality) {
            // Support pour */*
            if ($acceptedType === '*/*') {
                return $available[0] ?? null;
            }

            // Support pour type/*
            if (strpos($acceptedType, '/*') !== false) {
                $prefix = str_replace('/*', '', $acceptedType);

                foreach ($available as $type) {
                    if (strpos($type, $prefix) === 0) {
                        return $type;
                    }
                }
            }

            // Correspondance exacte
            if (in_array($acceptedType, $available)) {
                return $acceptedType;
            }
        }

        return null;
    }

    /**
     * Vérifie si la requête est une requête AJAX
     */
    public static function isAjax(ServerRequestInterface $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Détermine si on doit retourner du JSON
     * (combine plusieurs facteurs)
     */
    public static function wantsJson(ServerRequestInterface $request): bool
    {
        // Si c'est une requête AJAX, probablement du JSON
        if (self::isAjax($request)) {
            return true;
        }

        // Si le header Accept contient JSON
        if (self::acceptsJson($request)) {
            return true;
        }

        // Si le Content-Type de la requête est JSON
        $contentType = $request->getHeaderLine('Content-Type');

        if (stripos($contentType, 'application/json') !== false) {
            return true;
        }

        // Si c'est une route API (commence par /api)
        $path = $request->getUri()->getPath();

        if (strpos($path, '/api') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si c'est une requête API
     */
    public static function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        // Commence par /api
        if (strpos($path, '/api') === 0) {
            return true;
        }

        // A un préfixe api dans un sous-domaine
        $host = $request->getUri()->getHost();
        if (strpos($host, 'api.') === 0) {
            return true;
        }

        return false;
    }
}
