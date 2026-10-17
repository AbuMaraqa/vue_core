<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Actions\Users\CreateUser;
use App\Modules\Core\Actions\Users\UpdateUser;
use App\Modules\Core\Data\UserData;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Http\Requests\Users\StoreUserRequest;
use App\Modules\Core\Http\Requests\Users\UpdateUserRequest;
use App\Modules\Core\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    /**
     * Display a filterable, sortable list of users.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::query()->with('roles'))
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->whereAny(['name', 'email'], 'like', "%{$search}%"))->delimiter(''),
                AllowedFilter::callback('role', fn (Builder $query, string $role) => $query->whereRelation('roles', 'name', $role))->delimiter(''),
            )
            ->allowedSorts('name', 'email', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => UserResource::collection($users),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'filters' => [
                'search' => $request->input('filter.search'),
                'role' => $request->input('filter.role'),
                'sort' => $request->input('sort', '-created_at'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/Create', [
            'roles' => $this->assignableRoles($request),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $createUser->handle(UserData::from($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/Edit', [
            'user' => UserResource::make($user->load('roles')),
            'roles' => $this->assignableRoles($request),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($user, UserData::from($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Get the roles the current user is allowed to grant.
     *
     * @return list<string>
     */
    protected function assignableRoles(Request $request): array
    {
        $roles = Role::query()
            ->when(! $request->user()->isSuperAdmin(), fn (Builder $query) => $query->whereNot('name', UserRole::SuperAdmin->value))
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): string => $role->name);

        return array_values($roles->all());
    }
}
