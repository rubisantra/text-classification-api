<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateEmailRequest;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EmailTemplateController extends Controller
{
    /** @var EmailTemplateService */
    private $service;

    public function __construct(EmailTemplateService $service)
    {
        $this->service = $service;
    }

    public function generate(GenerateEmailRequest $request): JsonResponse
    {
        try {
            $result = $this->service->generate($request->validated());

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Email generation failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate email template. Please try again.',
            ], 500);
        }
    }
}