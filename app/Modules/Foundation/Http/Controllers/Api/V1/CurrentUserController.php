<?php

namespace App\Modules\Foundation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Http\Resources\Api\ApiUserResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CurrentUserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/me',
        summary: 'The signed-in user, with their roles and permissions',
        security: [['bearer' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(response: 200, description: 'The user', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function __invoke(Request $request): ApiUserResource
    {
        return ApiUserResource::make($request->user()->load('roles'));
    }
}
