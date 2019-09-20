<?php

namespace App\Http\Controllers;

use Mail;
use App\User;
use App\JuristicUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function signupActivate($token)
    {
        $user = User::where('activation_token', $token)->first();
        if (!$user) return response()->json(['message' => 'Activation token is invalid'], 404);

        $jusr_user = JuristicUser::where('jusr_user', $user->id)->first();
        if (!$jusr_user) {
            $user->type = 1;
        } else {
            $user->type = 2;
        }

        $user->activation_token = '';
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        $token->save();
        $user->save();
        DB::commit();

        return response()->json([
            'user' => $user,
            'juridic_user' => $user->type == 2 ? $jusr_user : false,
            'access_token' => $tokenResult->accessToken,
            'token_type'   => 'Bearer',
            'expires_at'   => Carbon::parse(
                $tokenResult->token->expires_at
            )
                ->toDateTimeString(),
        ]);
    }
    public function signup(Request $request)
    {
        DB::beginTransaction();

        $rules = [
            'opt_ci'    => 'required|string',
            'user_ci'   => 'required|string|unique:users',
            'first_name'     => 'required|string',
            'middle_name' => 'required|string',
            'first_surname'     => 'required|string',
            'second_surname' => 'required|string',
            'email'    => 'required|string|email|unique:users',
            'a_recovery'    => 'required|string',
            'q_recovery'    => 'required|string',
            'address' => 'required|string|',
            'phone' => 'required|string',
            'type' => 'required|string',
            'password' => 'required|string|confirmed'
        ];
        $errors = $this->validateRequest($request, $rules);
        if (count($errors)) {
            return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
        }
        try {
            $user = new User([
                'user_ci'   => $request->opt_ci . $request->user_ci,
                'first_name'     => $request->first_name,
                'middle_name' => $request->middle_name,
                'first_surname'     => $request->first_surname,
                'second_surname' => $request->second_surname,
                'email'    => $request->email,
                'q_recovery'     => $request->q_recovery,
                'a_recovery'    => $request->a_recovery,
                'address'   => $request->address,
                'phone' => $request->phone,
                'type'  => 0,
                'password' => bcrypt($request->password),
                'activation_token'  => Str::random(60),
            ]);

            $user->save();
        } catch (Swift_TransportException $a) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'Error creando el usuario',
                'exception' => $a
            ], 500);
        }

        if ($request->input('type') == 2) {
            $rules = [
                'jusr_rif'     => 'required|string|unique:juristic_users',
                'jusr_company' => 'required|string',
                'jusr_address' => 'required|string|',
                'jusr_phone' => 'required|string',
                'password' => 'required|string|confirmed',
            ];
            $errors = $this->validateRequest($request, $rules);
            if (count($errors)) {
                return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
            }
            try {
                $jusr_user = new JuristicUser([
                    'jusr_rif'     => $request->jusr_rif,
                    'jusr_user' => $user->id,
                    'jusr_company' => $request->jusr_company,
                    'jusr_address'     => $request->jusr_address,
                    'jusr_phone' => $request->jusr_phone,
                    // 'jusr_email'    => $request->email,
                    // 'password' => $user->password,
                ]);
                $jusr_user->save();
            } catch (Swift_TransportException $a) {
                DB::rollBack();
                return response()->json([
                    'error' => true,
                    'message' => 'Error creando el usuario juridico',
                    'exception' => $a
                ], 500);
            }
        }
        $url = env('CLIENT_URL') . 'confirm-account/' . $user->activation_token;

        $data = array('first_name' => $user->first_name, 'middle_name' => $user->middle_name, 'first_surname' => $user->first_surname, 'second_surname' => $user->second_surname, 'url' => $url, 'client_url' => env('CLIENT_URL'));
        Mail::send('emails.usr_activation', $data, function ($message) use ($user) {
            $message->from('banco-1@evenciohernandez.com.ve', 'Banco 1');
            $message->replyTo('banco-1@evenciohernandez.com.ve', 'Banco 1');
            $message->to($user->email, $user->first_name . ' ' . $user->first_surname)->subject('Confirma tu usuario');
        });
        DB::commit();
        return response()->json([
            'message' => 'Se ha registrado correctamente, solo falta confirmación de su correo electronico'
        ], 200);
    }
    public function login(Request $request)
    {
        $rules = [
            'email'       => 'required|string|email',
            'password'    => 'required|string',
            'usr_remember_me' => 'boolean',
        ];

        $errors = $this->validateRequest($request, $rules);
        if (count($errors)) {
            return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
        }

        $credentials = request(['email', 'password']);
        $credentials['type'] = $request->input('type');
        $credentials['deleted_at'] = null;
        try {
            DB::beginTransaction();
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Datos invalidos o cuenta no confirmada'
                ], 401);
            }
            $user = $request->user();
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->save();
            if ($request->remember_me) {
                $token->expires_at = Carbon::now()->addWeeks(1);
            }

            if($user->type == 2) $jusr_user = JuristicUser::where('jusr_user', $user->id)->first();

            DB::commit();
            return response()->json([
                'user' => $user->type == 2 ? $jusr_user : $user,
                'access_token' => $tokenResult->accessToken,
                'token_type'   => 'Bearer',
                'expires_at'   => Carbon::parse(
                    $tokenResult->token->expires_at
                )
                    ->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' =>
        'Se ha desconectado correctamente', 'logout' => true]);
    }

    public function user(Request $request)
    {
        try {
            $user = $request->user;
            if ($request->input('type') == 2) {
                $jusr_user = JuristicUser::where('jusr_user', $user->id)->first();
                return response()->json(['success' => true, 'user' => $jusr_user]);
            } else {
                return response()->json(['success' => true, 'user' => $user]);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }
}
