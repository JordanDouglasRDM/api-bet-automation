<?php

declare(strict_types = 1);

namespace App\Http\Utilities;

use Throwable;

class ServiceResponse
{
    /**
     * @param array<string, mixed> $cookie
     */
    public function __construct(
        public bool       $success,
        public int        $status,
        public ?string    $message = null,
        public mixed      $data = null,
        public ?Throwable $throw = null,
        public array      $cookie = [],
    ) {
    }

    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * Cria uma resposta de sucesso.
     *
     * @param array<string, mixed> $cookie
     */
    public static function success(
        mixed  $data = null,
        string $message = 'Sucesso na requisição',
        int    $status = 200,
        array  $cookie = [],
    ): self {
        return new self(true, $status, $message, $data, null, $cookie);
    }

    /**
     * Cria uma resposta de erro.
     *
     * @param Throwable|mixed $throw
     */
    public static function error(mixed $throw, int $status = 500, ?string $message = null): self
    {
        return new self(false, $status, $message, null, $throw);
    }
}
