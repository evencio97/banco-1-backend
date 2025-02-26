<?php

namespace App\Http\Controllers;

use Mail;
use App\User;
use App\Audit;
use App\Account;
use App\Transfer;
use Carbon\Carbon;
use App\JuristicUser;
use App\CreditCardPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends BaseController
{
    public function create(Request $request){
        try {
            $user = $request->get('user');
            if (!$user || $user->type != 3){
                return response()->json(['success' => false, 'message' => 'Have to be admin to make this operation'], 422);
            }
            $rules = [
                'user_id' => 'required|integer',
                'user_table' => 'required|string',
                'type' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            if ($request->user_table != 'users' && $request->user_table != 'juristic_users'){
                return response()->json(['success' => false, 'message' => 'The user table is incorrect'], 422);
            }
            if (($request->user_table == 'users' && !User::find($request->user_id)) || 
                ($request->user_table == 'juristic_users') && !JuristicUser::find($request->user_id)){
                return response()->json(['success' => false, 'message' => 'The user id dont exist'], 422);
            }
            if ($request->type != 'ahorro' && $request->type != 'corriente'){
                return response()->json(['success' => false, 'message' => 'The account type is incorrect'], 422);
            }
            if (Account::where('aco_user', $request->user_id)->where('aco_type', $request->type)->first()){
                return response()->json(['success' => false, 'message' => 'The user already have an account of this type'], 422);
            }

            $digits = 17;
            do {
                $number = "001" . strval(random_int(pow(10, $digits-1), pow(10, $digits)-1));
            } while (Account::find($number));
            
            DB::beginTransaction();
            $account = new Account([
                'aco_number' => $number,
                'aco_user' => $request->user_id,
                'aco_user_table' => $request->user_table,
                'aco_balance' => $request->get('balance') && $request->balance>0? $request->balance:0,
                'aco_type' => $request->type,
                'aco_status' => $request->get('status') && $request->status == 0? $request->status:1,
            ]);
            $account->save();

            Audit::saveAudit($account->aco_user, $account->aco_user_table, $number, 'accounts', 'create', $request->ip());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully created the account'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function update(Request $request){
        try {
            $rules = [
                'number' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $account = Account::find($request->number);
            if (!$account){
                return response()->json(['success' => false, 'message' => 'The account dont exist'], 422);
            }
            $user = $request->get('user');
            if (!$user || $user->type != 3){
                return response()->json(['success' => false, 'message' => 'Have to be admin to make this operation'], 422);
            }

            if ($request->get('status')) $account->aco_status = $request->status; 
            if ($request->get('balance')) $account->aco_balance = $request->balance; 
            if ($request->get('balance_lock')) $account->aco_balance_lock = $request->balance_lock; 
            if ($request->get('type')) $account->aco_type = $request->type; 
            
            DB::beginTransaction();

            $account->save();

            Audit::saveAudit($user->id, 3, $request->number, 'accounts', 'update', $request->ip());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully updated the account'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function deposit(Request $request){
        try {
            $rules = [
                'number' => 'required|string',
                'amount' => 'required|integer',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $account = Account::find($request->number);
            if (!$account){
                return response()->json(['success' => false, 'message' => 'The account dont exist'], 422);
            }
            if ($account->aco_status == 0){
                return response()->json(['success' => false, 'message' => 'The account is blocked up'], 422);
            }
            $user = $request->get('user');
            if (!$user || $user->id != $account->aco_user){
                return response()->json(['success' => false, 'message' => 'You dont are the owner of this account'], 422);
            }
            $password = DB::table($account->aco_user_table)->where('id', $account->aco_user)->first();
            if (!Hash::check($request->password, $password->password)){
                return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            }
            if ($request->amount <= 0){
                return response()->json(['success' => false, 'message' => 'The amount of the deposit is incorrect'], 422);
            }
            
            DB::beginTransaction();
            
            $account->aco_balance += $request->amount; 
            $account->save();

            Audit::saveAudit($account->aco_user, $account->aco_user_table, $account->aco_number, 'accounts', 'deposit', $request->ip());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully deposit in the account'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function getAccount(Request $request){
        try {
            $rules = [
                'number' => 'required|string|exists:accounts,aco_number'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $account = Account::find($request->number);
            $user = $request->get('user');
            if (!$user || ($user->get('id') != $account->aco_user && $user->type != 3)){
                return response()->json(['success' => false, 'message' => 'You dont are the owner of the account'], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'The operation has been successfully processed',
                'account' => $account
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function getAccountsAdmin(Request $request){
        try {
            $rules = [
                'user_id' => 'required|string',
                'user_table' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $user = $request->get('user');
            if (!$user || $user->type != 3){
                return response()->json(['success' => false, 'message' => 'Have to be admin to make this operation'], 422);
            }
            if ($request->user_table != 'users' || $request->user_table != 'juristic_users'){
                return response()->json(['success' => false, 'message' => 'Invalid user table'], 422);
            }
            if (!DB::table($request->user_table)->where('id', $request->user_id)-first()){
                return response()->json(['success' => false, 'message' => 'The user doesnt exist'], 422);
            }
            $accounts = Account::where('aco_user_table', $request->user_table)->where('aco_user', $request->user_id)
                                ->orderBy('aco_created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'The operation has been successfully processed',
                'accounts' => $accounts
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function getAllAccountsAdmin(Request $request){
        try {
            $user = $request->get('user');
            if (!$user || $user->type != 3){
                return response()->json(['success' => false, 'message' => 'Have to be admin to make this operation'], 422);
            }
            $accounts = Account::orderBy('aco_created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'The operation has been successfully processed',
                'accounts' => $accounts
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function getAccounts(Request $request){
        try {
            $user = $request->get('user');
            if (!$user || !$user->get('id')){
                return response()->json(['success' => false, 'message' => 'You have to be logged in'], 422);
            }
            $accounts = Account::where('aco_user_table', isset($user->jusr_rif)? 'juristic_users':'users')->where('aco_user', $user->id)
                                ->when($request->get('status'), function ($query) use ($request) {
                                    return $query->where('aco_status', $request->status);
                                })->orderBy('aco_created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'The operation has been successfully processed',
                'accounts' => $accounts
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function getAccountMoves(Request $request){
        try {
            $rules = [
                'number' => 'required|string|exists:accounts,aco_number',
                'option' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $account = Account::find($request->number);
            $user = $request->get('user');
            if (!$user || ($user->get('id') != $account->aco_user && $user->type != 3)){
                return response()->json(['success' => false, 'message' => 'You dont are the owner of the account'], 422);
            }

            $date = null;
            if ($request->option != 'custom'){
                if($request->option == 'today') $date = Carbon::now()->format('Y-m-d');
                if($request->option == 'week') $date = Carbon::now()->subDays(7)->format('Y-m-d');
                if($request->option == 'month') $date = Carbon::now()->subDays(30)->format('Y-m-d');
            }

            $transfers = Transfer::where(function ($query) use ($account) {
                                        $query->where('tra_account_emitter', $account->aco_number)
                                        ->orWhere('tra_account_receiver', $account->aco_number);
                                    })
                                    ->when($request->option!='custom' && $date, function ($query) use ($date) {
                                        return $query->whereDate('created_at', '>=', $date);
                                    })
                                    ->when($request->option=='custom' && $request->get('maxdate') && $request->get('mindate'), function ($query) use ($request) {
                                        return $query->whereDate('created_at', '>=', $request->mindate)
                                                    ->whereDate('created_at', '<=', $request->maxdate);
                                    })->orderBy('created_at', 'desc')->paginate(15);

            $ccpayments = CreditCardPayment::where('ccp_account', $account->aco_number)
                                    ->when($request->option!='custom' && $date, function ($query) use ($date) {
                                        return $query->whereDate('created_at', '>=', $date);
                                    })
                                    ->when($request->option=='custom' && $request->get('maxdate') && $request->get('mindate'), function ($query) use ($request) {
                                        return $query->whereDate('created_at', '<=', $request->mindate)
                                                    ->whereDate('created_at', '>=', $request->maxdate);
                                    })->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true, 'message' => 'The operation has been successfully processed',
                'account' => $account, 'transfers' => $transfers, 'ccpayments' => $ccpayments
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function transferSameBank(Request $request){
        try {
            $rules = [
                'emitter' => 'required|string|exists:accounts,aco_number',
                'receiver' => 'required|string|exists:accounts,aco_number',
                'type' => 'required|string',
                'identifier' => 'required|integer',
                'amount' => 'required|integer',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            //validate amount
            if ($request->amount < 1) return response()->json(['success' => false, 'message' => 'The amount is incorrect'], 422);
            $emitter = Account::find($request->emitter);
            //Validate user
            $user = $request->get('user');
            if (!$user || $user->id != $emitter->aco_user) return response()->json(['success' => false, 'message' => 'You dont are the owner of the emitter account'], 422);
            $password = DB::table($emitter->aco_user_table)->where('id', $emitter->aco_user)->first();
            if (!Hash::check($request->password, $password->password)) return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            //validate emitter acount
            if ($emitter->aco_status != 1) return response()->json(['success' => false, 'message' => 'The account is not active'], 422);
            if ($request->amount > $emitter->aco_balance) return response()->json(['success' => false, 'message' => 'You dont have enough money'], 422);
            // Validate identifier type            
            if ($request->type!='V' && $request->type!='v' && $request->type != 'j' && $request->type != 'J'
                && $request->type != 'e' && $request->type != 'E'){
                return response()->json(['success' => false, 'message' => 'The identifier type is incorrect'], 422);
            }
            // validate receiver account
            $receiver_table = $request->type=='j' || $request->type=='J'? 'juristic_users':'users';
            $receiver = Account::where('aco_number', $request->receiver)
                            ->join($receiver_table, 'id', '=', 'aco_user')->first();
            // $user_receiver = DB::table($receiver->aco_user_table)->where('id', $receiver->aco_user)->first();
            if (!$receiver) return response()->json(['success' => false, 'message' => 'The receiver account dont exists'], 422);
            if (($receiver_table == 'juristic_users' && $receiver->jusr_rif != $request->identifier) ||
                ($receiver_table == 'users' && $receiver->user_ci != $request->identifier)) {
                return response()->json(['success' => false, 'message' => 'The identifier is incorrect'], 422);
            }
            if ($receiver->aco_status != 1) return response()->json(['success' => false, 'message' => 'You cant transfer to a no active account'], 422);
            
            DB::beginTransaction();
            
            $emitter->aco_balance -= $request->amount; 
            $emitter->save();
            $receiver->aco_balance += $request->amount; 
            $receiver->save();
            $transfer = new Transfer([
                'tra_account_emitter' => $request->emitter, 
                'tra_account_receiver' => $request->receiver,
                'tra_description' => $request->get('description'),
                'tra_amount' => $request->amount,
                'tra_status' => 1,
                'tra_client_ip' => $request->ip()
            ]);
            $transfer->save();

            Audit::saveAudit($emitter->aco_user, $emitter->aco_user_table, $transfer->tra_number, 'transfers', 'create', $request->ip());
            
            DB::commit();
            try {
                Mail::send([], [], function ($message) use ($transfer, $receiver, $receiver_table) {
                    $message->from('banco1enlinea@gmail.com', 'Banco 1')
                    ->replyTo('banco1enlinea@gmail.com', 'Banco 1')
                    ->to($receiver_table == 'juristic_users'? $receiver->jusr_email:$receiver->email)->subject('Banco 1 Siempre Contigo')
                    ->setBody('Ha recibido una transferencia con codigo de referencia '.$transfer->tra_number.' por '.$transfer->tra_amount.'BsS');
                });
            } catch (\Exception $e) {}

            return response()->json([
                'success' => true, 'message' => 'The transfer has been successfully processed',
                'transfer' => ['number' => $transfer->tra_number, 'status' => $transfer->tra_status, 'amount' => $transfer->tra_amount]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function transferOtherBank(Request $request){
        try { 
            return response()->json([
                'success' => true, 'message' => 'Under construction',
                'transfer' => []
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function receive(Request $request){
        try {
            $rules = [
                'emitter' =>'required|string',
                'receiver' => 'required|string|exists:accounts,aco_number',
                'amount' => 'required|integer',
                'key' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            //Validate bank key
            $bank = Bank::where('bnk_key', $request->key)->first();
            if (!$bank) return response()->json(['success' => false, 'message' => 'The key is incorrect'], 422);
            //validate amount
            if ($request->amount < 1) return response()->json(['success' => false, 'message' => 'The amount is incorrect'], 422);
            //validate emitter account
            if ($bank->bnk_id == substr($request->receiver, 0, 3) && strlen($request->emitter) == 20) return response()->json(['success' => false, 'message' => 'The emitter account is incorrect'], 422);
            //validate receiver account
            $receiver = Account::find($request->receiver);
            if ($receiver->aco_status != 1) return response()->json(['success' => false, 'message' => 'The account is not active'], 422);
            DB::beginTransaction();
            
            $receiver->aco_balance += $request->amount; 
            $receiver->save();
            $transfer = new Transfer([
                'tra_account_emitter' => $request->emitter, 
                'tra_account_receiver' => $request->receiver,
                'tra_bank' => $bank->bnk_id,
                'tra_description' => $request->get('description'),
                'tra_amount' => $request->amount,
                'tra_type' => 1,
                'tra_status' => 1,
                'tra_client_ip' => $request->ip()
            ]);
            $transfer->save();

            Audit::saveAudit($request->receiver, 'accounts', $transfer->tra_number, 'transfers', 'receive', $request->ip());
            $user_receiver = DB::table($receiver->aco_user_table)->where('id', $receiver->aco_user)->first();
            
            DB::commit();
            try {
                Mail::send([], [], function ($message) use ($transfer, $user_receiver, $bank) {
                    $message->from('banco1enlinea@gmail.com', 'Banco 1')
                    ->replyTo('banco1enlinea@gmail.com', 'Banco 1')
                    ->to($user_receiver->get('jusr_rif')? $user_receiver->jusr_email:$user_receiver->email)->subject('Banco 1 Siempre Contigo')
                    ->setBody('Ha recibido una transferencia con codigo de referencia '.$transfer->tra_number.' por '.$transfer->tra_amount.'BsS desde una cuenta en '.$bank->bnk_name);
                });
            } catch (\Exception $e) {}

            return response()->json([
                'success' => true, 'message' => 'The transfer has been successfully processed',
                'transfer' => ['number' => $transfer->tra_number, 'status' => $transfer->tra_status, 'amount' => $transfer->tra_amount]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }
}
