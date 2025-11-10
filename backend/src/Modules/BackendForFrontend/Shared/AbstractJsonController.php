<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared;

use App\Modules\BackendForFrontend\Shared\Exception\HttpAwareExceptionInterface;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractJsonController extends AbstractController
{
    public function __construct(
        protected ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        private bool $debug = false,
    ) {
    }

    protected function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param callable(): mixed $callback
     */
    protected function execute(callable $callback, int $successStatus = Response::HTTP_OK): JsonResponse
    {
        try {
            $payload = $callback();

            return $this->jsonSuccess($payload, $successStatus);
        } catch (HttpAwareExceptionInterface $exception) {
            return $this->jsonError(
                $exception->getPublicMessage(),
                $exception->getStatusCode(),
                $exception->getContext(),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return $this->jsonError('Wewnętrzny błąd serwera', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param array<string, string> $headers
     */
    protected function jsonSuccess(mixed $data, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return $this->json($data, $status, $headers);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function jsonMessage(string $message, int $status = Response::HTTP_OK, array $context = []): JsonResponse
    {
        return $this->json(
            array_merge(['message' => $message], $context),
            $status,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function jsonError(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $context = [],
    ): JsonResponse {
        return $this->json(
            array_merge(['message' => $message], $context),
            $status,
        );
    }

    protected function validateDto(object $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (0 === $violations->count()) {
            return;
        }

        $errors = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath() ?: '_';
            $errors[$propertyPath][] = $violation->getMessage();
        }

        throw new ValidationException(errors: $errors);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonBody(Request $request): array
    {
        if ('' === $request->getContent()) {
            return [];
        }

        $decoded = json_decode((string) $request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Nieprawidłowy format JSON');
        }

        return $decoded;
    }
}
