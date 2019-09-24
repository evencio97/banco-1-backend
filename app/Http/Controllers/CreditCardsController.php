<?php

namespace App\Http\Controllers;

use App\User;
use App\Audit;
use App\Account;
use App\Transfer;
use App\Purchases;
use Carbon\Carbon;
use App\CreditCard;
use App\CreditCardPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditCardsController extends BaseController
{
    public function create(Request $request){
        try {
            $user = $request->user();
            if (!$user || $user->type != 3){
                return response()->json(['success' => false, 'message' => 'Have to be admin to make this operation'], 422);
            }
            $rules = [
                'user_id' => 'required|integer|exists:users,id',
                'limit' => 'required|numeric',
                'interest' => 'required|numeric',
                'minimum' => 'required|numeric'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            if ($request->limit < 1) return response()->json(['success' => false, 'message' => 'The limit has to be bigger than cero'], 422);
            if ($request->interest < 1 || $request->interest > 100) return response()->json(['success' => false, 'message' => 'The interest has to be between 1 and 100'], 422);
            if ($request->minimum < 1 || $request->minimum > $request->limit) return response()->json(['success' => false, 'message' => 'The minimum has to be bigger than cero and lest than the limit'], 422);

            $digits = 17;
            do {
                $number = "001" . strval(random_int(pow(10, $digits-1), pow(10, $digits)-1));
                $cvv = random_int(pow(10, 3-1), pow(10, 3)-1);
            } while (CreditCard::find($number) || CreditCard::where('cc_cvv', $cvv)->first());
            
            DB::beginTransaction();
            $tdc = new CreditCard([
                'cc_number' => $number,
                'cc_user' => $request->user_id,
                'cc_exp_date' => Carbon::now()->addYears(4)->format('Y-m-d'),
                'cc_cvv' => $cvv,
                'cc_balance' => 0,
                'cc_limit' => $request->limit,
                'cc_interest' => $request->interest,
                'cc_minimum_payment' => $request->minimum,
                'cc_payment_date' => null,
            ]);
            $tdc->save();

            Audit::saveAudit($request->user_id, 'users', $number, 'credit_cards', 'create', $request->ip());
            DB::commit();

            return response()->json([
                'success' => true, 'message' => 'Successfully created the credit card'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function update(Request $request){
        try {
            $user = $request->user();
            if (!$user || $user->type != 3){
                return response()->json(['success' => false, 'message' => 'Have to be admin to make this operation'], 422);
            }
            $rules = [
                'number' => 'required|string|exists:credit_cards,cc_number'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }

            $tdc = CreditCard::find($request->number);
            if ($request->get('status') && $request->status >= 0 && $request->status < 4) $tdc->cc_status = $request->status; 
            if ($request->get('limit') && $request->limit > 0) $account->cc_limit = $request->limit;
            if ($request->get('balance') && $request->balance > 0 && $request->balance < $tdc->cc_limit) $tdc->cc_balance = $request->balance;
            if ($request->get('minimum') && $request->minimum > 0 && $request->minimum < $tdc->cc_limit) $tdc->cc_minimum_payment = $request->minimum;
            if ($request->get('interest') && $request->interest > 0 && $request->interest < 100) $account->cc_interests = $request->interest;

            DB::beginTransaction();
            
            $tdc->save();
            Audit::saveAudit($user->id, 3, $number, 'credit_cards', 'update', $request->ip());
            
            DB::commit();

            return response()->json([
                'success' => true, 'message' => 'Credit card successfully updated'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function changeStatus(Request $request){
        try {
            $rules = [
                'number' => 'required|string|exists:credit_cards,cc_number',
                'status' => 'required|integer'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $user = $request->user();
            if (!$user || !$user->get('id')) return response()->json(['success' => false, 'message' => 'You have to login to do this operation'], 422);
            
            $tdc = CreditCard::where('cc_number',$request->number)->where('cc_user', $user->id)->first();
            if (!$tdc) return response()->json(['success' => false, 'message' => 'You dont are the credit card owner'], 422);
            if ($request->status >= 0 && $request->status < 4) $tdc->cc_status = $request->status; 

            DB::beginTransaction();
            
            $tdc->save();
            Audit::saveAudit($user->id, 3, $number, 'credit_cards', 'update', $request->ip());
            
            DB::commit();

            return response()->json([
                'success' => true, 'message' => 'Credit card successfully updated'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }
    
    public function getCreditCards(Request $request){
        //try {
            $user = $request;//->user();
            // if (!$user && !$user->get('id')){
            //     return response()->json(['success' => false, 'message' => 'You have to be logged in'], 422);
            // }
            $tdcs = CreditCard::where('cc_user', $user->id)->get();
            
            if ($request->get('accounts')){
                $accounts = Account::where('aco_user', $user->id)->where('aco_user_table', 'users')->where('aco_status', 1)->get();
                return response()->json([
                    'success' => true, 'message' => 'The operation has been successfully processed', 'tdcs' => $tdcs, 'accounts' => $accounts
                ], 200);   
            }
            return response()->json([
                'success' => true, 'message' => 'The operation has been successfully processed', 'tdcs' => $tdcs
            ], 200);
        // } catch (\Throwable $e) {
        //     DB::rollBack();
        //     return response()->json([
        //         'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
        //     ], 500);
        // }
    }

    public function getCreditCard(Request $request){
        try {
            $user = $request->user();
            if (!$user && !$user->get('id')){
                return response()->json(['success' => false, 'message' => 'You have to be logged in'], 422);
            }
            $rules = [
                'number' => 'required|string|exists:credit_cards,cc_number'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $tdc = CreditCard::when($user->type != 3, function ($query) use ($user) {
                                    return $query->where('cc_user', $user->id);
                                })->where('cc_number', $request->number)->first();
            if(!$tdc) return response()->json(['success' => false, 'message' => 'The credit card doesnt exists'], 422);

            return response()->json([
                'success' => true, 'message' => 'The operation has been successfully processed', 'tdc' => $tdc
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function getCreditCardsAdmin(Request $request){
        try {
            $user = $request->user();
            if (!$user && !$user->get('type') != 3){
                return response()->json(['success' => false, 'message' => 'You have to be admin to make this operation'], 422);
            }
            $tdcs = CreditCard::when($request->get('expdate'), function ($query) use ($request) {
                                    return $query->where('cc_exp_date', $request->expdate);
                                })->when($request->get('status'), function ($query) use ($request) {
                                    return $query->where('cc_status', $request->status);
                                })->when($request->get('limit'), function ($query) use ($request) {
                                    return $query->where('cc_limit', $request->limit);
                                })->paginate(15);

            return response()->json([
                'success' => true, 'message' => 'The operation has been successfully processed', 'tdcs' => $tdcs
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function payCreditCard(Request $request){
        //try {
            $rules = [
                'number' => 'required|string|exists:credit_cards,cc_number',
                'account' => 'required|string|exists:accounts,aco_number',
                'amount' => 'required|numeric',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $user = $request;//->user();
            if (!$user && !$user->get('id')){
                return response()->json(['success' => false, 'message' => 'You have to be logged in'], 422);
            }
            $account = Account::where('aco_number', $request->account)->where('aco_user', $user->id)
                                ->where('aco_user_table', 'users')->first();
            //validate password
            $password = DB::table('users')->select('password')->where('id', $account->aco_user)
                            ->where('password', $request->password)->first();
            if (!$password) return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            
            //validate account
            if ($account->aco_balance < $request->amount) return response()->json(['success' => false, 'message' => 'You dont have enough money in this account'], 422);
            if ($account->aco_status == 0) return response()->json(['success' => false, 'message' => 'The account is blocked up'], 422);
            
            //validate tdc
            $tdc = CreditCard::where('cc_user', $user->id)->where('cc_number', $request->number)->first();
            if (!$tdc) return response()->json(['success' => false, 'message' => 'The credit card dont exists or it is not in your name'], 422); 
            if ($tdc->cc_balance <= 0) return response()->json(['success' => false, 'message' => 'The credit card doesnt have any debt'], 422); 
            if ($tdc->cc_balance < $request->amount) return response()->json(['success' => false, 'message' => 'You cant pay more than the amount of your debt'], 422);
            if ($tdc->cc_minimum_payment > $request->amount) return response()->json(['success' => false, 'message' => 'You cant pay lest than the minimum payment of the credit card'], 422);
            
            DB::beginTransaction();

            $account->aco_balance -= $request->amount;
            $account->save();
            $tdc->cc_balance -= $request->amount;
            $tdc->save();
            $payment = new CreditCardPayment([
                'ccp_creditcard' => $tdc->cc_number, 
                'ccp_account' => $account->aco_number,
                'ccp_description' => $request->get('description'),
                'ccp_amount' => $request->amount,
                'ccp_status' => 1,
                'ccp_client_ip' => $request->ip()
            ]);
            $payment->save();
            Audit::saveAudit($user->id, 'users', $payment->ccp_id, 'credit_card_payments', 'create', $request->ip());

            DB::commit();

            return response()->json([
                'success' => true, 'message' => 'Credit card successfully paid'
            ], 200);
        // } catch (\Throwable $e) {
        //     DB::rollBack();
        //     return response()->json([
        //         'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
        //     ], 500);
        // }
    }

    public function purchase(Request $request){
        try {
            $rules = [
                'number' => 'required|string',
                'rif' => 'required|string|exists:juristic_users,jusr_rif',
                'cvv' => 'required|integer',
                'name' => 'required|string',
                'lastname' => 'required|string',
                'expdate' => 'required|date',
                'amount' => 'required|numeric'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $account = Account::where('aco_user_table', 'juristic_users')->join('juristic_users', 'juristic_users.id', '=', 'accounts.aco_user')
                                ->where('juristic_users.jusr_rif', $request->rif)->first();
            if (!$account) return response()->json(['success' => false, 'message' => 'The rif doesnt have any associated account'], 422);
            if ($account->aco_status != 1) return response()->json(['success' => false, 'message' => 'The account isnt active'], 422);            
            if ($request->amount < 1) return response()->json(['success' => false, 'message' => 'The amount of the purchases is invalid'], 422);
            if ($request->expdate < Carbon::now()->format('Y-m-d')) return response()->json(['success' => false, 'message' => 'The credit card has expirate or is wrong'], 422);
            if (strlen(strval($request->cvv)) != 3) return response()->json(['success' => false, 'message' => 'The cvv code has to be 3 digits long'], 422);
            if (strlen($request->number) != 20) return response()->json(['success' => false, 'message' => 'The credit card number has to be 20 digits long'], 422);

            $bank = substr($request->number, 0, 3);
            // if ($bank == '001' ){
                //same bank
                $result = sameBankPurchase($request, $account);
            // } else{
            //     // banco 2
            //     $result = 
            // }

            return $result;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function sameBankPurchase(Request $request, $account){
        try {
            //validate tdc
            $tdc = CreditCard::where('cc_number', $request->number)->join('users', 'users.id', '=', 'cc_user')->first();
            if (!$tdc) return response()->json(['success' => false, 'message' => 'The credit card dont exists'], 422); 
            if ($tdc->cc_cvv != $request->cvv) return response()->json(['success' => false, 'message' => 'The cvv code is incorrect'], 422); 
            if ($tdc->exp_date != $request->expdate) return response()->json(['success' => false, 'message' => 'The expiration date is incorrect'], 422); 
            if ($tdc->name != $request->first_name || $tdc->lastname != $request->first_surname) return response()->json(['success' => false, 'message' => 'The name or lastname is incorrect'], 422); 
            if (($tdc->cc_limit - $tdc->cc_balance) < $request->amount) return response()->json(['success' => false, 'message' => 'The credit card doesnt have enough money'], 422); 
            
            DB::beginTransaction();

            $tdc->cc_balance += $request->amount;
            $tdc->save();
            $account->aco_balance += $request->amount;
            $account->save();
            $purchase = new Purchases([
                'pur_credit_card' => $tdc->cc_number, 
                'pur_business' => $account->jusr_rif,
                'pur_amount' => $request->amount,
                'pur_description' => $request->get('description'),
                'pur_status' => 1,
                'pur_client_ip' => $request->ip()
            ]);
            $purchase->save();
            Audit::saveAudit($tdc->cc_user, 'users', $purchase->pur_id, 'purchases', 'create', $request->ip());

            DB::commit();
            try {
                Mail::send([], [], function ($message) use ($purchase, $tdc) {
                    $message->from('banco1enlinea@gmail.com', 'Banco 1')
                    ->replyTo('banco1enlinea@gmail.com', 'Banco 1')
                    ->to($tdc->email)->subject('Banco 1 Siempre Contigo')
                    ->setBody('Se ha realizado una compra con tdc con codigo de referencia '.$purchase->pur_id.' por '.$purchase->pur_amount.'BsS');
                });
            } catch (\Exception $e) {}

            return response()->json([
                'success' => true, 'message' => 'Purchase successfully process',
                'purchase' => ['refcode' => $purchase->pur_id, 'status' => $purchase->pur_status, 'amount' => $purchase->pur_amount]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }
}
