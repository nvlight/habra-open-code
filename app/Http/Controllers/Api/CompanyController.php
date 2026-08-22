<?php

namespace App\Http\Controllers\Api;

use App\Enums\PublicationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPublicationRequest;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\PublicationListResource;
use App\Models\Company;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CompanyResource::collection(
            Company::query()
                ->withCount(['publications', 'employees'])
                ->orderByDesc('rating')
                ->paginate(20)
        );
    }

    public function show(string $slug): CompanyResource
    {
        /** @var Company $company */
        $company = Company::query()
            ->with(['representative', 'industries'])
            ->withCount(['publications', 'employees'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new CompanyResource($company);
    }

    public function publications(string $slug, IndexPublicationRequest $request): AnonymousResourceCollection
    {
        $query = Publication::query()
            ->with(['author', 'company', 'hubs', 'tags'])
            ->whereHas('company', fn (Builder $q) => $q->where('slug', $slug))
            ->published();

        if ($request->filled('type')) {
            $query->type(PublicationType::from((string) $request->string('type')));
        }

        $query->reorder()->orderByDesc(
            (string) $request->string('sort', 'new') === 'best' ? 'rating' : 'published_at'
        );

        return PublicationListResource::collection($query->paginate($request->integer('per_page') ?: 20));
    }

    public function employees(string $slug): AnonymousResourceCollection
    {
        /** @var Company $company */
        $company = Company::query()->where('slug', $slug)->firstOrFail();

        $employees = User::query()
            ->whereHas('company', fn (Builder $q) => $q->where('id', $company->id))
            ->orderByDesc('rating')
            ->paginate(20);

        return AuthorResource::collection($employees);
    }
}
