<?php

namespace App\Helper;

use Illuminate\Http\JsonResponse;

final class ServiceResponse
{
    public function __construct(
        public readonly mixed $data = null,
        public readonly string $message = '',
        public readonly int $code = 200
    ) {}

    public static function success(mixed $data = null, string $message = '', int $code = 200): self
    {
        return new self($data, $message, $code);
    }

    public static function error(string $message = 'Erro interno', int $code = 500, mixed $data = null): self
    {
        return new self($data, $message, $code);
    }

    public function toResponse(): JsonResponse
    {
        $body = [];
        if ($this->message !== '') $body['message'] = $this->message;
        if ($this->data !== null)  $body['data']    = $this->data;

        return response()->json($body, $this->code);
    }
}
