<?php

namespace App\Http\Controllers\Api\Content;

use App\Content\ContentSchemaDocument;
use App\Services\ApiIdempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchemaController extends ContentApiController
{
    public function __construct(
        ApiIdempotency $idempotency,
        private readonly ContentSchemaDocument $schema,
    ) {
        parent::__construct($idempotency);
    }

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->schema->toArray(),
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }
}
