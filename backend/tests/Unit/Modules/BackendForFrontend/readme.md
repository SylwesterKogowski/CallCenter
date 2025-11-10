# BackendForFrontend unit tests

This suite validates the HTTP controllers that expose the BackendForFrontend layer. Each controller has a dedicated test class under `tests/Unit/Modules/BackendForFrontend/**`, mirroring the production namespace layout.

## Conventions
- Extend `Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase` to access common domain service mocks, request helpers and worker fixtures.
- Describe the expected scenarios for every controller as `TODO` items before implementing assertions. Keep at least three scenarios: validation failures, authorization failures, and successful responses.
- Use `createJsonRequest()` to build Symfony requests with JSON payloads and `stubAuthenticatedWorkerProvider()` to inject authenticated users.
- Prefer PHPUnit mocks for collaborating services. When the real service is not implemented yet, rely on stubs living in `backend/src/Modules/**/Application/Stub`.

## Running the suite
Execute all unit tests from the backend root:

```bash
./exec.sh composer run test
```

Run targeted tests with PHPUnit directly if needed:

```bash
./vendor/bin/phpunit --filter BackendForFrontend tests/Unit/Modules/BackendForFrontend
```

## Next steps
- Fill in the scenario TODOs with concrete test cases once controller behavior stabilises.
- Add reusable assertion helpers to `Shared/` when more controllers share the same expectations (e.g. pagination assertions, authorization shortcuts).
