<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassifyTextRequest;
use App\Services\TextClassificationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ClassificationController extends Controller
{
    /** @var TextClassificationService */
    private $service;

    public function __construct(TextClassificationService $service)
    {
        $this->service = $service;
    }

    public function classify(ClassifyTextRequest $request): JsonResponse
    {
        try {
            $result = $this->service->classify($request->validated()['text']);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to classify text. Please try again.',
            ], 502);
        }
    }
}
