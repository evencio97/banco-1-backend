<?php

namespace App\Http\Controllers;

use Mail;
use App\Bill;
use App\Audit;
use App\Transfer;
use App\Account;
use Carbon\Carbon;
use App\JuristicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillsController extends BaseController{
    public function create(Request $request){
        try {
            $rules = [
                'emitter' => 'required|string|exists:juristic_users,jusr_rif',
                'receiver' => 'required|string|exists:juristic_users,jusr_rif',
                'refcode' => 'required|string',
                'amount' => 'required|numeric',
                'paydate' => 'required|date',
                'expdate' => 'required|date',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            if ($request->emitter == $request->receiver){
                return response()->json(['success' => false, 'message' => 'Emitter and receiver are the same'], 422);
            }
            $emitter = DB::table('juristic_users')->where('jusr_rif', $request->emitter)->first();
            if ($emitter->password != $request->password){
                return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            }
            if ($request->paydate >= $request->expdate || $request->paydate < Carbon::now()->format('Y-m-d')){
                return response()->json(['success' => false, 'message' => 'The payment or expiration date are incorrect'], 422);
            }
            if ($request->amount <= 0){
                return response()->json(['success' => false, 'message' => 'The amount is incorrect'], 422);
            }
            
            DB::beginTransaction();
            $bill = new Bill([
                'bil_emitter' => $request->emitter,
                'bil_receiver' => $request->receiver,
                'bil_ref_code' => $request->refcode,
                'bil_description' => $request->get('description')? $request->description:null,
                'bil_amount' => $request->amount,
                'bil_paydate' => $request->paydate,
                'bil_expdate' => $request->expdate
            ]);
            $bill->save();

            Audit::saveAudit($emitter->id, 'juristic_users', $bill->bil_id, 'bills', 'create', $request->ip());
            
            $receiver = JuristicUser::where('jusr_rif', $request->receiver)->first();
            
            Mail::send([], [], function ($message) use ($receiver, $bill) {
                $message->from('banco1enlinea@gmail.com', 'Banco 1')
                ->replyTo('banco1enlinea@gmail.com', 'Ines Arenas')
                ->to($receiver->jusr_email)->subject('Banco 1 Siempre Contigo')
                ->setBody('Se ha creado una factura con codigo de referencia '.$bill->bil_ref_code.' a nombre de tu empresa');
            });
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Bill successfully created'
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
                'emitter' => 'required|string|exists:juristic_users,jusr_rif',
                'refcode' => 'required|string',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $emitter = DB::table('juristic_users')->where('jusr_rif', $request->emitter)->first();
            if ($emitter->password != $request->password){
                return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            }
            $bill = Bill::where('bil_emitter', $request->emitter)->where('bil_ref_code', $request->refcode)->first();
            if (!$bill){
                return response()->json(['success' => false, 'message' => 'The bill doesnt exist'], 422);
            }
            if ($bill->bil_status != 0){
                return response()->json(['success' => false, 'message' => 'The bill cant be updated because has been pay'], 422);
            }
            
            if ($request->get('description')) $bill->bil_description = $request->description;
            if ($request->get('amount') && $request->amount>0) $bill->bil_amount = $request->amount;
            
            DB::beginTransaction();

            $bill->save();
            Audit::saveAudit($emitter->id, 'juristic_users', $bill->bil_id, 'bills', 'update', $request->ip());
            $receiver = JuristicUser::where('jusr_rif', $bill->bil_receiver)->first();
            
            Mail::send([], [], function ($message) use ($receiver, $bill) {
                $message->from('banco1enlinea@gmail.com', 'Banco 1')
                ->replyTo('banco1enlinea@gmail.com', 'Ines Arenas')
                ->to($receiver->jusr_email)->subject('Banco 1 Siempre Contigo')
                ->setBody('Se ha actualizado la factura con codigo de referencia '.$bill->bil_ref_code.' a nombre de tu empresa');
            });
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Bill successfully updated'
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

    public function payBill(Request $request){
        try {
            $rules = [
                'bill_id' => 'required|integer|exists:bills,bil_id',
                'account_emitter' => 'required|string|exists:accounts,aco_number',
                'account_receiver' => 'required|string|exists:accounts,aco_number',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if(count($errors)){
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $emitter = Account::where('aco_number', $request->account_emitter)->where('aco_user_table', 'juristic_users')->first();
            $user = $request->user();
            if (!$user || !$user->get('id') || $user->id != $emitter->aco_user){
                return response()->json(['success' => false, 'message' => 'You dont are the owner of the emitter account'], 422);
            }
            //validate password
            $password = DB::table($emitter->aco_user_table)->where('id', $emitter->aco_user)->first();
            if ($password->password != $request->password){
                return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            }
            
            //validate bill
            $bill = Bill::where('bil_emitter', $user->jusr_rif)->where('bil_id', $request->bill_id)->first();
            if (!$bill) return response()->json(['success' => false, 'message' => 'The bill dont exist'], 422); 
            if ($bill->bil_status != 0) return response()->json(['success' => false, 'message' => 'The bill has been already pay'], 422); 
            
            //validate emitter and receiver account
            if ($bill->bil_amount > $emitter->aco_balance) return response()->json(['success' => false, 'message' => 'You dont have enough money'], 422);
            if ($emitter->aco_status == 0) return response()->json(['success' => false, 'message' => 'The account is blocked up'], 422);
            $receiver = Account::find($request->account_receiver);
            if ($receiver->aco_status == 0){
                return response()->json(['success' => false, 'message' => 'You cant transfer to a blocked up account'], 422);
            }
            
            DB::beginTransaction();

            $emitter->aco_balance -= $bill->bil_amount; 
            $emitter->save();
            $receiver->aco_balance += $bill->bil_amount; 
            $receiver->save();
            $transfer = new Transfer([
                'tra_account_emitter' => $request->account_emitter, 
                'tra_account_receiver' => $request->account_receiver,
                'tra_description' => $request->get('description'),
                'tra_amount' => $bill->bil_amount,
                'tra_status' => 1,
                'tra_client_ip' => $request->ip()
            ]);
            $transfer->save();
            $bill->bil_transfer = $transfer->tra_number;
            $bill->bil_status = 1;
            $bill->save();
            Audit::saveAudit($user->id, 'juristic_users', $bill->bil_id, 'bills', 'pay', $request->ip());
            $receiver = JuristicUser::where('jusr_rif', $bill->bil_emitter)->first();

            DB::commit();
            
            Mail::send([], [], function ($message) use ($receiver, $bill) {
                $message->from('banco1enlinea@gmail.com', 'Banco 1')
                        ->replyTo('banco1enlinea@gmail.com', 'Ines Arenas')
                        ->to($receiver->jusr_email)->subject('Banco 1 Siempre Contigo')
                        ->setBody('Se ha pagado su factura con codigo de referencia '.$bill->bil_ref_code);
            });

            return response()->json([
                'success' => true,
                'message' => 'Bill successfully paid'
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

    public function getBills(Request $request){
        try {
            $user = $request->user();
            if (!$user || !$user->get('jusr_rif')){
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            
            //validate bill
            $bills = Bill::where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif)->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Bills successfully acquired',
                'bills' => $bills
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

    public function getPayBills(Request $request){
        try {
            $user = $request->user();
            if (!$user || !$user->get('jusr_rif')){
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            
            //validate bill
            $bills = Bill::where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif)
                            ->where('bil_status', 1)->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Bills successfully acquired',
                'bills' => $bills
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

    public function getOpenBills(Request $request){
        try {
            $user = $request->user();
            if (!$user || !$user->get('jusr_rif')){
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            
            //validate bill
            $bills = Bill::where(function ($query) use ($user) {
                                $query->where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif);
                            })->whereDate('bil_expdate', '>=', Carbon::now()->format('Y-m-d'))->where('bil_status', 0)->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Bills successfully acquired',
                'bills' => $bills
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

    public function getExpBills(Request $request){
        try {
            $user = $request->user();
            if (!$user || !$user->get('jusr_rif')){
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            
            //validate bill
            $bills = Bill::where(function ($query) use ($user) {
                                $query->where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif);
                            })->whereDate('bil_expdate', '<', Carbon::now()->format('Y-m-d'))->where('bil_status', 0)->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Bill successfully created',
                'bills' => $bills
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
}
