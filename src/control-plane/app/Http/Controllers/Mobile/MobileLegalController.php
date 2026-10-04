<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Support\LegalDocument;
use Illuminate\Http\JsonResponse;

/**
 * The terms and privacy policy for the apps, in the Accept-Language locale.
 * Public, like /terms and /privacy, so the invite form can show them.
 */
class MobileLegalController extends Controller
{
    public function __invoke(string $document): JsonResponse
    {
        return response()->json(['data' => LegalDocument::build($document)]);
    }
}
