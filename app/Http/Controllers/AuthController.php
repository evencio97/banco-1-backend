<?php

namespace App\Http\Controllers;

use Mail;
use App\User;
use App\JuristicUser;
use App\CreditCard;
use App\Audit;
use App\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\AccessToken;
use App\AuthClient;
use Illuminate\Support\Facades\Hash;

use \Firebase\JWT\JWT;

class AuthController extends BaseController
{
    public function generateTDC($user, $request){
        $digits = 17;
        do {
            $number = "001" . strval(random_int(pow(10, $digits-1), pow(10, $digits)-1));
            $cvv = random_int(pow(10, 3-1), pow(10, 3)-1);
        } while (CreditCard::find($number) || CreditCard::where('cc_cvv', $cvv)->first());
        
        $limit = rand(200000, 1000000);
        $tdc = new CreditCard([
            'cc_number' => $number,
            'cc_user' => $user->id,
            'cc_exp_date' => Carbon::now()->addYears(4)->format('Y-m-d'),
            'cc_cvv' => $cvv,
            'cc_balance' => 0,
            'cc_limit' => $limit,
            'cc_interests' => rand(8, 12),
            'cc_minimum_payment' => rand(0, $limit),
            'cc_payment_date' => null,
        ]);
        $tdc->save();
        Audit::saveAudit($user->id, 'users', $number, 'credit_cards', 'create', $request->ip());
    }
    public function generateAccount($user, $request){
        $digits = 17;
        do {
            $number = "001" . strval(random_int(pow(10, $digits-1), pow(10, $digits)-1));
        } while (Account::find($number));
        
        $account = new Account([
            'aco_number' => $number,
            'aco_user' => $user->id,
            'aco_user_table' => isset($user->user_ci) ? 'users':'juristic_users',
            'aco_balance' => 0,
            'aco_type' => isset($request->type) ? $request->type : 3,
            'aco_status' => 1,
        ]);
        $account->save();

        Audit::saveAudit($account->aco_user, $account->aco_user_table, $number, 'accounts', 'create', $request->ip());
    }
    public function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public function signupActivate(Request $request, $token)
    {
        $user = JuristicUser::where('activation_token', $token)->first();

        if (!$user) {
            $user = User::where('activation_token', $token)->first();
            if (!$user) return response()->json(['message' => 'El token de activación es invalido'], 404);
        }

        try {
            DB::beginTransaction();
            $user->active = 1;
            $user->activation_token = '';
            $iat = time();
            $exp = $iat + (60 * 60);
            $key = $this->generateRandomString();

            $token = array(
                'iat' => $iat,
                'exp' => $exp,
                'data' => $user
            );

            $jwt = JWT::encode($token, $key);
            $authclient = new AuthClient([
                'user_id'   =>  isset($user->user_ci) ? $user->user_ci:$user->jusr_rif,
                'secret'    =>  $key,
                'client_url'    =>  $request->root(),
                'ip'    => $request->ip(),
                'revoked'   =>  0
            ]);
            //Agregamos el cliente asociado al token con el secret
            $authclient->save();
            $accesstoken = new AccessToken([
                'client_id' =>  $authclient->id,
                'user_id'   =>  isset($user->user_ci) ? $user->user_ci:$user->jusr_rif,
                'token' =>  $jwt,
                'revoked'   =>  0
            ]);
            //Agregamos el token de acceso asociada al cliente anterior y al usuario
            $accesstoken->save();
            //Guardamos el usuario
            $user->save();
            $this->generateAccount($user, $request);
            $this->generateTDC($user, $request);
            
            DB::commit();
            return response()->json([
                'success'   => true,
                'user' => $user,
                'access_token' => $jwt,
                'iat'   => $iat,
                'exp'   => $exp
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }
    public function signup(Request $request)
    {
        try {
            DB::beginTransaction();
            $type = filter_var($_GET['type'], FILTER_VALIDATE_INT);
            $check = filter_var($_GET['check'], FILTER_VALIDATE_BOOLEAN);
            if ($request->input('type') == 1 || !$check) {
                $rules = [
                    'opt_ci'    => 'required|string',
                    'user_ci'   => 'required|string|unique:users',
                    'first_name'     => 'required|string',
                    'middle_name' => 'required|string',
                    'first_surname'     => 'required|string',
                    'second_surname' => 'required|string',
                    'email'    => 'required|string|email|unique:users',
                    'a_recovery'    => 'required|string',
                    'type'  =>  'required|string',
                    'q_recovery'    => 'required|string',
                    'address' => 'required|string|',
                    'phone' => 'required|string',
                    'password' => 'required|string|confirmed'
                ];
                $errors = $this->validateRequest($request, $rules);
                if (count($errors)) {
                    return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
                }

                $user = new User([
                    'user_ci'   => $request->opt_ci . $request->user_ci,
                    'first_name'     => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'first_surname'     => $request->first_surname,
                    'second_surname' => $request->second_surname,
                    'email'    => $request->email,
                    'q_recovery'     => $request->q_recovery,
                    'a_recovery'    => $request->a_recovery,
                    'type'  => $request->type,
                    'address'   => $request->address,
                    'phone' => $request->phone,
                    'active'  => 0,
                    'password' => bcrypt($request->password),
                    'activation_token'  => Str::random(60),
                ]);
                //Se registra usuario natural
                $user->save();
            } else {
                $rules = [
                    'opt_ci'    => 'required|string',
                    'user_ci'   => 'required|string|unique:users',
                    'password'  => 'required|string|confirmed'
                ];
                $errors = $this->validateRequest($request, $rules);
                if (count($errors)) {
                    return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
                }
                //Se obtiene usuario natural ya registrado
                $user = User::where('user_ci', $request->opt_ci . $request->user_ci)->first();
                if (!$user) return response()->json(['success' => false, 'message' => 'No existe una cuenta afiliada a una persona con esa cédula'], 404);
                if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Contraseña invalida'], 404);
            }

            try {
                if ($type == 2) {
                    $rules = [
                        'opt_rif'   =>  'required|string',
                        'jusr_rif'     => 'required|string|unique:juristic_users',
                        'jusr_email'    => 'required|string|unique:juristic_users',
                        'jusr_q_recovery'     => 'required|string',
                        'jusr_a_recovery'    => 'required|string',
                        'jusr_company' => 'required|string',
                        'jusr_address' => 'required|string|',
                        'jusr_phone' => 'required|string',
                        'jusr_password' => 'required|string|confirmed',
                    ];
                    $errors = $this->validateRequest($request, $rules);
                    if (count($errors)) {
                        return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
                    }
                    $jusr_user = new JuristicUser([
                        'jusr_rif'     => $request->opt_rif . $request->jusr_rif,
                        'jusr_user' => $user->id,
                        'jusr_company' => $request->jusr_company,
                        'jusr_address'     => $request->jusr_address,
                        'jusr_phone' => $request->jusr_phone,
                        'jusr_email'    => $request->jusr_email,
                        'active'   => 0,
                        'q_recovery'     => $request->jusr_q_recovery,
                        'a_recovery'    => $request->jusr_a_recovery,
                        'password' => bcrypt($request->jusr_password),
                        'activation_token'  => Str::random(60),
                    ]);
                    //Se registra al usuario juridico
                    $jusr_user->save();
                    $url = env('CLIENT_URL') . 'confirm-account/' . $jusr_user->activation_token;
                    $data = array('jusr_company' => $jusr_user->jusr_company, 'admin_name' => $user->first_name, 'admin_surname' => $user->first_surname, 'url' => $url, 'client_url' => env('CLIENT_URL'));
                    Mail::send('emails.juristic_activation', $data, function ($message) use ($jusr_user) {
                        $message->from(env('MAIL_USERNAME'), 'Banco 1');
                        $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                        $message->to($jusr_user->jusr_email, $jusr_user->jusr_company)->subject('Confirma tu cuenta');
                    });
                }
                $url = env('CLIENT_URL') . 'confirm-account/' . $user->activation_token;
                $data = array('first_name' => $user->first_name, 'middle_name' => $user->middle_name, 'first_surname' => $user->first_surname, 'second_surname' => $user->second_surname, 'url' => $url, 'client_url' => env('CLIENT_URL'));
                Mail::send('emails.usr_activation', $data, function ($message) use ($user) {
                    $message->from(env('MAIL_USERNAME'), 'Banco 1');
                    $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                    $message->to($user->email, $user->first_name . ' ' . $user->first_surname)->subject('Confirma tu cuenta');
                });
            } catch (Swift_TransportException $a) {
                DB::rollBack();
                return response()->json([
                    'error' => true,
                    'message' => 'Error creando el usuario juridico',
                    'exception' => $a
                ], 500);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
        DB::commit();
        return response()->json([
            'success'   => true,
            'message' => 'Se ha registrado correctamente, solo falta confirmación de su correo electrónico'
        ], 200);
    }

    public function getToken($user, $request)
    {
        $matchThese = ['user_id' => isset($user->user_ci) ? $user->user_ci:$user->jusr_rif, 'client_url' => $request->root(), 'ip' => $request->ip(), 'revoked' => 0];
        $authclient = AuthClient::where($matchThese)->first();
        if (!$authclient) {
            $key = $this->generateRandomString();
            $authclient = new AuthClient([
                'user_id'   =>  isset($user->user_ci) ? $user->user_ci:$user->jusr_rif,
                'secret'    =>  $key,
                'client_url'    => $request->root(),
                'ip'    =>  $request->ip(),
                'revoked'   =>  0
            ]);
            //Agregamos el cliente asociado al token con el secret
            $authclient->save();
        } else {
            $key = $authclient->secret;
        }
        return ['key' => $key, 'auth_id' => $authclient->id];
    }

    public function login(Request $request)
    {
        $type = filter_var($_GET['type'], FILTER_VALIDATE_INT);
        try {
            if ($type == 1) {
                $rules = [
                    'opt_ci'    => 'required|string',
                    'user_ci'       => 'required|string',
                    'password'    => 'required|string',
                    'usr_remember_me' => 'boolean',
                ];

                $errors = $this->validateRequest($request, $rules);
                if (count($errors)) {
                    return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
                }

                $user = User::where('user_ci', $request->opt_ci . $request->user_ci)->first();
                if (!$user) return response()->json(['success' => false, 'message' => 'No existe una cuenta afiliada a una persona con esa cédula'], 404);
                if ($user->active == 0) return response()->json(['success' => false, 'message' => 'Recuerde que debe activar su cuenta antes de poder generar una sesión'], 404);
                if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Contraseña invalida'], 404);
            } else {
                $rules = [
                    'opt_rif'    => 'required|string',
                    'jusr_rif'       => 'required|string',
                    'password'    => 'required|string',
                    'usr_remember_me' => 'boolean',
                ];

                $errors = $this->validateRequest($request, $rules);
                if (count($errors)) {
                    return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
                }
                $user = JuristicUser::where('jusr_rif', $request->opt_rif . $request->jusr_rif)->first();
                if (!$user) return response()->json(['success' => false, 'message' => 'No existe una cuenta afiliada a una empresa con ese rif']);
                if ($user->active == 0) return response()->json(['success' => false, 'message' => 'Recuerde que debe activar su cuenta antes de poder generar una sesión'], 404);
                if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Contraseña invalida']);
            }

            $iat = time();
            $exp = $iat + (60 * 60);
            $key = $this->getToken($user, $request);

            $token = array(
                'iat' => $iat,
                'exp' => $exp,
                'data' => $user
            );

            //Creamos el token
            $jwt = JWT::encode($token, $key['key']);

            $accesstoken = new AccessToken([
                'client_id' =>  $key['auth_id'],
                'user_id'   =>  isset($user->user_ci) ? $user->user_ci:$user->jusr_rif,
                'token' =>  $jwt,
                'revoked'   =>  0
            ]);
            //Agregamos el token de acceso asociada al cliente anterior y al usuario
            $accesstoken->save();

            return response()->json([
                'success'   => true,
                'user' => $user,
                'access_token' => $jwt,
                'iat'   => $iat,
                'exp'   => $exp
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
        $user = $request->get('user');
        try {
            $matchThese = ['user_id' => isset($user->user_ci) ? $user->user_ci:$user->jusr_rif , 'ip' => $request->ip(), 'client_url' => $request->root(), 'revoked' => 0];
            $client = AuthClient::where($matchThese)->first();
            $tokenUpdate = AccessToken::where('client_id', $client->id)->update(['revoked' => 1]);
            return response()->json(['success' => true, 'message' => 'Sesión terminada', 'cont' =>  $tokenUpdate]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            return response()->json(['success' => true, 'user' => $request->get('user')]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }
}
