<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\UploadTransactionImport;
use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UploadTransactionImportRequest;
use App\Models\FinancialAccount;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TransactionImportController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    public function store(UploadTransactionImportRequest $request, Team $currentTeam, FinancialAccount $financialAccount, UploadTransactionImport $upload): JsonResponse
    {
        $result = $upload->handle($this->authenticatedUser->resolve($request), $currentTeam, $financialAccount, $request->file('file'));

        return response()->json([
            'batch' => $result['batch']->only(['id', 'financial_account_id', 'status', 'total_rows', 'processed_rows', 'mapping_version']),
            'reused' => $result['reused'],
        ], $result['reused'] ? 200 : 201);
    }
}
