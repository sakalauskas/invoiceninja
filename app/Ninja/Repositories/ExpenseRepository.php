<?php namespace app\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Expense;
use App\Models\Vendor;
use App\Ninja\Repositories\BaseRepository;
use Session;

class ExpenseRepository extends BaseRepository
{
    // Expenses
    public function getClassName()
    {
        return 'App\Models\Expense';
    }

    public function all()
    {
        return Expense::scope()
                ->with('user')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function findVendor($vendorPublicId)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->where('expenses.vendor_id', '=', $vendorPublicId)
                    ->select(
                        'expenses.id',
                        'expenses.expense_date',
                        'expenses.amount',
                        'expenses.public_notes',
                        'expenses.public_id',
                        'expenses.deleted_at',
                        'expenses.should_be_invoiced',
                        'expenses.created_at'
                    );

        return $query;
    }

    public function find($filter = null)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->leftjoin('clients', 'clients.id', '=', 'expenses.client_id')
                    ->leftjoin('vendors', 'vendors.id', '=', 'expenses.vendor_id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'expenses.invoice_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->select('expenses.account_id',
                        'expenses.amount',
                        'expenses.currency_id',
                        'expenses.deleted_at',
                        'expenses.exchange_rate',
                        'expenses.expense_date',
                        'expenses.id',
                        'expenses.is_deleted',
                        'expenses.private_notes',
                        'expenses.public_id',
                        'expenses.invoice_id',
                        'expenses.public_notes',
                        'expenses.should_be_invoiced',
                        'expenses.vendor_id',
                        'invoices.public_id as invoice_public_id',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id',
                        'accounts.country_id as account_country_id',
                        'accounts.currency_id as account_currency_id',
                        'clients.country_id as client_country_id'
                    );

        $showTrashed = \Session::get('show_trash:expense');

        if (!$showTrashed) {
            $query->where('expenses.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('expenses.public_notes', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($input)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($publicId) {
            $expense = Expense::scope($publicId)->firstOrFail();
        } else {
            $expense = Expense::createNew();
        }

        // First auto fill
        $expense->fill($input);

        $expense->expense_date = Utils::toSqlDate($input['expense_date']);
        $expense->private_notes = trim($input['private_notes']);
        $expense->public_notes = trim($input['public_notes']);
        $expense->should_be_invoiced = isset($input['should_be_invoiced']) || $expense->client_id ? true : false;

        $rate = isset($input['exchange_rate']) ? Utils::parseFloat($input['exchange_rate']) : 1;
        $expense->exchange_rate = round($rate, 4);
        $expense->amount = round(Utils::parseFloat($input['amount']), 2);

        $expense->save();

        return $expense;
    }

    public function bulk($ids, $action)
    {
        $expenses = Expense::withTrashed()->scope($ids)->get();

        foreach ($expenses as $expense) {
            if ($action == 'restore') {
                $expense->restore();

                $expense->is_deleted = false;
                $expense->save();
            } else {
                if ($action == 'delete') {
                    $expense->is_deleted = true;
                    $expense->save();
                }

                $expense->delete();
            }
        }

        return count($tasks);
    }
}
