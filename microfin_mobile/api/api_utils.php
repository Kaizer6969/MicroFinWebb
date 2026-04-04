<?php

function microfin_api_bootstrap(): void
{
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        exit;
    }
}

function microfin_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        microfin_json_response(['success' => false, 'message' => 'Invalid Request'], 405);
    }
}

function microfin_read_json_input(): array
{
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function microfin_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function microfin_clean_string($value): string
{
    return trim((string) ($value ?? ''));
}
