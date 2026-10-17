<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Actions\Users\CreateUser;
use App\Modules\Foundation\Exports\UsersExporter;
use App\Modules\Foundation\Http\Requests\ExportRequest;
use App\Modules\Foundation\Http\Requests\ImportRequest;
use App\Modules\Foundation\Imports\UsersImporter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export and import the users of Core's users list. It lives in Foundation
 * because Core does not depend on the export and import engine.
 *
 * The permissions are checked by the routes, before the request is validated.
 */
class UserTransferController extends Controller
{
    /**
     * Export the users matching the filters and sorting of the users list.
     */
    public function export(ExportRequest $request): Response
    {
        $query = QueryBuilder::for(User::query()->with('roles'))
            ->allowedFilters(
                SearchFilter::on('name', 'email'),
                AllowedFilter::callback('role', fn (Builder $query, string $role) => $query->whereRelation('roles', 'name', $role))->delimiter(''),
            )
            ->allowedSorts('name', 'email', 'created_at')
            ->defaultSort('-created_at');

        return UsersExporter::from($query)->download($request->exportFormat());
    }

    public function import(ImportRequest $request, CreateUser $createUser): RedirectResponse
    {
        $result = (new UsersImporter($request->user(), $createUser))->import($request->spreadsheet());

        Inertia::flash([
            'toast' => [
                'type' => $result->hasFailures() ? 'warning' : 'success',
                'message' => trans_choice('{0} No users were imported.|{1} One user was imported.|[2,*] :count users were imported.', $result->imported),
            ],
            'importResult' => $result->toArray(),
        ]);

        return back();
    }

    /**
     * An empty spreadsheet with the columns the import expects.
     */
    public function template(Request $request, CreateUser $createUser): Response
    {
        return (new UsersImporter($request->user(), $createUser))->template('users-import-template.xlsx');
    }
}
