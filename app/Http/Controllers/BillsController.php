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
use Illuminate\Support\Facades\Hash;

class BillsController extends BaseController
{

    public function generateRefCode()
    {
        do {
            $number = rand(10000000, 99999999);
        } while (Bill::where('bil_ref_code', $number)->first());
        return $number;
    }

    public function create(Request $request)
    {
        try {
            $user = $request->get('user');
            $refcode = isset($request->bill_ref_cod) ? $request->bill_ref_cod : $this->generateRefCode();
            $rules = [
                'emitter' => 'required|string|exists:juristic_users,jusr_rif',
                'receiver' => 'required|string|exists:juristic_users,jusr_rif',
                'bill_ref_code' => 'string',
                'amount' => 'required|numeric',
                // 'paydate' => 'required|date',
                'expdate' => 'required|date',
                'password' => 'string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if (isset($request->password) && !Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Contraseña invalida'], 422);
            if (count($errors)) {
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            if ($request->emitter == $request->receiver) {
                return response()->json(['success' => false, 'message' => 'Emitter and receiver are the same'], 422);
            }

            if ($request->expdate <= Carbon::now()->format('Y-m-d')) {
                return response()->json(['success' => false, 'message' => 'The expiration date are incorrect'], 422);
            }
            if ($request->amount <= 0) {
                return response()->json(['success' => false, 'message' => 'The amount is incorrect'], 422);
            }

            $receiver = DB::table('juristic_users')->where('jusr_rif', $request->receiver)->first();

            DB::beginTransaction();
            $bill = new Bill([
                'bil_emitter' => $user->jusr_rif,
                'bil_receiver' => $receiver->jusr_rif,
                'bil_ref_code' => $refcode,
                'bil_description' => $request->get('description') ? $request->description : null,
                'bil_amount' => $request->amount,
                //'bil_paydate' => $request->paydate,
                'bil_expdate' => $request->expdate
            ]);
            $bill->save();

            Audit::saveAudit($user->id, 'juristic_users', $bill->bil_id, 'bills', 'create', $request->ip());

            Mail::send([], [], function ($message) use ($receiver, $bill) {
                $message->from('banco1enlinea@gmail.com', 'Banco 1')
                    ->replyTo('banco1enlinea@gmail.com', 'Banco 1')
                    ->to($receiver->jusr_email)->subject('Banco 1 Siempre Contigo')
                    ->setBody('Se ha creado una factura con codigo de referencia ' . $bill->bil_ref_code . ' a nombre de tu empresa');
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

    public function update(Request $request)
    {
        try {
            $rules = [
                'emitter' => 'required|string|exists:juristic_users,jusr_rif',
                'refcode' => 'required|string',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if (count($errors)) {
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            $emitter = DB::table('juristic_users')->where('jusr_rif', $request->emitter)->first();
            if (!Hash::check($request->password, $emitter->password)) {
                return response()->json(['success' => false, 'message' => 'The password is incorrect'], 422);
            }
            $bill = Bill::where('bil_emitter', $request->emitter)->where('bil_ref_code', $request->refcode)->first();
            if (!$bill) {
                return response()->json(['success' => false, 'message' => 'The bill doesnt exist'], 422);
            }
            if ($bill->bil_status != 0) {
                return response()->json(['success' => false, 'message' => 'The bill cant be updated because has been pay'], 422);
            }

            if ($request->get('description')) $bill->bil_description = $request->description;
            if ($request->get('amount') && $request->amount > 0) $bill->bil_amount = $request->amount;

            DB::beginTransaction();

            $bill->save();
            Audit::saveAudit($emitter->id, 'juristic_users', $bill->bil_id, 'bills', 'update', $request->ip());
            $receiver = JuristicUser::where('jusr_rif', $bill->bil_receiver)->first();

            Mail::send([], [], function ($message) use ($receiver, $bill) {
                $message->from('banco1enlinea@gmail.com', 'Banco 1')
                    ->replyTo('banco1enlinea@gmail.com', 'Banco 1')
                    ->to($receiver->jusr_email)->subject('Banco 1 Siempre Contigo')
                    ->setBody('Se ha actualizado la factura con codigo de referencia ' . $bill->bil_ref_code . ' a nombre de tu empresa');
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

    public function payBill(Request $request)
    {
        $user = $request->get('user');
        try {
            $rules = [
                'bill_ref_cod' => 'required|string|exists:bills,bil_ref_code',
                'account_emitter' => 'required|string|exists:accounts,aco_number',
                'account_receiver' => 'required|string|exists:accounts,aco_number',
                'password' => 'required|string'
            ];
            $errors = $this->validateRequest($request, $rules);
            if (count($errors)) {
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Contraseña invalida'], 422);
            $emitter = Account::where('aco_number', $request->account_emitter)->where('aco_user_table', 'juristic_users')->first();

            if (!$user || !$user->get('id') || $user->id != $emitter->aco_user) {
                return response()->json(['success' => false, 'message' => 'You dont are the owner of the emitter account'], 422);
            }

            //validate bill
            $bill = Bill::where('bil_receiver', $user->jusr_rif)->where('bil_ref_code', $request->bill_ref_cod)->first();
            if (!$bill) return response()->json(['success' => false, 'message' => 'The bill dont exist'], 422);
            if ($bill->bil_status != 0) return response()->json(['success' => false, 'message' => 'The bill has been already pay'], 422);

            //validate emitter and receiver account
            if ($bill->bil_amount > $emitter->aco_balance) return response()->json(['success' => false, 'message' => 'You dont have enough money'], 422);
            if ($emitter->aco_status == 0) return response()->json(['success' => false, 'message' => 'The account is blocked up'], 422);
            $receiver = Account::find($request->account_receiver);
            if ($receiver->aco_status == 0) {
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
            $bill->bil_paydate = Carbon::now()->format('Y-m-d');
            $bill->bil_status = 1;
            $bill->save();
            Audit::saveAudit($user->id, 'juristic_users', $bill->bil_id, 'bills', 'pay', $request->ip());
            $receiver = JuristicUser::where('jusr_rif', $bill->bil_emitter)->first();

            DB::commit();

            Mail::send([], [], function ($message) use ($receiver, $bill) {
                $message->from('banco1enlinea@gmail.com', 'Banco 1')
                    ->replyTo('banco1enlinea@gmail.com', 'Banco 1')
                    ->to($receiver->jusr_email)->subject('Banco 1 Siempre Contigo')
                    ->setBody('Se ha pagado su factura con codigo de referencia ' . $bill->bil_ref_code);
            });

            return response()->json([
                'success' => true, 'message' => 'Bill successfully paid'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function getBills(Request $request)
    {
        try {
            $user = $request->get('user');
            if (!$user || !$user->get('jusr_rif')) {
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            $bills = Bill::where(function ($query) use ($user) {
                return $query->where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif);
            })
                ->when($request->get('start'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '>=', $request->start);
                })
                ->when($request->get('end'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '<=', $request->end);
                })->orderBy('bil_created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true, 'message' => 'Bills successfully acquired', 'bills' => $bills
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function getPayBills(Request $request)
    {
        try {
            $user = $request->get('user');
            if (!$user || !$user->get('jusr_rif')) {
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            $bills = Bill::where(function ($query) use ($user) {
                return $query->where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif);
            })
                ->when($request->get('start'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '>=', $request->start);
                })
                ->when($request->get('end'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '<=', $request->end);
                })->where('bil_status', 1)->orderBy('bil_created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true, 'message' => 'Bills successfully acquired', 'bills' => $bills
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function getOpenBills(Request $request)
    {
        try {
            $user = $request->get('user');
            if (!$user || !$user->get('jusr_rif')) {
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            $bills = Bill::where(function ($query) use ($user) {
                $query->where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif);
            })
                ->whereDate('bil_expdate', '>=', Carbon::now()->format('Y-m-d'))->where('bil_status', 0)
                ->when($request->get('start'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '>=', $request->start);
                })
                ->when($request->get('end'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '<=', $request->end);
                })->orderBy('bil_created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true, 'message' => 'Bills successfully acquired', 'bills' => $bills
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }

    public function getExpBills(Request $request)
    {
        try {
            $user = $request->get('user');
            if (!$user || !$user->get('jusr_rif')) {
                return response()->json(['success' => false, 'message' => 'You dont are a juristic user'], 422);
            }
            $bills = Bill::where(function ($query) use ($user) {
                $query->where('bil_emitter', $user->jusr_rif)->orWhere('bil_receiver', $user->jusr_rif);
            })
                ->whereDate('bil_expdate', '<', Carbon::now()->format('Y-m-d'))->where('bil_status', 0)
                ->when($request->get('start'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '>=', $request->start);
                })
                ->when($request->get('end'), function ($query) use ($request) {
                    return $query->whereDate('bil_created_at', '<=', $request->end);
                })->orderBy('bil_created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true, 'message' => 'Bill successfully created', 'bills' => $bills
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }
}
