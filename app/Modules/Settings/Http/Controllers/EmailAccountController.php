<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\EmailAccountRequest;
use App\Modules\Settings\Http\Resources\EmailAccountRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use KhattakBits\Core\Models\EmailAccount;
use KhattakBits\Core\Support\Mailer;
use KhattakBits\Core\Support\TableQuery;

class EmailAccountController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/EmailAccounts/Index');
    }

    public function data(Request $request)
    {
        $query = EmailAccount::query()->select(
            'id', 'name', 'from_email', 'from_name', 'host', 'port',
            'encryption', 'is_default', 'is_active', 'last_tested_at', 'last_test_ok',
        );

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'name' => 'name', 'host' => 'host', 'status' => 'is_active'],
            'default' => 'name',
            'dir' => 'asc',
            'count' => true,
            'filters' => [
                'status' => function ($q, $v) {
                    if (in_array($v, ['active', 'disabled'], true)) {
                        $q->where('is_active', $v === 'active');
                    }
                },
            ],
            'search' => fn ($q, $term) => $q->where('name', 'like', $term.'%'),   // prefix: index use hota hai
        ], EmailAccountRow::class);
    }

    public function create(Request $request)
    {
        return Inertia::render('Settings/EmailAccounts/Form', [
            'account' => null,
            'testTo' => $request->user()->email,
        ]);
    }

    public function store(EmailAccountRequest $request)
    {
        $data = $request->validated();

        $account = DB::transaction(function () use ($request, $data) {
            $account = new EmailAccount([
                'name' => $data['name'],
                'from_email' => strtolower($data['from_email']),
                'from_name' => $data['from_name'] ?? null,
                'host' => $data['host'],
                'port' => (int) $data['port'],
                'encryption' => $data['encryption'],
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'is_default' => $request->boolean('is_default'),
            ]);
            $account->save();

            if ($account->is_default) {
                EmailAccount::makeDefault($account);
            }

            Audit::log('email_account.created', $account, $account->name, [
                'default' => (bool) $account->is_default,
                'active' => (bool) $account->is_active,
            ]);
            Mailer::forgetReady();

            return $account;
        });

        return redirect("/settings/email-accounts/{$account->id}")->with('success', 'Email account created.');
    }

    public function edit(Request $request, EmailAccount $account)
    {
        return Inertia::render('Settings/EmailAccounts/Form', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'from_email' => $account->from_email,
                'from_name' => $account->from_name,
                'host' => $account->host,
                'port' => (string) $account->port,
                'encryption' => $account->encryption,
                'username' => $account->username,
                'has_password' => filled($account->password),   // password khud kabhi prop mein nahi jata
                'is_active' => (bool) $account->is_active,
                'is_default' => (bool) $account->is_default,
                'last_tested_at' => $account->last_tested_at?->toIso8601String(),
                'last_test_ok' => $account->last_test_ok,
            ],
            'testTo' => $request->user()->email,
        ]);
    }

    public function update(EmailAccountRequest $request, EmailAccount $account)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $account, $data) {
            $before = $this->snapshot($account);

            $account->fill([
                'name' => $data['name'],
                'from_email' => strtolower($data['from_email']),
                'from_name' => $data['from_name'] ?? null,
                'host' => $data['host'],
                'port' => (int) $data['port'],
                'encryption' => $data['encryption'],
                'username' => $data['username'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'is_default' => $request->boolean('is_default'),
            ]);

            if (! empty($data['password'])) {
                $account->password = $data['password'];   // khali ho to purana password hi rehta hai
            }

            $account->save();

            if ($account->is_default) {
                EmailAccount::makeDefault($account);
            } else {
                EmailAccount::ensureDefault();            // koi default na rahe to agla active account
            }

            Audit::log('email_account.updated', $account, $account->name, [
                'changes' => Audit::changes($before, $this->snapshot($account->refresh())),
                'password_changed' => ! empty($data['password']),
            ]);
            Mailer::forgetReady();
        });

        return back()->with('success', 'Email account saved.');
    }

    public function destroy(EmailAccount $account)
    {
        $name = $account->name;

        DB::transaction(function () use ($account, $name) {
            $account->delete();
            Audit::log('email_account.deleted', null, $name);
        });

        Mailer::forgetReady();

        return redirect('/settings/email-accounts')->with('success', 'Email account deleted.');
    }

    /** POST /settings/email-accounts/{account}/test — synchronous, queue bypass */
    public function test(Request $request, EmailAccount $account)
    {
        $data = $request->validate(['to' => ['nullable', 'email', 'max:190']]);
        $to = $data['to'] ?? $request->user()->email;

        $result = Mailer::sendTest($account, $to);

        Audit::log('email_account.tested', $account, $account->name, ['ok' => $result['ok']]);

        return response()->json($result);
    }

    /** Password kabhi snapshot mein nahi — sirf boolean */
    private function snapshot(EmailAccount $a): array
    {
        return [
            'name' => $a->name,
            'from_email' => $a->from_email,
            'from_name' => $a->from_name,
            'host' => $a->host,
            'port' => (int) $a->port,
            'encryption' => $a->encryption,
            'username' => $a->username,
            'is_active' => (bool) $a->is_active,
            'is_default' => (bool) $a->is_default,
        ];
    }
}
